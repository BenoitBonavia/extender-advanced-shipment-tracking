<?php
/**
 * Action manuelle « importer le suivi » sur la fiche commande.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Rattrapage : rejoue la synchronisation pour une commande donnée, sans
 * attendre le déclencheur `boxtal_connect_order_shipped` ni une relance
 * programmée. Sert aussi de test manuel pendant la mise en place.
 */
final class OrderAction {

	/**
	 * Identifiant de l'action, dans le menu déroulant « Actions » de la commande.
	 *
	 * Renommé par rapport au snippet (`mh_bxt_import_tracking`) : ce n'est
	 * qu'une entrée de menu, aucune donnée n'en dépend.
	 */
	public const ID = 'east_boxtal_import_tracking';

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
		$actions[ self::ID ] = __( 'Boxtal → AST : importer le suivi', 'extender-advanced-shipment-tracking' );

		return $actions;
	}

	/**
	 * Exécute l'action.
	 *
	 * @param \WC_Order $order Commande.
	 */
	public function run( \WC_Order $order ): void {
		TrackingSync::sync( $order->get_id() );
	}
}
