<?php
/**
 * Base commune aux modules.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Modules;

use EAST\Integration\HostPlugin;
use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Implémente le comportement par défaut d'un module : activation pilotée par
 * une option `east_module_{id}_enabled`, surchargeable par filtre, et
 * disponibilité conditionnée aux dépendances tierces déclarées.
 */
abstract class AbstractModule implements ModuleInterface {

	/**
	 * Identifiant machine unique.
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * Libellé lisible.
	 *
	 * @var string
	 */
	protected $title = '';

	/**
	 * Le module est-il actif par défaut (une fois disponible) ?
	 *
	 * @var bool
	 */
	protected $enabled_by_default = true;

	/**
	 * Extensions tierces requises par ce module, en plus de WooCommerce et
	 * Advanced Shipment Tracking. Exemple : `array( \EAST\Integration\Boxtal::class )`.
	 *
	 * @var class-string<HostPlugin>[]
	 */
	protected $dependencies = array();

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_dependencies(): array {
		return $this->dependencies;
	}

	/**
	 * Dépendances déclarées qui manquent actuellement.
	 *
	 * @return class-string<HostPlugin>[]
	 */
	public function missing_dependencies(): array {
		return array_values(
			array_filter(
				$this->get_dependencies(),
				static function ( $class_name ) {
					return is_string( $class_name )
						&& is_a( $class_name, HostPlugin::class, true )
						&& ! $class_name::is_active();
				}
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available(): bool {
		return array() === $this->missing_dependencies();
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled(): bool {
		$enabled = Settings::get_bool(
			'module_' . $this->get_id() . '_enabled',
			$this->enabled_by_default
		);

		/**
		 * Force l'activation ou la désactivation d'un module.
		 *
		 * N'est consulté que si le module est disponible : ce filtre ne peut pas
		 * contourner une dépendance tierce manquante.
		 *
		 * @param bool            $enabled État calculé depuis les réglages.
		 * @param ModuleInterface $module  Instance du module.
		 */
		return (bool) apply_filters( 'east_module_is_enabled', $enabled, $this );
	}

	/**
	 * {@inheritDoc}
	 */
	abstract public function register(): void;
}
