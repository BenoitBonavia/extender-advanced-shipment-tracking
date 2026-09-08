<?php
/**
 * Contrat commun à tous les modules du plugin.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Un module encapsule une règle d'extension autonome (l'équivalent structuré
 * d'un snippet).
 */
interface ModuleInterface {

	/**
	 * Identifiant machine unique, en snake_case.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Libellé lisible, affiché dans les réglages.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Extensions tierces dont le module a besoin, en plus des dépendances dures
	 * du plugin (WooCommerce, Advanced Shipment Tracking).
	 *
	 * @return class-string<\EAST\Integration\HostPlugin>[] Classes implémentant HostPlugin.
	 */
	public function get_dependencies(): array;

	/**
	 * Toutes les dépendances déclarées sont-elles actives ?
	 *
	 * Distinct de `is_enabled()` : indisponible faute d'extension tierce n'est
	 * pas la même chose que désactivé par choix du marchand.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Le module doit-il être chargé ?
	 *
	 * Lit uniquement le réglage du marchand. Un module indisponible
	 * (`! is_available()`) n'est jamais instancié, quelle que soit cette valeur —
	 * voir `Plugin::register_modules()`.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Accroche les hooks du module. Appelé une seule fois, si is_available() ET is_enabled().
	 */
	public function register(): void;
}
