<?php
/**
 * Détection du snippet WPCode que ce module remplace.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Empêche le module et le snippet de tourner en même temps.
 *
 * Les deux accrochent le même déclencheur AST (`update_order_status_after_adding_tracking`)
 * et écrivent la même meta de journal (`Legacy::META_SENT`) : les laisser
 * cohabiter provoquerait un envoi en double vers PayPal. Comme le snippet ne
 * peut pas s'effacer de lui-même, c'est le module qui se met en veille tant
 * qu'il est détecté.
 *
 * Même dépendance de calendrier que `BoxtalTracking\SnippetGuard` : WPCode
 * exécute les snippets « Exécuter partout » sur `plugins_loaded` priorité 5,
 * ce plugin s'amorce sur ce même hook en priorité 20.
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

		$detected = self::detected_functions();

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> — %s</p><p>%s</p><p>%s</p></div>',
			esc_html__( 'Extender for Advanced Shipment Tracking', 'extender-advanced-shipment-tracking' ),
			esc_html__(
				'Le snippet de pont AST → PayPal est toujours actif : le module du plugin reste en veille pour éviter les doublons.',
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
				'Désactivez le snippet, puis rechargez cette page. Aucune donnée n’est perdue : le module lit les mêmes commandes et le même journal d’envoi.',
				'extender-advanced-shipment-tracking'
			)
		);
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
