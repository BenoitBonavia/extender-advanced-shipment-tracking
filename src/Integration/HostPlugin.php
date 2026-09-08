<?php
/**
 * Contrat commun aux extensions tierces dont ce plugin dépend.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Point d'isolement d'une extension hôte : tout ce que ce plugin sait d'elle.
 *
 * Aucune autre classe ne doit écrire en dur le slug, le chemin de fichier ou un
 * nom de classe d'une extension tierce : le jour où elle change de structure,
 * seule l'implémentation de ce contrat bouge.
 *
 * Une classe implémentant cette interface peut servir de dépendance déclarée
 * dans `AbstractModule::$dependencies` — voir `ModuleInterface::get_dependencies()`.
 */
interface HostPlugin {

	/**
	 * Slug WordPress.org de l'extension, tel qu'il figure dans l'en-tête
	 * `Requires Plugins` du fichier principal de CE plugin (dépendances dures
	 * uniquement) et tel que WordPress.org l'expose.
	 *
	 * @return string
	 */
	public static function slug(): string;

	/**
	 * Chemin du fichier principal de l'extension, relatif au dossier des
	 * extensions (le format attendu par `get_plugins()` / `is_plugin_active()`).
	 *
	 * @return string
	 */
	public static function basename(): string;

	/**
	 * Nom lisible de l'extension.
	 *
	 * Volontairement non traduit : c'est un nom propre, il doit rester
	 * cherchable tel quel dans l'écran d'ajout d'extensions.
	 *
	 * @return string
	 */
	public static function name(): string;

	/**
	 * L'extension est-elle chargée ET démarrée ?
	 *
	 * Ne doit jamais se contenter de l'option `active_plugins` : une extension
	 * listée comme active mais qui a échoué à s'amorcer (WooCommerce manquant,
	 * fatal silencieux…) ne doit pas être considérée comme disponible.
	 *
	 * @return bool
	 */
	public static function is_active(): bool;

	/**
	 * Version de l'extension, si elle est déterminable sans coût prohibitif.
	 *
	 * @return string Chaîne vide si l'extension n'est pas démarrée ou si sa
	 *                version n'est pas exposée.
	 */
	public static function version(): string;

	/**
	 * L'extension est-elle présente dans le dossier des extensions, active ou non ?
	 *
	 * Sert uniquement à orienter un avertissement d'administration :
	 * « installer » et « activer » n'appellent pas le même écran.
	 *
	 * @return bool
	 */
	public static function is_installed(): bool;

	/**
	 * URL de l'écran pertinent pour obtenir l'extension : la fiche d'installation
	 * si elle est absente, la liste des extensions si elle est déjà installée.
	 *
	 * @return string
	 */
	public static function install_url(): string;
}
