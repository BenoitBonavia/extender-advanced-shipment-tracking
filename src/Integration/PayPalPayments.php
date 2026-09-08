<?php
/**
 * Point d'isolement de « WooCommerce PayPal Payments ».
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Integration;

use EAST\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Tout ce que ce plugin sait de l'extension de paiement PayPal.
 *
 * Aucune autre classe ne doit écrire en dur `WooCommerce\PayPalCommerce\PPCP`,
 * un identifiant de service de son conteneur, le préfixe `ppcp` d'une
 * passerelle ou le nom d'une de ses métas : le jour où PayPal Payments change
 * sa structure interne, seul ce fichier bouge.
 *
 * Dépendance DOUCE, volontairement absente de l'en-tête `Requires Plugins` du
 * fichier principal : ce plugin s'installe et s'active sans elle. Seul le
 * module `Modules\PayPalTracking` reste indisponible tant qu'elle manque —
 * voir `AbstractModule::is_available()`.
 *
 * Relevé sur la version 4.1.2 (lecture de code fournie avec le snippet
 * remplacé par `Modules\PayPalTracking`, dépôt `modules/ppcp-order-tracking/`).
 */
final class PayPalPayments implements HostPlugin {

	/**
	 * {@inheritDoc}
	 */
	public static function slug(): string {
		return 'woocommerce-paypal-payments';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function basename(): string {
		return 'woocommerce-paypal-payments/woocommerce-paypal-payments.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function name(): string {
		return 'WooCommerce PayPal Payments';
	}

	/**
	 * Nom pleinement qualifié de la classe d'amorçage de l'extension.
	 */
	private const MAIN_CLASS = 'WooCommerce\\PayPalCommerce\\PPCP';

	/**
	 * Préfixe commun à toutes les passerelles de paiement de l'extension
	 * (`ppcp-gateway`, `ppcp-credit-card-gateway`…).
	 */
	private const GATEWAY_ID_PREFIX = 'ppcp';

	/**
	 * Meta portant l'identifiant de commande PayPal.
	 */
	private const META_PAYPAL_ORDER_ID = '_ppcp_paypal_order_id';

	/**
	 * Identifiants des deux services de suivi exposés par le conteneur PPCP.
	 */
	private const SERVICE_SHIPMENT_FACTORY = 'order-tracking.shipment.factory';
	private const SERVICE_TRACKING_ENDPOINT = 'order-tracking.endpoint.controller';

	/**
	 * L'extension est-elle chargée ET démarrée ?
	 *
	 * Comme Advanced Shipment Tracking et Boxtal, sa classe principale se
	 * déclare à l'inclusion du fichier : `class_exists()` suffit.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( self::MAIN_CLASS );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Aucune constante ni propriété de version accessible : lue depuis l'en-tête
	 * du fichier principal, comme pour Boxtal. Coûteux (E/S disque) : réservé au
	 * diagnostic, jamais à un chemin d'exécution courant.
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
	 * Conteneur de services PPCP, sous toutes les gardes possibles.
	 *
	 * PayPal documente `PPCP::container()` comme un usage interne, sans
	 * garantie de compatibilité entre versions : une mise à jour cassante doit
	 * désactiver la synchronisation et journaliser, jamais faire fataler le
	 * site — d'où le `try/catch \Throwable` et la vérification explicite de
	 * l'interface PSR-11 (`has()` / `get()`) plutôt qu'un `instanceof`.
	 *
	 * @return object|null
	 */
	private static function container() {
		if ( ! self::is_active() ) {
			return null;
		}

		try {
			$container = call_user_func( array( self::MAIN_CLASS, 'container' ) );
		} catch ( \Throwable $exception ) {
			Logger::error( 'Conteneur PPCP indisponible : ' . $exception->getMessage() );

			return null;
		}

		if ( ! is_object( $container ) || ! method_exists( $container, 'has' ) || ! method_exists( $container, 'get' ) ) {
			Logger::error( 'Conteneur PPCP inattendu : interface PSR-11 absente.' );

			return null;
		}

		return $container;
	}

	/**
	 * Services de suivi de colis exposés par PayPal Payments.
	 *
	 * @return array{0: object, 1: object}|null [fabrique de suivi, contrôleur d'envoi],
	 *                                          ou null si le module de suivi PPCP est
	 *                                          absent, désactivé, ou incompatible.
	 */
	public static function tracking_services(): ?array {
		$container = self::container();

		if ( null === $container ) {
			return null;
		}

		try {
			if ( ! $container->has( self::SERVICE_SHIPMENT_FACTORY ) || ! $container->has( self::SERVICE_TRACKING_ENDPOINT ) ) {
				Logger::error( 'Services de suivi PPCP absents du conteneur (module désactivé ?).' );

				return null;
			}

			$factory  = $container->get( self::SERVICE_SHIPMENT_FACTORY );
			$endpoint = $container->get( self::SERVICE_TRACKING_ENDPOINT );
		} catch ( \Throwable $exception ) {
			Logger::error( 'Récupération des services PPCP impossible : ' . $exception->getMessage() );

			return null;
		}

		if ( ! is_object( $factory ) || ! method_exists( $factory, 'create_shipment' ) ) {
			return null;
		}

		if ( ! is_object( $endpoint ) || ! method_exists( $endpoint, 'add_tracking_information' ) ) {
			return null;
		}

		return array( $factory, $endpoint );
	}

	/**
	 * Le paiement de cette commande passe-t-il par une passerelle PayPal Payments ?
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return bool
	 */
	public static function uses_gateway( \WC_Order $order ): bool {
		return 0 === strpos( (string) $order->get_payment_method(), self::GATEWAY_ID_PREFIX );
	}

	/**
	 * Identifiant de commande PayPal.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return string Chaîne vide si absent.
	 */
	public static function paypal_order_id( \WC_Order $order ): string {
		return trim( (string) $order->get_meta( self::META_PAYPAL_ORDER_ID ) );
	}

	/**
	 * Identifiant de capture du paiement.
	 *
	 * Pour une commande PPCP, `get_transaction_id()` EST l'identifiant de
	 * capture (`TransactionIdHandlingTrait::update_transaction_id()`) : aucun
	 * appel API n'est nécessaire pour l'obtenir.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return string Chaîne vide si le paiement n'est pas encore capturé.
	 */
	public static function capture_id( \WC_Order $order ): string {
		return trim( (string) $order->get_transaction_id() );
	}
}
