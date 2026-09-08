<?php
/**
 * Signatures de « Advanced Shipment Tracking for WooCommerce », pour l'analyse
 * statique uniquement.
 *
 * Ce fichier n'est JAMAIS chargé à l'exécution : il est seulement listé dans
 * `scanFiles` de phpstan.neon.dist, et exclu des archives par .gitattributes.
 *
 * Raison d'être : `EAST\Integration\AdvancedShipmentTracking` appelle
 * `wc_advanced_shipment_tracking()` et lit la propriété publique `$version` de
 * l'instance renvoyée. Sans cette déclaration, PHPStan signale une fonction
 * introuvable, et conclut même que le `function_exists()` qui la garde est
 * toujours faux.
 *
 * Tous les appels restent gardés par `function_exists()` à l'exécution :
 * l'extension hôte n'inclut son fichier que si elle est elle-même active.
 *
 * Signatures relevées sur la version 4.0.2 (dépôt SVN WordPress.org, trunk).
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

/**
 * Classe principale de l'extension hôte.
 */
class Zorem_Woocommerce_Advanced_Shipment_Tracking {

	/**
	 * Numéro de version, exposé en propriété publique (pas de constante).
	 *
	 * @var string
	 */
	public $version = '0.0.0';
}

/**
 * Accesseur global au singleton de l'extension hôte.
 *
 * @return Zorem_Woocommerce_Advanced_Shipment_Tracking
 */
function wc_advanced_shipment_tracking() {
	return new Zorem_Woocommerce_Advanced_Shipment_Tracking();
}
