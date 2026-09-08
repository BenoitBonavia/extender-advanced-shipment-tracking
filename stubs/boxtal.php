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
 * Signatures relevées sur la version 2.0.2 (dépôt SVN WordPress.org, trunk) :
 * slug, fichier principal et nom de classe uniquement — aucun module de ce
 * plugin n'appelle encore son API.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace Boxtal\BoxtalConnectWoocommerce;

/**
 * Classe principale de l'extension Boxtal Connect.
 */
class Plugin {

}
