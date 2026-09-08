<?php
/**
 * Vérification des prérequis d'exécution.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST;

use EAST\Integration\AdvancedShipmentTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Vérifie que WooCommerce et Advanced Shipment Tracking sont présents et assez
 * récents — les deux dépendances DURES de ce plugin.
 *
 * L'en-tête `Requires Plugins` du fichier principal couvre déjà le cas courant :
 * depuis WordPress 6.5, l'activation est refusée tant que les deux extensions ne
 * sont pas là. Ce contrôle reste le filet pour les cas qu'il ne couvre pas :
 * une extension installée dans un dossier renommé (le slug ne résout plus), une
 * version trop ancienne (l'en-tête ignore les numéros de version), et une
 * extension présente mais non démarrée.
 *
 * Boxtal n'entre PAS dans ce contrôle : c'est une dépendance douce, résolue par
 * module — voir `Modules\AbstractModule::is_available()`.
 */
final class Requirements {

	/**
	 * Code de la dernière raison d'échec.
	 *
	 * Les chaînes ne sont traduites qu'à l'affichage : les prérequis sont
	 * évalués sur `plugins_loaded`, soit avant que les traductions ne soient
	 * disponibles (WordPress 6.7 signale les chargements trop précoces).
	 *
	 * @var string
	 */
	private static $failure_code = '';

	/**
	 * Indique si l'environnement permet de charger le plugin.
	 *
	 * L'ordre des contrôles compte : WooCommerce d'abord. Advanced Shipment
	 * Tracking instancie son singleton sans jamais vérifier WooCommerce — sans
	 * cette garde, une boutique sans WooCommerce pourrait malgré tout être
	 * signalée comme ayant l'extension hôte, ce qui masquerait le vrai manque.
	 *
	 * @return bool
	 */
	public static function are_met(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			self::$failure_code = 'missing_wc';

			return false;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, EAST_MIN_WC_VERSION, '<' ) ) {
			self::$failure_code = 'outdated_wc';

			return false;
		}

		if ( ! AdvancedShipmentTracking::is_active() ) {
			self::$failure_code = 'missing_ast';

			return false;
		}

		if ( ! AdvancedShipmentTracking::is_supported() ) {
			self::$failure_code = 'outdated_ast';

			return false;
		}

		self::$failure_code = '';

		return true;
	}

	/**
	 * Affiche l'avertissement d'administration en cas de prérequis manquant.
	 */
	public static function render_notice(): void {
		if ( '' === self::$failure_code || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = self::failure_message();

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> — %s</p></div>',
			esc_html__( 'Extender for Advanced Shipment Tracking', 'extender-advanced-shipment-tracking' ),
			wp_kses( $message, array( 'a' => array( 'href' => array() ) ) )
		);
	}

	/**
	 * Compose le message correspondant à la raison d'échec mémorisée.
	 *
	 * @return string HTML restreint aux liens.
	 */
	private static function failure_message(): string {
		switch ( self::$failure_code ) {
			case 'outdated_wc':
				return esc_html(
					sprintf(
						/* translators: %s: numéro de version minimale de WooCommerce. */
						__( 'WooCommerce %s ou supérieur est requis.', 'extender-advanced-shipment-tracking' ),
						EAST_MIN_WC_VERSION
					)
				);

			case 'missing_wc':
				return esc_html__( 'WooCommerce doit être installé et activé.', 'extender-advanced-shipment-tracking' );

			case 'missing_ast':
				/*
				 * « Installer » et « activer » n'appellent pas le même écran, et
				 * l'extension est le plus souvent déjà installée : ce plugin ne
				 * s'active pas sans elle. Envoyer systématiquement vers l'écran
				 * d'ajout ferait chercher une extension déjà présente.
				 */
				return sprintf(
					/* translators: 1: nom de l'extension requise, 2: URL de l'écran adéquat, 3: libellé du lien. */
					esc_html__( 'L’extension %1$s est requise. %2$s', 'extender-advanced-shipment-tracking' ),
					'<strong>' . esc_html( AdvancedShipmentTracking::name() ) . '</strong>',
					AdvancedShipmentTracking::is_installed()
						? '<a href="' . esc_url( AdvancedShipmentTracking::install_url() ) . '">'
							. esc_html__( 'L’activer', 'extender-advanced-shipment-tracking' ) . '</a>'
						: '<a href="' . esc_url( AdvancedShipmentTracking::install_url() ) . '">'
							. esc_html__( 'L’installer', 'extender-advanced-shipment-tracking' ) . '</a>'
				);

			case 'outdated_ast':
				return esc_html(
					sprintf(
						/* translators: 1: nom de l'extension requise, 2: version minimale, 3: version installée. */
						__( '%1$s %2$s ou supérieur est requis ; version installée : %3$s.', 'extender-advanced-shipment-tracking' ),
						AdvancedShipmentTracking::name(),
						AdvancedShipmentTracking::MIN_VERSION,
						AdvancedShipmentTracking::version()
					)
				);

			default:
				return '';
		}
	}
}
