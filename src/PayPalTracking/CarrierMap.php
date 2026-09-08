<?php
/**
 * Correspondance transporteur AST → code transporteur PayPal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * Portage direct de la table et de la logique du snippet WPCode remplacé.
 */
final class CarrierMap {

	/**
	 * Table de correspondance.
	 *
	 * Clé   : nom OU slug AST, réduit en minuscules alphanumériques (`key()`).
	 *         Le pont Boxtal écrit le nom d'affichage (« Mondial Relay »), la
	 *         saisie manuelle peut écrire le slug (« mondial-relay »). Les deux
	 *         formes se réduisent à la même clé, une seule entrée suffit.
	 * Valeur : code de l'énumération PayPal (`modules/ppcp-order-tracking/carriers.php`).
	 *
	 * Colissimo : PayPal expose `FR_COLIS` (liste France) et `FR_COLISSIMO`
	 * (liste globale). `FR_COLIS` est retenu. Si PayPal refuse le tracker,
	 * basculer via le filtre `east_paypal_carrier_map`.
	 *
	 * @return array<string, string>
	 */
	public static function map(): array {
		/**
		 * Filtre la table de correspondance transporteur AST → code PayPal.
		 *
		 * @param array<string, string> $map Table par défaut.
		 */
		return apply_filters(
			'east_paypal_carrier_map',
			array(
				'colissimo'    => 'FR_COLIS',
				'chronopost'   => 'CHRONOPOST_FR',
				'mondialrelay' => 'FR_MONDIAL',
				'colisprive'   => 'COLIS_PRIVE',
				'laposte'      => 'LA_POSTE_SUIVI',
				'dpd'          => 'DPD_FR',
				'dpdfrance'    => 'DPD_FR',
				'gls'          => 'GLS',
				'dhl'          => 'DHL',
				'ups'          => 'UPS',
				'fedex'        => 'FEDEX',
				'geodis'       => 'GEODIS',
				'cubyn'        => 'CUBYN',
				'tnt'          => 'TNT_FR',
			)
		);
	}

	/**
	 * Réduit une chaîne à ses caractères alphanumériques minuscules.
	 *
	 * @param string $string Chaîne source.
	 *
	 * @return string
	 */
	public static function key( string $string ): string {
		$string = function_exists( 'remove_accents' ) ? remove_accents( $string ) : $string;

		return strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', $string ) );
	}

	/**
	 * Traduit un transporteur AST en couple (code PayPal, nom libre).
	 *
	 * Rien n'est inventé : un transporteur non mappé part en `OTHER` avec son
	 * nom lisible, ce que fait aussi l'intégration officielle WC Shipment
	 * Tracking.
	 *
	 * @param string $provider        `tracking_provider` de l'entrée AST.
	 * @param string $custom_provider `custom_tracking_provider` de l'entrée AST.
	 *
	 * @return array{0: string, 1: string} [code PayPal, nom libre (vide si code reconnu)].
	 */
	public static function map_carrier( string $provider, string $custom_provider ): array {
		$label = '' !== trim( $provider ) ? $provider : $custom_provider;
		$label = trim( $label );

		$map = self::map();
		$key = self::key( $label );

		if ( '' !== $key && isset( $map[ $key ] ) ) {
			return array( $map[ $key ], '' );
		}

		if ( '' === $label ) {
			$label = __( 'Transporteur', 'extender-advanced-shipment-tracking' );
		}

		return array( 'OTHER', $label );
	}
}
