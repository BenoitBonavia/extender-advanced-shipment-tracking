<?php
/**
 * Point d'isolement de « Advanced Shipment Tracking for WooCommerce ».
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Tout ce que ce plugin sait de l'extension hôte principale.
 *
 * Aucune autre classe ne doit écrire en dur `wc_advanced_shipment_tracking`,
 * `Zorem_Woocommerce_Advanced_Shipment_Tracking` ni le slug de l'extension : le
 * jour où elle renomme une fonction ou une classe, seul ce fichier bouge.
 *
 * Dépendance DURE : déclarée dans l'en-tête `Requires Plugins` du fichier
 * principal de ce plugin et contrôlée par `Requirements::are_met()`.
 *
 * Relevé sur la version 4.0.2 (dépôt SVN WordPress.org, trunk).
 */
final class AdvancedShipmentTracking implements HostPlugin {

	/**
	 * {@inheritDoc}
	 */
	public static function slug(): string {
		return 'woo-advanced-shipment-tracking';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function basename(): string {
		return 'woo-advanced-shipment-tracking/woocommerce-advanced-shipment-tracking.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function name(): string {
		return 'Advanced Shipment Tracking for WooCommerce';
	}

	/**
	 * Version minimale acceptée.
	 *
	 * Plancher provisoire et assumé : sans fonctionnalité, aucun hook consommé
	 * ne justifie encore un seuil précis. 4.0 ouvre la ligne majeure courante.
	 * À réviser dès qu'un module dépendra d'un hook daté.
	 */
	public const MIN_VERSION = '4.0';

	/**
	 * Dernière version de l'extension hôte contre laquelle ce plugin a été relu.
	 *
	 * Ce plugin s'accroche à du code qu'il ne maîtrise pas : un changement de
	 * version majeure de l'hôte peut déplacer un hook ou renommer une option
	 * sans que rien ne casse bruyamment. Cette valeur n'empêche rien — elle sert
	 * à afficher un avertissement dans le diagnostic, seul signal disponible.
	 */
	public const TESTED_VERSION = '4.0.2';

	/**
	 * L'extension hôte est-elle chargée ET démarrée ?
	 *
	 * On teste la FONCTION d'accès au singleton, jamais l'option `active_plugins` :
	 * contrairement à d'autres extensions WooCommerce, celle-ci instancie son
	 * singleton à l'inclusion même du fichier, SANS attendre `plugins_loaded` ni
	 * vérifier que WooCommerce est actif. `function_exists()` suffit donc à
	 * savoir que le fichier a été inclus ; c'est à Requirements de tester
	 * WooCommerce séparément, et en premier.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return function_exists( 'wc_advanced_shipment_tracking' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Lue sur la propriété PUBLIQUE `$version` du singleton : l'extension hôte
	 * n'expose aucune constante de version.
	 */
	public static function version(): string {
		if ( ! self::is_active() ) {
			return '';
		}

		$instance = wc_advanced_shipment_tracking();

		if ( ! is_object( $instance ) || ! isset( $instance->version ) || ! is_scalar( $instance->version ) ) {
			return '';
		}

		return (string) $instance->version;
	}

	/**
	 * L'extension hôte est-elle présente dans une version exploitable ?
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		$version = self::version();

		return '' !== $version && version_compare( $version, self::MIN_VERSION, '>=' );
	}

	/**
	 * L'extension hôte a-t-elle changé de version MAJEURE depuis la relecture ?
	 *
	 * On ne compare que le premier segment : une version mineure ou correctrice
	 * n'est pas censée déplacer un hook, et déclencher l'avertissement à chaque
	 * correctif le rendrait invisible à force d'être affiché.
	 *
	 * @return bool
	 */
	public static function is_untested(): bool {
		$version = self::version();

		if ( '' === $version ) {
			return false;
		}

		$installed = (int) explode( '.', $version )[0];
		$tested    = (int) explode( '.', self::TESTED_VERSION )[0];

		return $installed > $tested;
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
