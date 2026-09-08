<?php
/**
 * Point d'isolement de « Boxtal Connect ».
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Tout ce que ce plugin sait de la dépendance optionnelle Boxtal.
 *
 * Dépendance DOUCE, volontairement absente de l'en-tête `Requires Plugins` du
 * fichier principal : ce plugin s'installe et s'active sans Boxtal. Seuls les
 * modules qui la déclarent dans `AbstractModule::$dependencies` restent
 * indisponibles tant qu'elle manque — voir `AbstractModule::is_available()`.
 *
 * Relevé sur la version 2.0.2 (dépôt SVN WordPress.org, trunk) : slug, fichier
 * principal et classe uniquement. Aucun module de ce plugin n'appelle encore
 * son API ; à compléter (hooks consommés, réglages lus) au moment où le premier
 * module dépendant de Boxtal sera écrit — ne pas supposer cette classe à jour
 * au-delà de ce qui est vérifié ci-dessous.
 */
final class Boxtal implements HostPlugin {

	/**
	 * {@inheritDoc}
	 */
	public static function slug(): string {
		return 'boxtal-connect';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function basename(): string {
		return 'boxtal-connect/boxtal-connect.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function name(): string {
		return 'Boxtal Connect';
	}

	/**
	 * Nom pleinement qualifié de la classe principale de Boxtal Connect.
	 *
	 * Sert de test de présence : contrairement à Advanced Shipment Tracking,
	 * Boxtal n'expose aucune fonction d'accès globale.
	 */
	private const MAIN_CLASS = 'Boxtal\\BoxtalConnectWoocommerce\\Plugin';

	/**
	 * L'extension est-elle chargée ET démarrée ?
	 *
	 * Comme Advanced Shipment Tracking, sa classe principale se déclare à
	 * l'inclusion du fichier : `class_exists()` suffit, sans dépendre de
	 * l'option `active_plugins`.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( self::MAIN_CLASS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Boxtal n'expose ni constante ni propriété de version : la seule source est
	 * l'en-tête du fichier principal, lu via `get_plugin_data()`. Coûteux (E/S
	 * disque) : réservé à un écran de diagnostic, jamais à un chemin d'exécution
	 * courant.
	 */
	public static function version(): string {
		if ( ! self::is_installed() ) {
			return '';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$path = WP_PLUGIN_DIR . '/' . self::basename();

		if ( ! is_readable( $path ) ) {
			return '';
		}

		$data = get_plugin_data( $path, false, false );

		return isset( $data['Version'] ) ? (string) $data['Version'] : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_installed(): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( self::basename(), get_plugins() );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function install_url(): string {
		return self::is_installed()
			? self_admin_url( 'plugins.php' )
			: self_admin_url( 'plugin-install.php?tab=search&type=term&s=' . rawurlencode( self::slug() ) );
	}
}
