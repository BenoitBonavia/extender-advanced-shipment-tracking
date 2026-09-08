<?php
/**
 * Signatures de « Boxtal Connect », pour l'analyse statique uniquement.
 *
 * Ce fichier n'est JAMAIS chargé à l'exécution : il est seulement listé dans
 * `scanFiles` de phpstan.neon.dist, et exclu des archives par .gitattributes.
 *
 * Raison d'être : `EAST\Integration\Boxtal::is_active()` teste l'existence de
 * cette classe. Sans déclaration, PHPStan la signale introuvable.
 *
 * Signatures relevées sur la version 2.0.2 (dépôt SVN WordPress.org, trunk),
 * dans `Boxtal/BoxtalConnectWoocommerce/util/`.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace Boxtal\BoxtalConnectWoocommerce;

/**
 * Classe principale de l'extension Boxtal Connect.
 */
class Plugin {

}

namespace Boxtal\BoxtalConnectWoocommerce\Util;

/**
 * Appels à l'API Boxtal (class-shipping-api-util.php).
 */
class Shipping_Api_Util {

	/**
	 * @param int $reference Identifiant de commande Boxtal.
	 *
	 * @return mixed Objet décodé depuis la réponse JSON de l'API.
	 */
	public static function get_order( $reference ) {
		return null;
	}
}

/**
 * Lecture des commandes côté Boxtal (class-order-util.php).
 */
class Order_Util {

	/**
	 * @param \WC_Order $order Commande.
	 *
	 * @return mixed Point relais (objet), ou null en livraison standard.
	 */
	public static function get_parcelpoint( $order ) {
		return null;
	}

	/**
	 * @param int $order_id Commande.
	 *
	 * @return void
	 */
	public static function set_order_as_shipped( $order_id ) {}
}
