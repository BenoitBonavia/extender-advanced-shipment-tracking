<?php
/**
 * Action groupée « importer le suivi » sur la liste des commandes.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

use EAST\Integration\AdvancedShipmentTracking;
use EAST\Support\Logger;
use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Portage du second snippet WPCode (action groupée complémentaire) : mêmes
 * deux écrans déclarés — historique (`edit-shop_order`) et HPOS
 * (`woocommerce_page_wc-orders`) —, même plafond de sécurité, même trio de
 * paramètres de retour. Appelle directement `TrackingSync::sync()` : cette
 * action groupée est désormais une fonctionnalité du module, elle n'a plus de
 * raison de dépendre du nom d'une fonction du snippet remplacé.
 */
final class BulkAction {

	/**
	 * Identifiant de l'action, dans le menu déroulant de la liste des commandes.
	 */
	public const ACTION_KEY = 'east_boxtal_import_tracking_bulk';

	/**
	 * Accroche les hooks de l'action groupée.
	 */
	public function register(): void {
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_action' ), 60 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_action' ), 60 );

		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle' ), 10, 3 );

		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Ajoute l'entrée au menu déroulant.
	 *
	 * @param array $bulk_actions Actions existantes.
	 *
	 * @return array
	 */
	public function register_bulk_action( array $bulk_actions ): array {
		$bulk_actions[ self::ACTION_KEY ] = __( 'Boxtal → AST : importer le suivi', 'extender-advanced-shipment-tracking' );

		return $bulk_actions;
	}

	/**
	 * Traite l'action groupée.
	 *
	 * @param string $redirect_to URL de redirection.
	 * @param string $action      Action demandée.
	 * @param array  $ids         Identifiants de commandes sélectionnées.
	 *
	 * @return string
	 */
	public function handle( $redirect_to, $action, $ids ) {
		if ( self::ACTION_KEY !== $action ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return $redirect_to;
		}

		$ids   = array_map( 'absint', (array) $ids );
		$total = count( $ids );
		$max   = Config::bulk_max();
		$batch = array_slice( $ids, 0, $max );

		$skipped   = $total - count( $batch );
		$processed = 0;
		$added     = 0;

		foreach ( $batch as $order_id ) {
			// Comptage avant / après plutôt qu'une lecture du résultat de
			// TrackingSync::sync() (void) : mêmes garanties que le snippet
			// d'origine, sans faire porter à sync() un contrat de retour dont
			// le seul autre appelant (le déclencheur temps réel) n'a pas besoin.
			$before = count( AdvancedShipmentTracking::get_tracking_items( $order_id ) );

			TrackingSync::sync( $order_id );

			$after = count( AdvancedShipmentTracking::get_tracking_items( $order_id ) );

			++$processed;
			$added += max( 0, $after - $before );
		}

		if ( Settings::get_bool( 'enable_logging' ) ) {
			Logger::log(
				sprintf(
					'Action groupée : %d commande(s) traitée(s), %d suivi(s) ajouté(s), %d ignorée(s) (plafond %d).',
					$processed,
					$added,
					$skipped,
					$max
				)
			);
		}

		return add_query_arg(
			array(
				'east_bxt_processed' => $processed,
				'east_bxt_added'     => $added,
				'east_bxt_skipped'   => $skipped,
			),
			$redirect_to
		);
	}

	/**
	 * Affiche le résultat de l'action groupée.
	 */
	public function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- simple affichage de résultat, aucune action déclenchée par la lecture.
		if ( ! isset( $_GET['east_bxt_processed'] ) ) {
			return;
		}

		$processed = absint( $_GET['east_bxt_processed'] );
		$added     = isset( $_GET['east_bxt_added'] ) ? absint( $_GET['east_bxt_added'] ) : 0;
		$skipped   = isset( $_GET['east_bxt_skipped'] ) ? absint( $_GET['east_bxt_skipped'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$message = sprintf(
			/* translators: 1: nombre de commandes traitées, 2: nombre de suivis ajoutés. */
			esc_html__( 'Boxtal → AST : %1$d commande(s) traitée(s), %2$d numéro(s) de suivi ajouté(s).', 'extender-advanced-shipment-tracking' ),
			$processed,
			$added
		);

		$class = 'notice-success';

		if ( $skipped > 0 ) {
			$class    = 'notice-warning';
			$message .= ' ' . sprintf(
				/* translators: 1: nombre de commandes ignorées, 2: plafond configuré. */
				esc_html__( '%1$d commande(s) non traitée(s) : le lot est plafonné à %2$d. Relancez la sélection sur les commandes restantes.', 'extender-advanced-shipment-tracking' ),
				$skipped,
				Config::bulk_max()
			);
		}

		if ( 0 === $added && 0 === $skipped ) {
			$class    = 'notice-info';
			$message .= ' ' . esc_html__( 'Aucun nouveau numéro : soit ils étaient déjà présents, soit Boxtal n’a pas encore d’expédition. Détail dans WooCommerce > Statut > Journaux, source « extender-ast ».', 'extender-advanced-shipment-tracking' );
		}

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			wp_kses_post( $message )
		);
	}
}
