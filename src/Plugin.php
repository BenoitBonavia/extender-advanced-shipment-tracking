<?php
/**
 * Bootstrap du plugin.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST;

use EAST\Admin\Admin;
use EAST\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Conteneur principal : vérifie les prérequis puis enregistre les modules.
 */
final class Plugin {

	/**
	 * Instance unique.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Modules actifs, indexés par identifiant.
	 *
	 * @var ModuleInterface[]
	 */
	private $modules = array();

	/**
	 * Retourne (et amorce) l'instance unique.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Constructeur privé : passer par instance().
	 */
	private function __construct() {}

	/**
	 * Amorce le plugin si l'environnement le permet.
	 */
	private function boot(): void {
		/*
		 * Pas de load_plugin_textdomain() : depuis WordPress 6.8, le chargement
		 * « just-in-time » des traductions couvre toutes les extensions dès lors
		 * que les en-têtes Text Domain et Domain Path sont présents et que le
		 * text domain correspond au slug du dossier du plugin.
		 */

		/*
		 * Les mises à jour sont branchées AVANT le contrôle des prérequis : si
		 * WooCommerce ou Advanced Shipment Tracking venaient à manquer, le site
		 * doit rester capable de recevoir un correctif de ce plugin.
		 */
		Updater::register();

		if ( ! Requirements::are_met() ) {
			add_action( 'admin_notices', array( Requirements::class, 'render_notice' ) );

			return;
		}

		if ( is_admin() ) {
			( new Admin() )->register();
		}

		$this->register_modules();

		/*
		 * WordPress n'exécute pas le hook d'activation lors d'une mise à jour :
		 * les migrations doivent donc aussi être déclenchées depuis une requête
		 * ordinaire. Le coût est d'une lecture d'option.
		 *
		 * APRÈS l'enregistrement des modules, impérativement : ce sont eux qui
		 * s'abonnent à `east_upgrade` pour amorcer leurs traitements de fond.
		 */
		Installer::maybe_upgrade();

		/**
		 * Le plugin est prêt : tous les modules disponibles et activés sont enregistrés.
		 *
		 * @param Plugin $plugin Instance du plugin.
		 */
		do_action( 'east_loaded', $this );
	}

	/**
	 * Liste des classes de modules déclarées.
	 *
	 * Chaque règle d'extension autonome devient un module : créez une classe
	 * dans src/Modules/, étendez AbstractModule, puis ajoutez-la ci-dessous. Un
	 * module qui dépend de Boxtal déclare `protected $dependencies` avec
	 * `\EAST\Integration\Boxtal::class` — voir `Modules\AbstractModule`.
	 *
	 * @return string[] Noms de classes implémentant ModuleInterface.
	 */
	public static function get_module_classes(): array {
		/**
		 * Liste des classes de modules à charger.
		 *
		 * @param string[] $classes Noms de classes implémentant ModuleInterface.
		 */
		return (array) apply_filters( 'east_module_classes', array() );
	}

	/**
	 * Instancie et enregistre les modules disponibles et actifs.
	 */
	private function register_modules(): void {
		foreach ( self::get_module_classes() as $class_name ) {
			if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
				continue;
			}

			$module = new $class_name();

			if ( ! $module instanceof ModuleInterface ) {
				continue;
			}

			// Une dépendance tierce manquante prime sur le réglage du marchand :
			// un module indisponible n'est jamais instancié, quelle que soit la
			// case cochée dans les réglages.
			if ( ! $module->is_available() ) {
				continue;
			}

			if ( ! $module->is_enabled() ) {
				continue;
			}

			$module->register();

			$this->modules[ $module->get_id() ] = $module;
		}
	}

	/**
	 * Retourne les modules actifs.
	 *
	 * @return ModuleInterface[]
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * Retourne un module actif par son identifiant.
	 *
	 * @param string $id Identifiant du module.
	 *
	 * @return ModuleInterface|null
	 */
	public function get_module( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Empêche le clonage.
	 */
	private function __clone() {}

	/**
	 * Empêche la désérialisation.
	 *
	 * @throws \RuntimeException Systématiquement : le conteneur est un singleton.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Unserializing Plugin is not allowed.' );
	}
}
