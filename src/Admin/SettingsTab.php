<?php
/**
 * Onglet de réglages WooCommerce.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Admin;

use EAST\BoxtalTracking\CarrierResolver;
use EAST\BoxtalTracking\Config as BoxtalConfig;
use EAST\BoxtalTracking\SnippetGuard as BoxtalSnippetGuard;
use EAST\Integration\AdvancedShipmentTracking;
use EAST\Integration\Boxtal;
use EAST\Modules\ModuleInterface;
use EAST\Plugin;
use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce → Réglages → Suivi d'expédition.
 *
 * Les identifiants de champs servent directement de noms d'options : ils
 * doivent donc rester préfixés par `east_` (cf. Settings::PREFIX).
 */
final class SettingsTab extends \WC_Settings_Page {

	/**
	 * Vrai pendant le traitement d'un enregistrement.
	 *
	 * Lu par `get_settings_for_modules_section()` : les champs des modules
	 * indisponibles doivent être RENDUS (avec `disabled`, pour l'affichage) mais
	 * OMIS de la liste transmise à `WC_Admin_Settings::save_fields()` — un champ
	 * désactivé n'est de toute façon jamais envoyé par le navigateur, et
	 * l'inclure ferait enregistrer une valeur vide, effaçant le choix antérieur
	 * du marchand pour ce module.
	 *
	 * @var bool
	 */
	private $saving = false;

	/**
	 * Déclare l'onglet auprès de WooCommerce.
	 */
	public function __construct() {
		$this->id    = Admin::SETTINGS_TAB;
		$this->label = __( 'Suivi d’expédition', 'extender-advanced-shipment-tracking' );

		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	public function save() {
		$this->saving = true;

		parent::save();

		$this->saving = false;
	}

	/**
	 * Sous-sections de l'onglet.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections(): array {
		return array(
			''        => __( 'Général', 'extender-advanced-shipment-tracking' ),
			'boxtal'  => __( 'Boxtal → AST', 'extender-advanced-shipment-tracking' ),
			'modules' => __( 'Modules', 'extender-advanced-shipment-tracking' ),
		);
	}

	/**
	 * Champs de la section par défaut.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section(): array {
		return array(
			array(
				'title' => __( 'Réglages généraux', 'extender-advanced-shipment-tracking' ),
				'type'  => 'title',
				'desc'  => __( 'Comportement global du plugin.', 'extender-advanced-shipment-tracking' ),
				'id'    => Settings::PREFIX . 'general_options',
			),
			array(
				'title'    => __( 'Journalisation', 'extender-advanced-shipment-tracking' ),
				'desc'     => __( 'Consigner les opérations du plugin dans les journaux WooCommerce.', 'extender-advanced-shipment-tracking' ),
				'desc_tip' => __( 'Visible dans WooCommerce → État → Journaux, source « extender-ast ».', 'extender-advanced-shipment-tracking' ),
				'id'       => Settings::PREFIX . 'enable_logging',
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array(
				'title'    => __( 'Effacer les données à la désinstallation', 'extender-advanced-shipment-tracking' ),
				'desc'     => __( 'Supprimer les réglages du plugin si l’extension est supprimée.', 'extender-advanced-shipment-tracking' ),
				'desc_tip' => __( 'Décoché, la suppression de l’extension laisse vos réglages intacts : vous pourrez la réinstaller sans rien perdre.', 'extender-advanced-shipment-tracking' ),
				'id'       => Settings::PREFIX . 'delete_data_on_uninstall',
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => Settings::PREFIX . 'general_options',
			),
			array(
				'title' => __( 'Diagnostic', 'extender-advanced-shipment-tracking' ),
				'type'  => 'title',
				'desc'  => $this->diagnostics_html(),
				'id'    => Settings::PREFIX . 'diagnostics',
			),
			array(
				'type' => 'sectionend',
				'id'   => Settings::PREFIX . 'diagnostics',
			),
		);
	}

	/**
	 * Tableau de diagnostic : versions des extensions dont ce plugin dépend, et
	 * volumétrie des modules déclarés.
	 *
	 * Sur un plugin greffé à du code tiers, c'est le seul signal disponible
	 * quand un hôte déplace un hook sans rien casser bruyamment.
	 *
	 * @return string
	 */
	private function diagnostics_html(): string {
		$lines = array();

		$lines[] = sprintf(
			/* translators: %s: numéro de version de WooCommerce. */
			esc_html__( 'WooCommerce : %s', 'extender-advanced-shipment-tracking' ),
			'<strong>' . esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '?' ) . '</strong>'
		);

		$ast_line = sprintf(
			/* translators: 1: oui/non, 2: numéro de version. */
			esc_html__( '%1$s — actif : %2$s', 'extender-advanced-shipment-tracking' ),
			esc_html( AdvancedShipmentTracking::name() ),
			$this->yes_no( AdvancedShipmentTracking::is_active() )
		);

		if ( AdvancedShipmentTracking::is_active() ) {
			$ast_line .= ' · ' . sprintf(
				/* translators: %s: numéro de version installée. */
				esc_html__( 'version %s', 'extender-advanced-shipment-tracking' ),
				'<strong>' . esc_html( AdvancedShipmentTracking::version() ) . '</strong>'
			);
		}

		$lines[] = $ast_line;

		if ( AdvancedShipmentTracking::is_untested() ) {
			$lines[] = '<strong style="color:#b32d2e">' . sprintf(
				/* translators: 1: version installée, 2: dernière version relue. */
				esc_html__( 'Version majeure %1$s non encore relue par ce plugin (relu jusqu’à %2$s). Surveillez le comportement des modules qui en dépendent.', 'extender-advanced-shipment-tracking' ),
				esc_html( AdvancedShipmentTracking::version() ),
				esc_html( AdvancedShipmentTracking::TESTED_VERSION )
			) . '</strong>';
		}

		$boxtal_line = sprintf(
			/* translators: 1: oui/non, 2: numéro de version. */
			esc_html__( '%1$s (optionnel) — actif : %2$s', 'extender-advanced-shipment-tracking' ),
			esc_html( Boxtal::name() ),
			$this->yes_no( Boxtal::is_active() )
		);

		if ( Boxtal::is_active() && '' !== Boxtal::version() ) {
			$boxtal_line .= ' · ' . sprintf(
				/* translators: %s: numéro de version installée. */
				esc_html__( 'version %s', 'extender-advanced-shipment-tracking' ),
				'<strong>' . esc_html( Boxtal::version() ) . '</strong>'
			);
		}

		$lines[] = $boxtal_line;

		$modules   = $this->get_declared_modules();
		$available = array_filter( $modules, static fn( ModuleInterface $module ) => $module->is_available() );
		$enabled   = array_filter( $available, static fn( ModuleInterface $module ) => $module->is_enabled() );

		$lines[] = sprintf(
			/* translators: 1: nombre de modules déclarés, 2: nombre disponibles, 3: nombre actifs. */
			esc_html__( 'Modules — déclarés : %1$s · disponibles : %2$s · actifs : %3$s', 'extender-advanced-shipment-tracking' ),
			'<strong>' . esc_html( (string) count( $modules ) ) . '</strong>',
			'<strong>' . esc_html( (string) count( $available ) ) . '</strong>',
			'<strong>' . esc_html( (string) count( $enabled ) ) . '</strong>'
		);

		return implode( '<br>', $lines );
	}

	/**
	 * Champs de la section « Boxtal → AST ».
	 *
	 * @return array
	 */
	protected function get_settings_for_boxtal_section(): array {
		$overrides = BoxtalConfig::constant_overrides();

		return array(
			array(
				'title' => __( 'Suivi Boxtal → Advanced Shipment Tracking', 'extender-advanced-shipment-tracking' ),
				'type'  => 'title',
				'desc'  => __( 'Réglages du pont qui importe automatiquement dans AST le numéro et le lien de suivi générés par Boxtal à l’édition d’un bordereau.', 'extender-advanced-shipment-tracking' ),
				'id'    => Settings::PREFIX . 'boxtal_options',
			),
			array(
				'title'    => __( 'Préférer le lien Boxtal', 'extender-advanced-shipment-tracking' ),
				'desc'     => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_PREFER_LINK,
					__( 'Utiliser en priorité l’URL de suivi renvoyée par Boxtal plutôt que celle construite par AST. Recommandé : elle reste valide pour Mondial Relay, dont la page de suivi exige numéro et code postal.', 'extender-advanced-shipment-tracking' )
				),
				'id'       => Settings::PREFIX . BoxtalConfig::KEY_PREFER_LINK,
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => false,
			),
			array(
				'title'    => __( 'Statut « expédié » automatique', 'extender-advanced-shipment-tracking' ),
				'desc'     => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_STATUS_SHIPPED,
					__( 'Faire passer la commande au statut « expédié » d’AST à l’ajout du suivi. Décoché par défaut : Boxtal change généralement le statut de la commande juste après.', 'extender-advanced-shipment-tracking' )
				),
				'id'       => Settings::PREFIX . BoxtalConfig::KEY_STATUS_SHIPPED,
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => false,
			),
			array(
				'title'    => __( 'Déduire du format du numéro', 'extender-advanced-shipment-tracking' ),
				'desc'     => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_GUESS_FROM_NUMBER,
					__( 'En dernier recours, déduire Mondial Relay d’un numéro purement numérique. Faible confiance : Colissimo et Chronopost partagent le même format et ne sont jamais déduits de cette façon.', 'extender-advanced-shipment-tracking' )
				),
				'id'       => Settings::PREFIX . BoxtalConfig::KEY_GUESS_FROM_NUMBER,
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => false,
			),
			array(
				'title'    => __( 'Journal détaillé', 'extender-advanced-shipment-tracking' ),
				'desc'     => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_DEBUG,
					__( 'Consigner la réponse brute de l’API Boxtal. À activer pour diagnostiquer, avec la journalisation générale de l’onglet Général.', 'extender-advanced-shipment-tracking' )
				),
				'id'       => Settings::PREFIX . BoxtalConfig::KEY_DEBUG,
				'type'     => 'checkbox',
				'default'  => 'no',
				'desc_tip' => false,
			),
			array(
				'title'             => __( 'Tentatives maximum', 'extender-advanced-shipment-tracking' ),
				'desc'              => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_MAX_RETRY,
					__( 'Nombre de relances si l’API Boxtal n’a pas encore le colis au moment de l’édition du bordereau.', 'extender-advanced-shipment-tracking' )
				),
				'id'                => Settings::PREFIX . BoxtalConfig::KEY_MAX_RETRY,
				'type'              => 'number',
				'default'           => BoxtalConfig::DEFAULT_MAX_RETRY,
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'desc_tip'          => false,
			),
			array(
				'title'             => __( 'Délai entre tentatives (secondes)', 'extender-advanced-shipment-tracking' ),
				'desc'              => $this->override_note(
					$overrides,
					BoxtalConfig::KEY_RETRY_DELAY,
					__( 'Délai entre deux relances.', 'extender-advanced-shipment-tracking' )
				),
				'id'                => Settings::PREFIX . BoxtalConfig::KEY_RETRY_DELAY,
				'type'              => 'number',
				'default'           => BoxtalConfig::DEFAULT_RETRY_DELAY,
				'custom_attributes' => array(
					'min'  => '60',
					'step' => '60',
				),
				'desc_tip'          => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => Settings::PREFIX . 'boxtal_options',
			),
			array(
				'title' => __( 'Diagnostic', 'extender-advanced-shipment-tracking' ),
				'type'  => 'title',
				'desc'  => $this->boxtal_diagnostics_html(),
				'id'    => Settings::PREFIX . 'boxtal_diagnostics',
			),
			array(
				'type' => 'sectionend',
				'id'   => Settings::PREFIX . 'boxtal_diagnostics',
			),
		);
	}

	/**
	 * Complète la description d'un champ imposé par une constante héritée du
	 * snippet WPCode.
	 *
	 * Le champ reste modifiable : le désactiver ferait enregistrer une valeur
	 * vide par WooCommerce, et la configuration serait perdue le jour où la
	 * constante disparaîtrait.
	 *
	 * @param array  $overrides   Réglages imposés par une constante.
	 * @param string $key         Clé du réglage.
	 * @param string $description Description de base.
	 *
	 * @return string
	 */
	private function override_note( array $overrides, string $key, string $description ): string {
		if ( ! isset( $overrides[ $key ] ) ) {
			return $description;
		}

		return $description . '<br><strong>' . esc_html(
			sprintf(
				/* translators: %s: nom de la constante PHP. */
				__( 'Actuellement imposé par la constante %s : ce réglage est ignoré tant qu’elle est définie.', 'extender-advanced-shipment-tracking' ),
				$overrides[ $key ]
			)
		) . '</strong>';
	}

	/**
	 * Tableau de diagnostic du pont Boxtal → AST.
	 *
	 * @return string
	 */
	private function boxtal_diagnostics_html(): string {
		$lines = array();

		if ( BoxtalSnippetGuard::snippet_is_active() ) {
			$lines[] = '<strong style="color:#b32d2e">'
				. esc_html__( 'Snippet détecté : le module est en veille. Désactivez le snippet pour que le plugin prenne le relais.', 'extender-advanced-shipment-tracking' )
				. '</strong> ' . $this->function_list( BoxtalSnippetGuard::detected_functions() );
		} else {
			$lines[] = '<span style="color:#00a32a">'
				. esc_html__( 'Aucun snippet concurrent détecté.', 'extender-advanced-shipment-tracking' )
				. '</span>';
		}

		$networks = Boxtal::is_active() ? Boxtal::get_parcelpoint_networks() : array();

		$lines[] = sprintf(
			/* translators: %s: nombre de réseaux de points relais reconnus par Boxtal. */
			esc_html__( 'Réseaux de points relais résolus par Boxtal (BW_PP_NETWORKS) : %s', 'extender-advanced-shipment-tracking' ),
			'<strong>' . esc_html( (string) count( $networks ) ) . '</strong>'
		);

		$carriers = array_map(
			static function ( array $carrier ) {
				return $carrier['ast'];
			},
			CarrierResolver::carriers()
		);

		$lines[] = sprintf(
			/* translators: %s: liste des transporteurs reconnus. */
			esc_html__( 'Transporteurs reconnus : %s', 'extender-advanced-shipment-tracking' ),
			'<code>' . esc_html( implode( ', ', $carriers ) ) . '</code>'
		);

		return implode( '<br>', $lines );
	}

	/**
	 * Rend la liste des fonctions qui mettent le module en veille.
	 *
	 * @param string[] $functions Fonctions détectées.
	 *
	 * @return string
	 */
	private function function_list( array $functions ): string {
		if ( empty( $functions ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: liste des noms de fonctions détectées. */
			esc_html(
				_n(
					'Fonction détectée : %s.',
					'Fonctions détectées : %s.',
					count( $functions ),
					'extender-advanced-shipment-tracking'
				)
			),
			'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $functions ) ) . '</code>'
		);
	}

	/**
	 * Champs de la section « Modules » : une case à cocher par module déclaré.
	 *
	 * @return array
	 */
	protected function get_settings_for_modules_section(): array {
		$settings = array(
			array(
				'title' => __( 'Modules', 'extender-advanced-shipment-tracking' ),
				'type'  => 'title',
				'desc'  => __( 'Activez individuellement les règles apportées par ce plugin.', 'extender-advanced-shipment-tracking' ),
				'id'    => Settings::PREFIX . 'module_options',
			),
		);

		foreach ( $this->get_declared_modules() as $module ) {
			$field_id = Settings::PREFIX . 'module_' . $module->get_id() . '_enabled';

			if ( ! $module->is_available() ) {
				// Cf. la doc de $this->saving : un champ indisponible n'est
				// rendu qu'à l'affichage, jamais transmis à save_fields().
				if ( $this->saving ) {
					continue;
				}

				$settings[] = array(
					'title'             => $module->get_title(),
					'desc'              => $this->unavailable_note( $module ),
					'id'                => $field_id,
					'type'              => 'checkbox',
					'default'           => 'no',
					'custom_attributes' => array( 'disabled' => 'disabled' ),
					'desc_tip'          => false,
				);

				continue;
			}

			$settings[] = array(
				'title'   => $module->get_title(),
				'desc'    => __( 'Activer', 'extender-advanced-shipment-tracking' ),
				'id'      => $field_id,
				'type'    => 'checkbox',
				'default' => 'yes',
			);
		}

		if ( 1 === count( $settings ) ) {
			$settings[] = array(
				'title' => '',
				'type'  => 'info',
				'text'  => __( 'Aucun module déclaré pour le moment. Ajoutez vos classes dans src/Modules/ puis référencez-les via le filtre east_module_classes.', 'extender-advanced-shipment-tracking' ),
				'id'    => Settings::PREFIX . 'module_empty_notice',
			);
		}

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => Settings::PREFIX . 'module_options',
		);

		return $settings;
	}

	/**
	 * Décrit ce qui manque à un module indisponible, avec un lien vers l'écran
	 * adéquat pour chaque dépendance.
	 *
	 * @param ModuleInterface $module Module indisponible.
	 *
	 * @return string
	 */
	private function unavailable_note( ModuleInterface $module ): string {
		$links = array();

		foreach ( $module->missing_dependencies() as $class_name ) {
			$links[] = sprintf(
				/* translators: 1: nom de l'extension manquante, 2: lien vers l'écran adéquat. */
				esc_html__( '%1$s — %2$s', 'extender-advanced-shipment-tracking' ),
				'<strong>' . esc_html( $class_name::name() ) . '</strong>',
				'<a href="' . esc_url( $class_name::install_url() ) . '">'
					. ( $class_name::is_installed()
						? esc_html__( 'l’activer', 'extender-advanced-shipment-tracking' )
						: esc_html__( 'l’installer', 'extender-advanced-shipment-tracking' ) )
					. '</a>'
			);
		}

		return '<strong style="color:#b32d2e">'
			. esc_html__( 'Nécessite :', 'extender-advanced-shipment-tracking' )
			. '</strong> ' . implode( ' · ', $links );
	}

	/**
	 * Instancie tous les modules déclarés, disponibles ou non, pour l'affichage.
	 *
	 * @return ModuleInterface[]
	 */
	private function get_declared_modules(): array {
		$modules = array();

		foreach ( Plugin::get_module_classes() as $class_name ) {
			if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
				continue;
			}

			$module = new $class_name();

			if ( $module instanceof ModuleInterface ) {
				$modules[] = $module;
			}
		}

		return $modules;
	}

	/**
	 * Rend un booléen sous forme colorée.
	 *
	 * @param bool $value Valeur.
	 *
	 * @return string
	 */
	private function yes_no( bool $value ): string {
		return $value
			? '<span style="color:#00a32a">' . esc_html__( 'oui', 'extender-advanced-shipment-tracking' ) . '</span>'
			: '<span style="color:#b32d2e">' . esc_html__( 'non', 'extender-advanced-shipment-tracking' ) . '</span>';
	}
}
