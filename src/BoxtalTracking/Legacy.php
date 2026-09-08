<?php
/**
 * Constantes figées, héritées du snippet WPCode remplacé par ce module.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Rien ici ne doit changer : ces valeurs sont lues sur des données déjà écrites
 * par le snippet en production, ou servent à le détecter.
 */
final class Legacy {

	/**
	 * Méta de commande portant, par numéro de suivi normalisé, l'URL renvoyée
	 * par Boxtal — lue par `TrackingLink` en secours du lien construit par AST.
	 *
	 * Conserve le préfixe `_mh_bxt_` du snippet à dessein : la renommer ferait
	 * perdre ce lien de secours pour toutes les commandes déjà traitées.
	 */
	public const META_TRACKING_URLS = '_mh_bxt_tracking_urls';

	/**
	 * Fonctions globales déclarées par le snippet WPCode, utilisées comme
	 * sentinelles par `SnippetGuard::snippet_is_active()`.
	 *
	 * Plusieurs plutôt qu'une seule : le snippet peut avoir été partiellement
	 * modifié, et un faux négatif (double exécution) est bien plus coûteux
	 * qu'un faux positif (module en veille un peu plus longtemps).
	 *
	 * @var string[]
	 */
	public const SNIPPET_SENTINELS = array(
		'mh_bxt_sync_tracking_to_ast',
		'mh_bxt_resolve_carrier',
		'mh_bxt_carriers',
	);
}
