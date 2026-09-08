<?php
/**
 * Signatures de « WooCommerce PayPal Payments », pour l'analyse statique
 * uniquement.
 *
 * Ce fichier n'est JAMAIS chargé à l'exécution : il est seulement listé dans
 * `scanFiles` de phpstan.neon.dist, et exclu des archives par .gitattributes.
 *
 * Raison d'être : `EAST\Integration\PayPalPayments` appelle
 * `PPCP::container()` puis interroge un conteneur PSR-11 générique (`has()` /
 * `get()`), sans jamais typer les objets renvoyés par les services de suivi —
 * exactement ce que fait le snippet remplacé, `PPCP::container()` étant
 * documenté par PayPal comme un usage interne sans garantie de compatibilité.
 * Ce stub ne déclare donc que le strict nécessaire pour que PHPStan résolve
 * les appels sous garde `class_exists()` / `method_exists()`.
 *
 * Signatures relevées sur la version 4.1.2 (lecture de code fournie avec le
 * snippet, `modules/ppcp-order-tracking/`).
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace WooCommerce\PayPalCommerce;

/**
 * Classe d'amorçage de l'extension.
 */
class PPCP {

	/**
	 * @return object Conteneur de services PSR-11 (has()/get()).
	 */
	public static function container() {
		return new class() {
			/**
			 * @param string $id Identifiant de service.
			 *
			 * @return bool
			 */
			public function has( $id ) {
				return false;
			}

			/**
			 * @param string $id Identifiant de service.
			 *
			 * @return mixed
			 */
			public function get( $id ) {
				return null;
			}
		};
	}
}
