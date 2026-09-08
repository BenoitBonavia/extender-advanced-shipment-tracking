<?php
/**
 * Envoi du suivi AST vers PayPal Package Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

use EAST\Integration\PayPalPayments;
use EAST\Support\Logger;
use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Portage de `mh_ppt_push_tracking()`.
 *
 * On ne réimplémente aucun client REST PayPal : tout passe par les services
 * internes de PayPal Payments (`Integration\PayPalPayments::tracking_services()`),
 * qui réutilisent la connexion déjà établie par l'extension — aucun identifiant
 * ni jeton à gérer ici.
 */
final class Push {

	/**
	 * Hook de l'envoi différé, déclenché après ajout d'un suivi dans AST.
	 *
	 * Propre à ce plugin, groupe Action Scheduler `east` partagé avec le reste
	 * du plugin — le snippet remplacé utilisait son propre groupe
	 * `mh-paypal-tracking`, consolidé ici.
	 */
	public const HOOK_PUSH = 'east_paypal_tracking_push';

	/**
	 * Transmet à PayPal tous les suivis AST d'une commande qui ne le sont pas
	 * déjà.
	 *
	 * @param int $order_id Commande.
	 *
	 * @return array{sent:int, skipped:int, failed:int, message:string}
	 */
	public static function push( $order_id ): array {
		$result = array(
			'sent'    => 0,
			'skipped' => 0,
			'failed'  => 0,
			'message' => '',
		);

		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			$result['message'] = __( 'identifiant de commande invalide', 'extender-advanced-shipment-tracking' );

			return $result;
		}

		$order = wc_get_order( $order_id );
		$state = OrderState::evaluate( $order );

		if ( ! $state['eligible'] ) {
			$result['message'] = $state['reason'];

			return $result;
		}

		if ( ! $state['pending'] ) {
			$result['skipped'] = count( $state['sent'] );
			$result['message'] = __( 'déjà envoyé', 'extender-advanced-shipment-tracking' );

			return $result;
		}

		$services = PayPalPayments::tracking_services();

		if ( ! $services ) {
			$result['message'] = __( 'services PayPal Payments indisponibles', 'extender-advanced-shipment-tracking' );

			Logger::error( sprintf( 'Commande #%d : %s.', $order_id, $result['message'] ) );

			return $result;
		}

		list( $factory, $endpoint ) = $services;

		$capture_id = $state['capture_id'];
		$pending    = array_flip( $state['pending'] );
		$journal    = array();

		foreach ( $state['items'] as $item ) {
			$number = trim( (string) $item['tracking_number'] );

			if ( ! isset( $pending[ $number ] ) ) {
				++$result['skipped'];
				continue;
			}

			list( $carrier, $carrier_other ) = CarrierMap::map_carrier(
				isset( $item['tracking_provider'] ) ? (string) $item['tracking_provider'] : '',
				isset( $item['custom_tracking_provider'] ) ? (string) $item['custom_tracking_provider'] : ''
			);

			try {
				// Le 7e argument vide signifie « toutes les lignes de la commande ».
				$shipment = $factory->create_shipment(
					(int) $order_id,
					(string) $capture_id,
					(string) $number,
					Config::status(),
					(string) $carrier,
					(string) $carrier_other,
					array()
				);
			} catch ( \Throwable $exception ) {
				++$result['failed'];

				Logger::error(
					sprintf( 'Commande #%d : création du shipment %s impossible : %s', $order_id, $number, $exception->getMessage() )
				);

				continue;
			}

			if ( Config::debug() && self::logging_enabled() && method_exists( $shipment, 'to_array' ) ) {
				Logger::log( sprintf( 'Commande #%d : charge utile = %s', $order_id, wp_json_encode( $shipment->to_array() ) ), 'debug' );
			}

			list( $ok, $how, $error ) = self::send( $endpoint, $shipment, $order_id, $number );

			if ( $ok ) {
				++$result['sent'];

				$journal[ $number ] = array(
					'at'      => time(),
					'carrier' => $carrier,
					'other'   => $carrier_other,
					'mode'    => $how,
				);

				if ( self::logging_enabled() ) {
					Logger::log(
						sprintf(
							'Commande #%d : suivi %s transmis à PayPal (%s). Transporteur : %s%s.',
							$order_id,
							$number,
							$how,
							$carrier,
							$carrier_other ? ' / ' . $carrier_other : ''
						)
					);
				}
			} else {
				++$result['failed'];

				Logger::error( sprintf( 'Commande #%d : échec sur le suivi %s. %s', $order_id, $number, $error ) );
			}
		}

		if ( $journal ) {
			// Relecture obligatoire : add_tracking_information() a fait un
			// save() sur la commande pour écrire sa propre meta.
			$fresh = wc_get_order( $order_id );

			if ( $fresh instanceof \WC_Order ) {
				$previous = $fresh->get_meta( Legacy::META_SENT );
				$previous = is_array( $previous ) ? $previous : array();

				$fresh->update_meta_data( Legacy::META_SENT, array_merge( $previous, $journal ) );
				$fresh->add_order_note(
					sprintf(
						/* translators: %d: nombre de numéros de suivi transmis. */
						_n(
							'%d numéro de suivi transmis à PayPal.',
							'%d numéros de suivi transmis à PayPal.',
							count( $journal ),
							'extender-advanced-shipment-tracking'
						),
						count( $journal )
					)
				);
				$fresh->save();
			}

			Scan::forget();
		}

		$result['message'] = sprintf(
			'%d envoyé(s), %d ignoré(s), %d en échec',
			$result['sent'],
			$result['skipped'],
			$result['failed']
		);

		return $result;
	}

	/**
	 * Transmet un envoi à PayPal, avec repli en mise à jour si un tracker
	 * existe déjà côté PayPal pour ce numéro.
	 *
	 * @param object $endpoint Contrôleur d'envoi PPCP.
	 * @param object $shipment Envoi construit par la fabrique PPCP.
	 * @param int    $order_id Commande.
	 * @param string $number   Numéro de suivi, pour l'appel de reprise.
	 *
	 * @return array{0: bool, 1: string, 2: string} [succès, mode, message d'erreur].
	 */
	private static function send( $endpoint, $shipment, int $order_id, string $number ): array {
		try {
			$endpoint->add_tracking_information( $shipment, $order_id );

			return array( true, 'création', '' );
		} catch ( \Throwable $exception ) {
			$error = $exception->getMessage();
		}

		// PayPal refuse la création si le tracker existe déjà côté PayPal. On
		// tente alors une mise à jour, comme le fait l'intégration officielle
		// WC Shipment Tracking.
		try {
			$existing = $endpoint->get_tracking_information( $order_id, $number );

			if ( $existing ) {
				$endpoint->update_tracking_information( $shipment, $order_id );

				return array( true, 'mise à jour', '' );
			}
		} catch ( \Throwable $exception ) {
			$error .= ' | reprise en mise à jour : ' . $exception->getMessage();
		}

		return array( false, '', $error );
	}

	/**
	 * Les entrées de niveau info/warning sont-elles journalisées ?
	 *
	 * Les erreurs (services PPCP indisponibles, échec d'envoi) le sont
	 * toujours, sans condition.
	 *
	 * @return bool
	 */
	private static function logging_enabled(): bool {
		return Settings::get_bool( 'enable_logging' );
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
