<?php
/**
 * Détection du snippet WPCode que ce module remplace.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Empêche le module et le snippet de tourner en même temps.
 *
 * Les deux accrochent le même déclencheur (`boxtal_connect_order_shipped`) et le
 * même filtre (`ast_tracking_link`) : les laisser cohabiter provoquerait un
 * suivi ajouté deux fois et une note de commande en double. Comme le snippet ne
 * peut pas s'effacer de lui-même, c'est le module qui se met en veille tant
 * qu'il est détecté.
 *
 * Effet de bord recherché : le retour arrière consiste simplement à réactiver le
 * snippet, le module se remettant en veille au chargement suivant.
 *
 * DÉPENDANCE DE CALENDRIER — WPCode exécute les snippets « Exécuter partout » sur
 * `plugins_loaded` en priorité 5, et ce plugin s'amorce sur ce même hook en
 * priorité 20 : les fonctions du snippet sont donc déjà déclarées au moment de la
 * détection. Abaisser la priorité d'amorçage sous 5 casserait la garde, et le
 * module s'enregistrerait en double sans aucun signal.
 */
final class SnippetGuard {

	/**
	 * Le snippet est-il chargé sur cette requête ?
	 *
	 * @return bool
	 */
	public static function snippet_is_active(): bool {
		foreach ( Legacy::SNIPPET_SENTINELS as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Nom des fonctions du snippet effectivement détectées.
	 *
	 * @return string[]
	 */
	public static function detected_functions(): array {
		return array_values( array_filter( Legacy::SNIPPET_SENTINELS, 'function_exists' ) );
	}

	/**
	 * Affiche l'avertissement de coexistence dans l'administration.
	 */
	public static function render_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// On nomme les fonctions détectées : sans cela, le marchand doit deviner
		// lequel de ses snippets met le module en veille.
		$detected = self::detected_functions();

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> — %s</p><p>%s</p><p>%s</p></div>',
			esc_html__( 'Extender for Advanced Shipment Tracking', 'extender-advanced-shipment-tracking' ),
			esc_html__(
				'Le snippet de pont Boxtal → AST est toujours actif : le module du plugin reste en veille pour éviter les doublons.',
				'extender-advanced-shipment-tracking'
			),
			sprintf(
				/* translators: %s: liste des noms de fonctions détectées. */
				esc_html(
					_n(
						'Fonction détectée : %s.',
						'Fonctions détectées : %s.',
						count( $detected ),
						'extender-advanced-shipment-tracking'
					)
				),
				'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $detected ) ) . '</code>'
			),
			esc_html__(
				'Désactivez le snippet, puis rechargez cette page. Aucune donnée n’est perdue : le module lit les mêmes commandes et pose le même type de suivi.',
				'extender-advanced-shipment-tracking'
			)
		);
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
