<?php
/**
 * Constantes figées, héritées du snippet WPCode remplacé par ce module.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Rien ici ne doit changer : ces valeurs sont lues sur des données déjà
 * écrites par le snippet en production, ou servent à le détecter.
 */
final class Legacy {

	/**
	 * Journal des numéros de suivi déjà transmis à PayPal, tenu par ce plugin.
	 *
	 * Conserve le préfixe `_mh_ppt_` du snippet à dessein : c'est déjà la clé
	 * écrite sur les commandes que le snippet a traitées en production.
	 */
	public const META_SENT = '_mh_ppt_sent';

	/**
	 * Meta écrite par PayPal Payments lui-même après un envoi réussi
	 * (numéro de suivi => identifiants des lignes de commande couvertes).
	 *
	 * Jamais écrite par ce plugin : uniquement lue, pour ne jamais présenter à
	 * PayPal un tracker qu'il connaît déjà.
	 */
	public const META_PPCP_SENT = '_ppcp_paypal_tracking_info_meta_name';

	/**
	 * Fonctions globales déclarées par le snippet WPCode, utilisées comme
	 * sentinelles par `SnippetGuard::snippet_is_active()`.
	 *
	 * @var string[]
	 */
	public const SNIPPET_SENTINELS = array(
		'mh_ppt_push_tracking',
		'mh_ppt_order_state',
		'mh_ppt_container',
	);
}
