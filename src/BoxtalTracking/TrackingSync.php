<?php
/**
 * Cœur du pont Boxtal → Advanced Shipment Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

use EAST\Integration\AdvancedShipmentTracking;
use EAST\Integration\Boxtal;
use EAST\Support\Logger;
use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Importe dans AST les numéros de suivi que Boxtal a générés pour une commande.
 *
 * Portage direct de `mh_bxt_sync_tracking_to_ast()` : même forme de réponse API
 * lue (`shipmentsTracking[] → parcelsTracking[] → reference / trackingUrl`),
 * mêmes clés `$args` transmises à AST, même logique de relance. Seuls les
 * accès aux extensions hôtes changent de chemin, vers `Integration\*`.
 */
final class TrackingSync {

	/**
	 * Hook des relances programmées.
	 *
	 * Propre à ce plugin — remplace `mh_bxt_retry_event` du snippet. Un
	 * événement déjà programmé par le snippet au moment de la bascule
	 * s'exécutera simplement sans effet : aucune donnée n'en dépend.
	 */
	public const RETRY_HOOK = 'east_boxtal_tracking_retry';

	/**
	 * Synchronise le suivi Boxtal d'une commande vers AST.
	 *
	 * @param int $order_id Commande.
	 * @param int $attempt  Numéro de tentative (1 au premier passage).
	 */
	public static function sync( int $order_id, int $attempt = 1 ): void {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return;
		}

		if ( ! AdvancedShipmentTracking::is_active() ) {
			Logger::error( sprintf( 'Commande #%d : Advanced Shipment Tracking introuvable, abandon.', $order_id ) );

			return;
		}

		if ( ! Boxtal::is_active() ) {
			Logger::error( sprintf( 'Commande #%d : Boxtal Connect introuvable, abandon.', $order_id ) );

			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$tracking = Boxtal::get_order_tracking( $order_id );

		if ( Config::debug() && self::logging_enabled() ) {
			// Logger::log() plutôt que Logger::debug() : ce dump est piloté par
			// Config::debug(), pas par WP_DEBUG, qui est un réglage distinct.
			Logger::log( sprintf( 'Commande #%d : réponse API Boxtal = %s', $order_id, wp_json_encode( $tracking ) ), 'debug' );
		}

		if ( ! is_object( $tracking )
			|| ! property_exists( $tracking, 'shipmentsTracking' )
			|| empty( $tracking->shipmentsTracking ) ) {
			self::schedule_retry( $order_id, $attempt, 'aucune expédition renvoyée par l’API Boxtal' );

			return;
		}

		$result = self::import( $order, $tracking );

		if ( 0 === $result['found'] ) {
			self::schedule_retry( $order_id, $attempt, 'expédition présente mais aucun colis avec référence' );
		}
	}

	/**
	 * Importe dans AST le suivi déjà récupéré auprès de Boxtal pour une commande.
	 *
	 * Partagée entre le déclencheur temps réel (`sync()`) et le rattrapage par
	 * lots (`Backfill::process()`) : même parcours
	 * `shipmentsTracking[] → parcelsTracking[]`, même dédoublonnage par numéro,
	 * même résolution de transporteur. Ne programme jamais de relance — c'est à
	 * l'appelant de décider si un résultat vide justifie une nouvelle tentative.
	 *
	 * @param \WC_Order $order    Commande.
	 * @param mixed     $tracking Réponse déjà validée (objet portant `shipmentsTracking`).
	 *
	 * @return array{found:int, added:int}
	 */
	public static function import( \WC_Order $order, $tracking ): array {
		$order_id = $order->get_id();

		// Numéros déjà enregistrés dans AST (anti-doublon : AST n'en fait aucun).
		$known = array();

		foreach ( AdvancedShipmentTracking::get_tracking_items( $order_id ) as $item ) {
			if ( is_array( $item ) && ! empty( $item['tracking_number'] ) ) {
				$known[] = CarrierResolver::slug( (string) $item['tracking_number'] );
			}
		}

		$url_map = $order->get_meta( Legacy::META_TRACKING_URLS );
		$url_map = is_array( $url_map ) ? $url_map : array();

		$found = 0;
		$added = 0;

		foreach ( (array) $tracking->shipmentsTracking as $shipment ) {
			if ( ! is_object( $shipment ) || empty( $shipment->parcelsTracking ) ) {
				continue;
			}

			foreach ( $shipment->parcelsTracking as $parcel ) {
				$number = ( is_object( $parcel ) && isset( $parcel->reference ) ) ? trim( (string) $parcel->reference ) : '';

				if ( '' === $number ) {
					continue;
				}

				++$found;

				$key = CarrierResolver::slug( $number );

				if ( in_array( $key, $known, true ) ) {
					continue;
				}

				$url = ( isset( $parcel->trackingUrl ) && $parcel->trackingUrl ) ? esc_url_raw( (string) $parcel->trackingUrl ) : '';

				list( $carrier, $source ) = CarrierResolver::resolve( $order, $url, $number );

				$args = array(
					'tracking_number' => $number,
					'date_shipped'    => current_time( 'Y-m-d' ),
					'status_shipped'  => Config::status_shipped() ? 1 : 0,
					'source'          => 'boxtal',
				);

				if ( $carrier ) {
					// Transporteur reconnu : logo, modèle d'URL, compatibilité TrackShip/PayPal.
					$args['tracking_provider']    = $carrier;
					$args['custom_tracking_link'] = $url; // Filet de sécurité.
				} else {
					// Inconnu : on n'invente rien, on pose le lien fourni par Boxtal.
					$args['tracking_provider']        = '';
					$args['custom_tracking_provider'] = __( 'Transporteur', 'extender-advanced-shipment-tracking' );
					$args['custom_tracking_link']     = $url;
				}

				AdvancedShipmentTracking::add_tracking_item( $order_id, $args );

				if ( $url ) {
					$url_map[ $key ] = $url;
				}

				$known[] = $key;
				++$added;

				if ( self::logging_enabled() ) {
					Logger::log(
						sprintf(
							'Commande #%d : suivi %s ajouté. Transporteur : %s (source : %s). Lien Boxtal : %s',
							$order_id,
							$number,
							$carrier ? $carrier : 'NON IDENTIFIÉ',
							$source,
							$url ? $url : 'aucun'
						),
						$carrier ? 'info' : 'warning'
					);
				}
			}
		}

		if ( $added > 0 ) {
			// Relecture : AST vient d'écrire ses propres métas sur la commande.
			$fresh = wc_get_order( $order_id );

			if ( $fresh instanceof \WC_Order ) {
				$fresh->update_meta_data( Legacy::META_TRACKING_URLS, $url_map );
				$fresh->add_order_note(
					sprintf(
						/* translators: %d: nombre de numéros de suivi importés. */
						_n(
							'%d numéro de suivi importé depuis Boxtal vers Advanced Shipment Tracking.',
							'%d numéros de suivi importés depuis Boxtal vers Advanced Shipment Tracking.',
							$added,
							'extender-advanced-shipment-tracking'
						),
						$added
					)
				);
				$fresh->save();
			}
		}

		return array(
			'found' => $found,
			'added' => $added,
		);
	}

	/**
	 * Reprogramme une tentative, jusqu'à la limite configurée.
	 *
	 * @param int    $order_id Commande.
	 * @param int    $attempt  Tentative venant de s'achever.
	 * @param string $reason   Raison de la relance, pour le journal.
	 */
	private static function schedule_retry( int $order_id, int $attempt, string $reason ): void {
		if ( $attempt >= Config::max_retry() ) {
			Logger::warning( sprintf( 'Commande #%d : abandon après %d tentatives (%s).', $order_id, $attempt, $reason ) );

			return;
		}

		wp_schedule_single_event(
			time() + Config::retry_delay(),
			self::RETRY_HOOK,
			array( $order_id, $attempt + 1 )
		);

		if ( self::logging_enabled() ) {
			Logger::log(
				sprintf( 'Commande #%d : %s. Tentative %d dans %d s.', $order_id, $reason, $attempt + 1, Config::retry_delay() )
			);
		}
	}

	/**
	 * Les entrées de niveau info/warning sont-elles journalisées ?
	 *
	 * Les erreurs (extension hôte manquante) le sont toujours, sans condition.
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
