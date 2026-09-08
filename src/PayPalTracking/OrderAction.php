<?php
/**
 * Action manuelle « envoyer le suivi » sur la fiche commande.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Rejoue l'envoi vers PayPal pour une commande donnée, sans attendre le
 * déclencheur temps réel ni l'écran de rattrapage. Même gabarit que
 * `BoxtalTracking\OrderAction`.
 */
final class OrderAction {

	/**
	 * Identifiant de l'action, dans le menu déroulant « Actions » de la commande.
	 *
	 * Renommé par rapport au snippet (`mh_ppt_push`) : ce n'est qu'une entrée
	 * de menu, aucune donnée n'en dépend.
	 */
	public const ID = 'east_paypal_push_tracking';

	/**
	 * Accroche les hooks de l'action.
	 */
	public function register(): void {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_action' ) );
		add_action( 'woocommerce_order_action_' . self::ID, array( $this, 'run' ) );
	}

	/**
	 * Ajoute l'action à la liste.
	 *
	 * @param array $actions Actions existantes.
	 *
	 * @return array
	 */
	public function add_action( array $actions ): array {
		$actions[ self::ID ] = __( 'AST → PayPal : envoyer le suivi', 'extender-advanced-shipment-tracking' );

		return $actions;
	}

	/**
	 * Exécute l'action.
	 *
	 * @param \WC_Order $order Commande.
	 */
	public function run( \WC_Order $order ): void {
		Push::push( $order->get_id() );
	}
}
