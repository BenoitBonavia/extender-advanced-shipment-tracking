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

	/**
	 * Action déclenchée quand Boxtal marque une commande comme expédiée.
	 *
	 * Émise par `Util\Order_Util::set_order_as_shipped()`, avec l'identifiant de
	 * commande en unique argument. C'est le seul point d'entrée temps réel du
	 * pont de suivi — confirmé par lecture de `class-order-util.php` (2.0.2).
	 */
	public const ACTION_ORDER_SHIPPED = 'boxtal_connect_order_shipped';

	/**
	 * Nom pleinement qualifié de l'utilitaire d'appel à l'API Boxtal.
	 */
	private const SHIPPING_API_UTIL_CLASS = 'Boxtal\\BoxtalConnectWoocommerce\\Util\\Shipping_Api_Util';

	/**
	 * Nom pleinement qualifié de l'utilitaire de lecture des commandes Boxtal.
	 */
	private const ORDER_UTIL_CLASS = 'Boxtal\\BoxtalConnectWoocommerce\\Util\\Order_Util';

	/**
	 * Suivi d'une commande tel que renvoyé par l'API Boxtal.
	 *
	 * Appelle `GET https://api.boxtal.com/v2/shop-order/{$order_id}` (relevé sur
	 * `Util\Shipping_Api_Util::get_order()`, 2.0.2). La forme de la réponse est
	 * un objet portant `shipmentsTracking[]`, chaque expédition portant
	 * `parcelsTracking[]`, chaque colis portant `reference` (numéro de suivi) et
	 * `trackingUrl` — à lire tel quel, aucune normalisation n'est faite ici.
	 *
	 * @param int $order_id Commande.
	 *
	 * @return mixed Objet décodé depuis la réponse JSON, ou null si indisponible.
	 */
	public static function get_order_tracking( int $order_id ) {
		if ( ! self::is_active() || ! class_exists( self::SHIPPING_API_UTIL_CLASS ) ) {
			return null;
		}

		return call_user_func( array( self::SHIPPING_API_UTIL_CLASS, 'get_order' ), $order_id );
	}

	/**
	 * Point relais choisi pour une commande, s'il y en a un.
	 *
	 * Relevé sur `Util\Order_Util::get_parcelpoint()`, 2.0.2 : renvoie un objet
	 * portant (entre autres) la propriété `network`, ou `null` en livraison
	 * standard.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return mixed
	 */
	public static function get_parcelpoint( \WC_Order $order ) {
		if ( ! self::is_active() || ! class_exists( self::ORDER_UTIL_CLASS ) ) {
			return null;
		}

		return call_user_func( array( self::ORDER_UTIL_CLASS, 'get_parcelpoint' ), $order );
	}

	/**
	 * Mise en correspondance réseau de points relais → transporteurs.
	 *
	 * Boxtal stocke cette table dans l'option `BW_PP_NETWORKS`, tantôt en objet
	 * tantôt en tableau selon la version ayant écrit l'option : normalisée ici
	 * en tableau `réseau => transporteurs[]`, pour que le reste du plugin n'ait
	 * jamais à se soucier de cette variation de forme.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_parcelpoint_networks(): array {
		$networks = get_option( 'BW_PP_NETWORKS' );

		if ( is_object( $networks ) ) {
			$networks = get_object_vars( $networks );
		}

		if ( ! is_array( $networks ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $networks as $network => $carriers ) {
			$normalized[ (string) $network ] = array_map( 'strval', (array) $carriers );
		}

		return $normalized;
	}
}
