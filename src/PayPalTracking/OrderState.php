<?php
/**
 * Éligibilité d'une commande à l'envoi de suivi vers PayPal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

use EAST\Integration\AdvancedShipmentTracking;
use EAST\Integration\PayPalPayments;

defined( 'ABSPATH' ) || exit;

/**
 * Portage de `mh_ppt_order_state()`.
 *
 * Seul changement délibéré par rapport au snippet : la lecture des items de
 * suivi passe par `Integration\AdvancedShipmentTracking::get_tracking_items()`
 * plutôt que par un accès direct à la méta `_wc_shipment_tracking_items` —
 * comportement strictement identique (la méthode lit cette même méta non
 * formatée), mais toute connaissance de la meta interne d'AST reste dans
 * `Integration\`.
 */
final class OrderState {

	/**
	 * Évalue l'état d'une commande vis-à-vis de PayPal.
	 *
	 * @param mixed $order Commande, ou toute autre valeur (retourne « inéligible »).
	 *
	 * @return array{
	 *     order_id: int,
	 *     eligible: bool,
	 *     reason: string,
	 *     capture_id: string,
	 *     items: array<int, array<string, mixed>>,
	 *     pending: string[],
	 *     sent: string[]
	 * }
	 */
	public static function evaluate( $order ): array {
		$state = array(
			'order_id'   => 0,
			'eligible'   => false,
			'reason'     => '',
			'capture_id' => '',
			'items'      => array(),
			'pending'    => array(),
			'sent'       => array(),
		);

		if ( ! $order instanceof \WC_Order ) {
			$state['reason'] = __( 'commande introuvable', 'extender-advanced-shipment-tracking' );

			return $state;
		}

		$state['order_id'] = $order->get_id();

		if ( ! PayPalPayments::uses_gateway( $order ) ) {
			$state['reason'] = __( 'paiement hors PayPal Payments', 'extender-advanced-shipment-tracking' );

			return $state;
		}

		if ( '' === PayPalPayments::paypal_order_id( $order ) ) {
			$state['reason'] = __( 'identifiant de commande PayPal absent', 'extender-advanced-shipment-tracking' );

			return $state;
		}

		$capture_id = PayPalPayments::capture_id( $order );

		if ( '' === $capture_id ) {
			$state['reason'] = __( 'paiement non capturé (pas de transaction ID)', 'extender-advanced-shipment-tracking' );

			return $state;
		}

		$state['capture_id'] = $capture_id;

		$items = self::tracking_items( $order->get_id() );

		if ( ! $items ) {
			$state['reason'] = __( 'aucun numéro de suivi dans AST', 'extender-advanced-shipment-tracking' );

			return $state;
		}

		$state['items'] = $items;

		$sent = self::sent_numbers( $order );

		foreach ( $items as $item ) {
			$number = trim( (string) $item['tracking_number'] );

			if ( isset( $sent[ $number ] ) ) {
				$state['sent'][] = $number;
			} else {
				$state['pending'][] = $number;
			}
		}

		$state['eligible'] = true;
		$state['reason']   = $state['pending']
			? __( 'à envoyer', 'extender-advanced-shipment-tracking' )
			: __( 'déjà envoyé', 'extender-advanced-shipment-tracking' );

		return $state;
	}

	/**
	 * Items de suivi AST portant un numéro exploitable.
	 *
	 * @param int $order_id Commande.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function tracking_items( int $order_id ): array {
		$clean = array();

		foreach ( AdvancedShipmentTracking::get_tracking_items( $order_id ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$number = isset( $item['tracking_number'] ) ? trim( (string) $item['tracking_number'] ) : '';

			if ( '' === $number ) {
				continue;
			}

			$clean[] = $item;
		}

		return $clean;
	}

	/**
	 * Numéros de suivi déjà transmis à PayPal pour cette commande.
	 *
	 * Fusionne le journal de PPCP (idempotence côté PayPal) et le nôtre
	 * (`Legacy::META_SENT`) : soit l'un soit l'autre suffit à considérer un
	 * numéro comme déjà transmis.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return array<string, bool>
	 */
	private static function sent_numbers( \WC_Order $order ): array {
		$sent = array();

		$ppcp = $order->get_meta( Legacy::META_PPCP_SENT );

		if ( is_array( $ppcp ) ) {
			foreach ( array_keys( $ppcp ) as $number ) {
				$sent[ (string) $number ] = true;
			}
		}

		$mine = $order->get_meta( Legacy::META_SENT );

		if ( is_array( $mine ) ) {
			foreach ( array_keys( $mine ) as $number ) {
				$sent[ (string) $number ] = true;
			}
		}

		return $sent;
	}
}
