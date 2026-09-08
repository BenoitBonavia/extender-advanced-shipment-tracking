<?php
/**
 * Priorité au lien de suivi renvoyé par Boxtal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

defined( 'ABSPATH' ) || exit;

/**
 * AST construit le lien de suivi depuis son propre modèle d'URL. Pour Mondial
 * Relay ce modèle exige le code postal, qu'AST prend sur l'adresse de
 * livraison — ce qui ne correspond pas forcément au colis en point relais.
 * L'URL renvoyée par Boxtal, elle, est toujours valide : on la substitue
 * quand on l'a.
 */
final class TrackingLink {

	/**
	 * Filtre `ast_tracking_link`.
	 *
	 * @param string $link                Lien construit par AST.
	 * @param string $tracking_number     Numéro de suivi.
	 * @param int    $order_id            Commande.
	 * @param bool   $trackship_supported Non utilisé ici, transmis par AST.
	 *
	 * @return string
	 */
	public static function filter_link( $link, $tracking_number, $order_id, $trackship_supported ) {
		if ( ! Config::prefer_boxtal_link() ) {
			return $link;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return $link;
		}

		$map = $order->get_meta( Legacy::META_TRACKING_URLS );

		if ( ! is_array( $map ) ) {
			return $link;
		}

		$key = CarrierResolver::slug( (string) $tracking_number );

		return ! empty( $map[ $key ] ) ? $map[ $key ] : $link;
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
