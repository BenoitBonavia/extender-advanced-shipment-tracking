<?php
/**
 * Analyse (mise en cache) des commandes candidates au rattrapage PayPal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

use EAST\Integration\PayPalPayments;

defined( 'ABSPATH' ) || exit;

/**
 * Portage de `mh_ppt_scan()`.
 *
 * Résultat mis en cache dans un transient : le scan lit une meta sur chaque
 * commande de la fenêtre, ce qui coûte cher sur mutualisé. `Push::push()`
 * invalide ce cache après un envoi réussi via `forget()`.
 */
final class Scan {

	/**
	 * Version de la forme du résultat mis en cache.
	 *
	 * À incrémenter si la structure d'une ligne change, pour ne jamais lire un
	 * ancien format depuis le transient.
	 */
	private const CACHE_VERSION = 'v1';

	/**
	 * Clé du transient de cache, dépendante de la fenêtre configurée.
	 *
	 * @return string
	 */
	public static function key(): string {
		return 'east_paypal_scan_' . self::CACHE_VERSION . '_' . Config::backfill_days();
	}

	/**
	 * Invalide le cache de l'analyse.
	 */
	public static function forget(): void {
		delete_transient( self::key() );
	}

	/**
	 * Analyse les commandes de la fenêtre de rattrapage.
	 *
	 * @param bool $force Ignorer le cache et relire.
	 *
	 * @return array{generated:int, rows:array<int, array<string, mixed>>, todo:int, done:int, skip:int}
	 */
	public static function run( bool $force = false ): array {
		$key = self::key();

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$since = gmdate( 'Y-m-d', strtotime( '-' . Config::backfill_days() . ' days' ) );

		$statuses = array_diff(
			array_keys( wc_get_order_statuses() ),
			array_map(
				static function ( $status ) {
					return 'wc-' . $status;
				},
				self::excluded_statuses()
			)
		);

		$ids = wc_get_orders(
			array(
				'limit'        => Config::backfill_max(),
				'date_created' => '>' . $since,
				'status'       => $statuses,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'ids',
			)
		);

		$scan = array(
			'generated' => time(),
			'rows'      => array(),
			'todo'      => 0,
			'done'      => 0,
			'skip'      => 0,
		);

		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );

			if ( ! $order instanceof \WC_Order || ! PayPalPayments::uses_gateway( $order ) ) {
				// Une commande payée autrement que par PayPal n'a rien à faire
				// dans cette liste.
				continue;
			}

			$state = OrderState::evaluate( $order );

			$scan['rows'][] = self::build_row( $order, $state );

			if ( $state['eligible'] && $state['pending'] ) {
				++$scan['todo'];
			} elseif ( $state['eligible'] ) {
				++$scan['done'];
			} else {
				++$scan['skip'];
			}
		}

		set_transient( $key, $scan, Config::scan_ttl() );

		return $scan;
	}

	/**
	 * Construit la ligne d'affichage d'une commande.
	 *
	 * Une ligne par colis : numéro, transporteur et état restent appariés,
	 * même quand une partie seulement de la commande est déjà transmise.
	 *
	 * @param \WC_Order            $order Commande.
	 * @param array<string, mixed> $state État déjà évalué par `OrderState::evaluate()`.
	 *
	 * @return array<string, mixed>
	 */
	private static function build_row( \WC_Order $order, array $state ): array {
		$already = array_flip( $state['sent'] );
		$lines   = array();

		foreach ( $state['items'] as $item ) {
			$number   = trim( (string) $item['tracking_number'] );
			$provider = isset( $item['tracking_provider'] ) ? (string) $item['tracking_provider'] : '';
			$custom   = isset( $item['custom_tracking_provider'] ) ? (string) $item['custom_tracking_provider'] : '';

			list( $code, $other ) = CarrierMap::map_carrier( $provider, $custom );

			$label = '' !== trim( $provider ) ? $provider : $custom;
			$label = '' !== trim( $label ) ? $label : '—';

			$lines[] = array(
				'number' => $number,
				'label'  => $label,
				'code'   => $code,
				'other'  => $other,
				'sent'   => isset( $already[ $number ] ),
			);
		}

		return array(
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'edit'     => $order->get_edit_order_url(),
			'date'     => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y' ) : '',
			'total'    => $order->get_formatted_order_total(),
			'status'   => wc_get_order_status_name( $order->get_status() ),
			'eligible' => $state['eligible'],
			'reason'   => $state['reason'],
			'pending'  => $state['pending'],
			'sent'     => $state['sent'],
			'lines'    => $lines,
		);
	}

	/**
	 * Statuts de commande exclus du rattrapage.
	 *
	 * @return string[]
	 */
	private static function excluded_statuses(): array {
		/**
		 * Filtre les statuts de commande exclus du rattrapage AST → PayPal.
		 *
		 * @param string[] $statuses Statuts exclus par défaut.
		 */
		return (array) apply_filters(
			'east_paypal_backfill_excluded_statuses',
			array( 'cancelled', 'failed', 'pending', 'checkout-draft', 'trash' )
		);
	}

	/**
	 * Constructeur privé : classe utilitaire, jamais instanciée.
	 */
	private function __construct() {}
}
