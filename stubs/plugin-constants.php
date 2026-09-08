<?php
/**
 * Constantes du plugin, déclarées pour l'analyse statique uniquement.
 *
 * Ce fichier n'est JAMAIS chargé à l'exécution : il est seulement listé dans
 * `scanFiles` de phpstan.neon.dist, et exclu des archives par .gitattributes.
 *
 * Raison d'être : PHPStan n'enregistre une constante déclarée par `define()`
 * que s'il peut évaluer sa valeur statiquement. `EAST_VERSION` (littéral) et
 * `EAST_FILE` (__FILE__) passent donc, mais pas `EAST_PATH`, `EAST_URL` ni
 * `EAST_BASENAME`, dont la valeur vient d'un appel de fonction WordPress.
 * Les valeurs ci-dessous ne servent qu'à fixer un TYPE ; elles n'ont aucune
 * signification.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

define( 'EAST_VERSION', '0.0.0' );
define( 'EAST_FILE', '' );
define( 'EAST_PATH', '' );
define( 'EAST_URL', '' );
define( 'EAST_BASENAME', '' );
define( 'EAST_MIN_WC_VERSION', '0.0' );
