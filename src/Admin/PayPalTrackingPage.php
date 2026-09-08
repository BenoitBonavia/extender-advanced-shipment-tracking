<?php
/**
 * Écran de rattrapage du suivi AST → PayPal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Admin;

use EAST\Integration\PayPalPayments;
use EAST\PayPalTracking\Config;
use EAST\PayPalTracking\Push;
use EAST\PayPalTracking\Scan;
use EAST\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Sous-page WooCommerce → PayPal : suivi. Portage de `mh_ppt_render_page()` /
 * `mh_ppt_handle_actions()`.
 *
 * Écran dédié plutôt qu'un bloc dans l'onglet de réglages du plugin : ce que
 * cet écran donne à voir — une table par commande, un colis par ligne — n'a
 * pas d'équivalent dans le rattrapage automatique de Boxtal, qui n'a rien de
 * comparable à afficher. Même gabarit que `BrevoBackfillPage` dans
 * `extender-for-back-in-stock-notifier` : la page ne fait que déclencher et
 * afficher, tout le travail réel passe par Action Scheduler.
 */
final class PayPalTrackingPage {

	/**
	 * Slug de la sous-page.
	 */
	public const SLUG = 'east-paypal-tracking';

	/**
	 * Nom du paramètre de requête portant l'action demandée.
	 */
	private const ACTION_PARAM = 'east_ppt_action';

	/**
	 * Accroche la page.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), 60 );
	}

	/**
	 * Déclare la sous-page.
	 */
	public function add_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'PayPal — suivi des colis', 'extender-advanced-shipment-tracking' ),
			__( 'PayPal : suivi', 'extender-advanced-shipment-tracking' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Affiche la page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'extender-advanced-shipment-tracking' ) );
		}

		$notice = $this->handle_actions();
		$scan   = Scan::run();
		$ready  = null !== PayPalPayments::tracking_services();
		$base   = admin_url( 'admin.php?page=' . self::SLUG );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'PayPal — suivi des colis', 'extender-advanced-shipment-tracking' ) . '</h1>';

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: nombre de jours. */
					__( 'Transmission des numéros de suivi Advanced Shipment Tracking vers PayPal. Fenêtre analysée : %d derniers jours.', 'extender-advanced-shipment-tracking' ),
					Config::backfill_days()
				)
			)
		);

		if ( $notice ) {
			printf(
				'<div class="notice %s is-dismissible"><p>%s</p></div>',
				esc_attr( $notice[0] ),
				esc_html( $notice[1] )
			);
		}

		if ( ! $ready ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Les services de suivi de WooCommerce PayPal Payments sont introuvables. Vérifiez que l’extension est active, que le compte PayPal est connecté et que le module de suivi n’est pas désactivé.', 'extender-advanced-shipment-tracking' )
				. '</p></div>';
		}

		if ( ! Config::enabled() ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'L’envoi automatique est désactivé dans les réglages du plugin. Le rattrapage manuel reste opérationnel.', 'extender-advanced-shipment-tracking' )
				. '</p></div>';
		}

		$this->render_counters( $scan );
		$this->render_buttons( $base, $scan );

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: date et heure de l'analyse. */
					__( 'Analyse du %s. Le rattrapage groupé passe par Action Scheduler : les envois sont étalés pour ne pas saturer l’API PayPal ni dépasser les limites de l’hébergement mutualisé.', 'extender-advanced-shipment-tracking' ),
					date_i18n( 'd/m/Y H:i', $scan['generated'] )
				)
			)
		);

		$this->render_table( $scan, $base );

		echo '<p class="description" style="margin-top:16px;">'
			. esc_html__( 'Journal détaillé : WooCommerce → État → Journaux, source « extender-ast ». File d’attente : WooCommerce → État → Actions planifiées, groupe « east ».', 'extender-advanced-shipment-tracking' )
			. '</p>';

		echo '</div>';
	}

	/**
	 * Affiche les trois compteurs.
	 *
	 * @param array $scan Résultat de `Scan::run()`.
	 */
	private function render_counters( array $scan ): void {
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:18px 0 22px;">';

		$cards = array(
			array( __( 'À envoyer', 'extender-advanced-shipment-tracking' ), (int) $scan['todo'], '#b32d2e' ),
			array( __( 'Déjà transmises', 'extender-advanced-shipment-tracking' ), (int) $scan['done'], '#00795a' ),
			array( __( 'Non éligibles', 'extender-advanced-shipment-tracking' ), (int) $scan['skip'], '#787c82' ),
		);

		foreach ( $cards as $card ) {
			printf(
				'<div style="flex:1 1 180px;background:#fff;border:1px solid #dcdcde;border-left:4px solid %1$s;border-radius:4px;padding:14px 16px;">
					<div style="font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:#646970;">%2$s</div>
					<div style="font-size:26px;font-weight:600;line-height:1.3;color:%1$s;">%3$d</div>
				</div>',
				esc_attr( $card[2] ),
				esc_html( $card[0] ),
				(int) $card[1]
			);
		}

		echo '</div>';
	}

	/**
	 * Affiche les boutons d'action globale.
	 *
	 * @param string $base URL de base de la page.
	 * @param array  $scan Résultat de `Scan::run()`.
	 */
	private function render_buttons( string $base, array $scan ): void {
		unset( $scan );

		echo '<p>';

		printf(
			'<a href="%s" class="button button-primary">%s</a> ',
			esc_url( wp_nonce_url( add_query_arg( self::ACTION_PARAM, 'push_all', $base ), 'east_paypal_push_all' ) ),
			esc_html__( 'Rattraper toutes les commandes en attente', 'extender-advanced-shipment-tracking' )
		);

		printf(
			'<a href="%s" class="button">%s</a>',
			esc_url( wp_nonce_url( add_query_arg( self::ACTION_PARAM, 'refresh', $base ), 'east_paypal_refresh' ) ),
			esc_html__( 'Relancer l’analyse', 'extender-advanced-shipment-tracking' )
		);

		echo '</p>';
	}

	/**
	 * Affiche le tableau des commandes.
	 *
	 * @param array  $scan Résultat de `Scan::run()`.
	 * @param string $base URL de base de la page.
	 */
	private function render_table( array $scan, string $base ): void {
		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:14px;">';
		echo '<thead><tr>';
		echo '<th style="width:110px;">' . esc_html__( 'Commande', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '<th style="width:95px;">' . esc_html__( 'Date', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '<th style="width:130px;">' . esc_html__( 'Statut', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '<th>' . esc_html__( 'Suivi / transporteur → code PayPal', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '<th style="width:150px;">' . esc_html__( 'État PayPal', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '<th style="width:110px;">' . esc_html__( 'Action', 'extender-advanced-shipment-tracking' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $scan['rows'] ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Aucune commande PayPal sur la période.', 'extender-advanced-shipment-tracking' ) . '</td></tr>';
		}

		foreach ( $scan['rows'] as $row ) {
			$this->render_row( $row, $base );
		}

		echo '</tbody></table>';
	}

	/**
	 * Affiche une ligne de commande.
	 *
	 * @param array<string, mixed> $row  Ligne construite par `Scan`.
	 * @param string               $base URL de base de la page.
	 */
	private function render_row( array $row, string $base ): void {
		$edit = ! empty( $row['edit'] )
			? $row['edit']
			: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $row['id'] );

		echo '<tr>';

		printf(
			'<td><a href="%s"><strong>#%s</strong></a><br><span class="description">%s</span></td>',
			esc_url( $edit ),
			esc_html( $row['number'] ),
			wp_kses_post( $row['total'] )
		);

		printf( '<td>%s</td>', esc_html( $row['date'] ) );
		printf( '<td>%s</td>', esc_html( $row['status'] ) );

		echo '<td>';
		$this->render_lines( $row['lines'] );
		echo '</td>';

		echo '<td>';
		$this->render_state( $row );
		echo '</td>';

		echo '<td>';

		if ( $row['eligible'] && $row['pending'] ) {
			printf(
				'<a href="%s" class="button button-small">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								self::ACTION_PARAM => 'push_one',
								'order_id'         => (int) $row['id'],
							),
							$base
						),
						'east_paypal_push_one'
					)
				),
				esc_html__( 'Envoyer', 'extender-advanced-shipment-tracking' )
			);
		} else {
			echo '—';
		}

		echo '</td></tr>';
	}

	/**
	 * Affiche la liste des colis d'une commande.
	 *
	 * @param array<int, array<string, mixed>> $lines Colis de la ligne.
	 */
	private function render_lines( array $lines ): void {
		if ( empty( $lines ) ) {
			echo '<span class="description">' . esc_html__( 'aucun', 'extender-advanced-shipment-tracking' ) . '</span>';

			return;
		}

		echo '<ul style="margin:0;">';

		foreach ( $lines as $line ) {
			printf(
				'<li style="margin:0 0 2px;%1$s"><code>%2$s</code> &nbsp;%3$s <span style="color:#646970;">→</span> <strong>%4$s</strong>%5$s %6$s</li>',
				$line['sent'] ? 'opacity:.55;' : '',
				esc_html( $line['number'] ),
				esc_html( $line['label'] ),
				esc_html( $line['code'] ),
				$line['other'] ? ' <span style="color:#646970;">(' . esc_html( $line['other'] ) . ')</span>' : '',
				$line['sent'] ? '<span style="color:#00795a;">✓</span>' : ''
			);
		}

		echo '</ul>';
	}

	/**
	 * Affiche l'état PayPal d'une commande.
	 *
	 * @param array<string, mixed> $row Ligne construite par `Scan`.
	 */
	private function render_state( array $row ): void {
		if ( ! $row['eligible'] ) {
			printf( '<span style="color:#787c82;">%s</span>', esc_html( $row['reason'] ) );

			return;
		}

		if ( $row['pending'] ) {
			printf(
				'<span style="color:#b32d2e;font-weight:600;">%s</span>',
				sprintf(
					/* translators: %d: nombre de numéros de suivi. */
					esc_html__( '%d à envoyer', 'extender-advanced-shipment-tracking' ),
					count( $row['pending'] )
				)
			);

			return;
		}

		printf(
			'<span style="color:#00795a;font-weight:600;">%s</span>',
			sprintf(
				/* translators: %d: nombre de numéros de suivi. */
				esc_html__( '%d transmis', 'extender-advanced-shipment-tracking' ),
				count( $row['sent'] )
			)
		);
	}

	/**
	 * Traite l'action demandée en requête.
	 *
	 * @return array{0: string, 1: string}|null Classe de notice et message, ou null si aucune action.
	 */
	private function handle_actions(): ?array {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- le nonce est vérifié juste après, une fois l'action connue.
		$action = isset( $_REQUEST[ self::ACTION_PARAM ] ) ? sanitize_key( wp_unslash( $_REQUEST[ self::ACTION_PARAM ] ) ) : '';

		if ( '' === $action ) {
			return null;
		}

		check_admin_referer( 'east_paypal_' . $action );

		if ( 'refresh' === $action ) {
			Scan::forget();

			return array( 'notice-info', __( 'Analyse relancée.', 'extender-advanced-shipment-tracking' ) );
		}

		if ( 'push_one' === $action ) {
			return $this->handle_push_one();
		}

		if ( 'push_all' === $action ) {
			return $this->handle_push_all();
		}

		return null;
	}

	/**
	 * Traite l'envoi manuel d'une seule commande.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function handle_push_one(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce déjà vérifié par l'appelant.
		$order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
		$result   = Push::push( $order_id );

		Scan::forget();

		$class = $result['failed'] ? 'notice-error' : ( $result['sent'] ? 'notice-success' : 'notice-warning' );

		return array(
			$class,
			sprintf(
				/* translators: 1: identifiant de commande, 2: résumé du résultat. */
				__( 'Commande #%1$d : %2$s.', 'extender-advanced-shipment-tracking' ),
				$order_id,
				$result['message']
			),
		);
	}

	/**
	 * Traite le rattrapage groupé : programme chaque commande éligible à un
	 * décalage croissant.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function handle_push_all(): array {
		$scan   = Scan::run( true );
		$queued = 0;
		$offset = 30;

		foreach ( $scan['rows'] as $row ) {
			if ( ! $row['eligible'] || ! $row['pending'] ) {
				continue;
			}

			Scheduler::schedule( time() + $offset, Push::HOOK_PUSH, (int) $row['id'], true );

			$offset += Config::spacing();
			++$queued;
		}

		Scan::forget();

		if ( ! $queued ) {
			return array( 'notice-info', __( 'Aucune commande à rattraper.', 'extender-advanced-shipment-tracking' ) );
		}

		return array(
			'notice-success',
			sprintf(
				/* translators: 1: nombre de commandes, 2: minutes. */
				__( '%1$d commande(s) mises en file. Traitement étalé sur environ %2$d minute(s) par Action Scheduler.', 'extender-advanced-shipment-tracking' ),
				$queued,
				max( 1, (int) ceil( $offset / 60 ) )
			),
		);
	}
}
