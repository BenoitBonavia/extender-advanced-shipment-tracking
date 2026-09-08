<?php
/**
 * Résolution du transporteur AST à partir des signaux disponibles côté Boxtal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

use EAST\Integration\Boxtal;

defined( 'ABSPATH' ) || exit;

/**
 * Cascade de quatre signaux, du plus fiable au plus faible. Portage direct des
 * fonctions `mh_bxt_*` du snippet WPCode remplacé — voir le commentaire de
 * chaque méthode pour la justification d'origine.
 */
final class CarrierResolver {

	/**
	 * Table des transporteurs reconnus.
	 *
	 * 'ast'     : nom EXACT du transporteur dans AST (doit exister dans sa table).
	 * 'aliases' : fragments cherchés dans le nom de réseau Boxtal, l'URL de suivi
	 *             et le libellé de la méthode de livraison (comparés sans accent,
	 *             sans espace, sans ponctuation, en minuscules — voir `slug()`).
	 *
	 * @return array<string, array{ast: string, aliases: string[]}>
	 */
	public static function carriers(): array {
		/**
		 * Filtre la table des transporteurs reconnus par le pont Boxtal → AST.
		 *
		 * @param array<string, array{ast: string, aliases: string[]}> $carriers Table par défaut.
		 */
		return apply_filters(
			'east_boxtal_carriers',
			array(
				'mondial-relay' => array(
					'ast'     => 'Mondial Relay',
					'aliases' => array( 'mondialrelay', 'inpost', 'monr' ),
				),
				'chronopost'    => array(
					'ast'     => 'Chronopost',
					'aliases' => array( 'chronopost', 'chronorelais', 'chrono' ),
				),
				'colissimo'     => array(
					'ast'     => 'Colissimo',
					'aliases' => array( 'colissimo', 'socolissimo', 'laposte' ),
				),
			)
		);
	}

	/**
	 * Réduit une chaîne à ses caractères alphanumériques minuscules, pour une
	 * comparaison insensible aux accents, espaces et ponctuation.
	 *
	 * @param string $string Chaîne source.
	 *
	 * @return string
	 */
	public static function slug( string $string ): string {
		$string = function_exists( 'remove_accents' ) ? remove_accents( $string ) : $string;

		return strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', $string ) );
	}

	/**
	 * Cherche un transporteur connu dans une chaîne quelconque.
	 *
	 * @param string $haystack Chaîne dans laquelle chercher.
	 *
	 * @return string|null Nom AST du transporteur, ou null si aucun ne correspond.
	 */
	public static function match_carrier( string $haystack ): ?string {
		$needle = self::slug( $haystack );

		if ( '' === $needle ) {
			return null;
		}

		foreach ( self::carriers() as $carrier ) {
			foreach ( $carrier['aliases'] as $alias ) {
				if ( false !== strpos( $needle, self::slug( $alias ) ) ) {
					return $carrier['ast'];
				}
			}
		}

		return null;
	}

	/**
	 * Signal 1 — le plus fiable.
	 *
	 * Pour une livraison en point relais, Boxtal stocke le réseau choisi sur la
	 * commande, et l'option `BW_PP_NETWORKS` associe chaque réseau aux noms de
	 * transporteurs renvoyés par l'API Boxtal. Ce n'est donc pas une déduction :
	 * c'est Boxtal qui nomme le transporteur.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return string|null
	 */
	public static function from_parcelpoint( \WC_Order $order ): ?string {
		$point = Boxtal::get_parcelpoint( $order );

		if ( ! is_object( $point ) || empty( $point->network ) ) {
			return null;
		}

		$network  = (string) $point->network;
		$networks = Boxtal::get_parcelpoint_networks();

		if ( ! empty( $networks[ $network ] ) ) {
			$match = self::match_carrier( implode( ' ', $networks[ $network ] ) );

			if ( $match ) {
				return $match;
			}
		}

		// Le code réseau lui-même peut porter le nom (ex. MONR).
		return self::match_carrier( $network );
	}

	/**
	 * Signal 3 — libellé de la méthode de livraison WooCommerce.
	 *
	 * @param \WC_Order $order Commande.
	 *
	 * @return string|null
	 */
	public static function from_shipping_method( \WC_Order $order ): ?string {
		$label = '';

		foreach ( $order->get_shipping_methods() as $method ) {
			$label .= ' ' . $method->get_name() . ' ' . $method->get_method_id();
		}

		return self::match_carrier( $label );
	}

	/**
	 * Signal 4 — format du numéro. Dernier recours, faible confiance.
	 *
	 * Un numéro d'expédition Mondial Relay est purement numérique ; Colissimo et
	 * Chronopost partagent le format S10 de La Poste et ne sont PAS distinguables
	 * de cette façon — on ne tente donc rien pour ces deux-là.
	 *
	 * @param string $number Numéro de suivi.
	 *
	 * @return string|null
	 */
	public static function from_number( string $number ): ?string {
		if ( ! Config::guess_from_number() ) {
			return null;
		}

		return preg_match( '/^\d{8,12}$/', trim( $number ) ) ? 'Mondial Relay' : null;
	}

	/**
	 * Cascade complète.
	 *
	 * @param \WC_Order $order        Commande.
	 * @param string    $tracking_url URL de suivi renvoyée par Boxtal (peut être vide).
	 * @param string    $number       Numéro de suivi.
	 *
	 * @return array{0: string|null, 1: string} Transporteur AST résolu (ou null) et source du signal.
	 */
	public static function resolve( \WC_Order $order, string $tracking_url, string $number ): array {
		$carrier = self::from_parcelpoint( $order );

		if ( $carrier ) {
			return array( $carrier, 'point relais Boxtal' );
		}

		$carrier = self::match_carrier( $tracking_url );

		if ( $carrier ) {
			return array( $carrier, 'url de suivi' );
		}

		$carrier = self::from_shipping_method( $order );

		if ( $carrier ) {
			return array( $carrier, 'méthode de livraison' );
		}

		$carrier = self::from_number( $number );

		if ( $carrier ) {
			return array( $carrier, 'format du numéro (faible confiance)' );
		}

		return array( null, 'non identifié' );
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
