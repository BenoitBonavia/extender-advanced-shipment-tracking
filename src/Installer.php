<?php
/**
 * Activation / désactivation du plugin.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST;

defined( 'ABSPATH' ) || exit;

/**
 * Gère les routines d'installation et de nettoyage à chaud.
 */
final class Installer {

	/**
	 * Option stockant la version installée (utile pour les futures migrations).
	 */
	public const VERSION_OPTION = 'east_version';

	/**
	 * Routine d'activation.
	 */
	public static function activate(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( EAST_BASENAME );

			wp_die(
				esc_html__(
					'Extender for Advanced Shipment Tracking nécessite WooCommerce. Activez WooCommerce puis réessayez.',
					'extender-advanced-shipment-tracking'
				),
				esc_html__( 'Prérequis manquant', 'extender-advanced-shipment-tracking' ),
				array( 'back_link' => true )
			);
		}

		if ( ! \EAST\Integration\AdvancedShipmentTracking::is_active() ) {
			deactivate_plugins( EAST_BASENAME );

			wp_die(
				esc_html__(
					'Extender for Advanced Shipment Tracking nécessite Advanced Shipment Tracking for WooCommerce. Activez cette extension puis réessayez.',
					'extender-advanced-shipment-tracking'
				),
				esc_html__( 'Prérequis manquant', 'extender-advanced-shipment-tracking' ),
				array( 'back_link' => true )
			);
		}

		self::maybe_upgrade();

		do_action( 'east_activated' );
	}

	/**
	 * Routine de désactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'east_daily_maintenance' );

		do_action( 'east_deactivated' );
	}

	/**
	 * Exécute les migrations entre versions puis met à jour le marqueur.
	 *
	 * Volontairement publique et idempotente : WordPress n'exécute pas le hook
	 * d'activation lors d'une mise à jour de plugin, elle doit donc pouvoir être
	 * appelée depuis une requête ordinaire (cf. Plugin::boot).
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '' );

		if ( EAST_VERSION === $installed ) {
			return;
		}

		/**
		 * Point d'accroche pour les migrations de données.
		 *
		 * @param string $installed Version précédemment installée ('' si première install).
		 */
		do_action( 'east_upgrade', $installed );

		update_option( self::VERSION_OPTION, EAST_VERSION, false );
	}
}
