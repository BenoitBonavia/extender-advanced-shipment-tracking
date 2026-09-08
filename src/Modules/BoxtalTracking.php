<?php
/**
 * Module : suivi Boxtal → Advanced Shipment Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Modules;

use EAST\Admin\Admin;
use EAST\BoxtalTracking\Backfill;
use EAST\BoxtalTracking\BulkAction;
use EAST\BoxtalTracking\OrderAction;
use EAST\BoxtalTracking\SnippetGuard;
use EAST\BoxtalTracking\TrackingLink;
use EAST\BoxtalTracking\TrackingSync;
use EAST\Integration\Boxtal;

defined( 'ABSPATH' ) || exit;

/**
 * Dès qu'un bordereau est généré côté Boxtal, importe le numéro et le lien de
 * suivi dans Advanced Shipment Tracking — en temps réel, en lot depuis la
 * liste des commandes, ou par rattrapage automatique de l'historique à
 * l'installation. La logique vit dans le namespace `EAST\BoxtalTracking\` ;
 * cette classe ne fait qu'accrocher les hooks.
 */
final class BoxtalTracking extends AbstractModule {

	/**
	 * {@inheritDoc}
	 */
	protected $id = 'boxtal_tracking';

	/**
	 * {@inheritDoc}
	 */
	protected $title = 'Suivi Boxtal → Advanced Shipment Tracking';

	/**
	 * {@inheritDoc}
	 */
	protected $dependencies = array( Boxtal::class );

	/**
	 * Action `admin-post.php` de relance manuelle du rattrapage.
	 *
	 * Sert aussi d'action de nonce : une seule chaîne pour les deux rôles,
	 * cohérent avec l'usage qu'en fait `SettingsTab::backfill_status_html()`.
	 */
	public const RESTART_ACTION = 'east_boxtal_backfill_restart';

	/**
	 * Rattrapage en masse, initialisé par `register()`.
	 *
	 * @var Backfill|null
	 */
	private $backfill = null;

	/**
	 * {@inheritDoc}
	 *
	 * N'accroche aucun hook tant que le snippet WPCode remplacé est détecté —
	 * voir `SnippetGuard`. C'est un troisième état, orthogonal à
	 * `is_available()` (dépendance tierce manquante) : un module peut être
	 * disponible et activé, et pourtant rester en veille le temps de la bascule.
	 */
	public function register(): void {
		if ( SnippetGuard::snippet_is_active() ) {
			add_action( 'admin_notices', array( SnippetGuard::class, 'render_notice' ) );

			return;
		}

		add_action( Boxtal::ACTION_ORDER_SHIPPED, array( TrackingSync::class, 'sync' ), 10, 1 );
		add_action( TrackingSync::RETRY_HOOK, array( TrackingSync::class, 'sync' ), 10, 2 );
		add_filter( 'ast_tracking_link', array( TrackingLink::class, 'filter_link' ), 10, 4 );

		( new OrderAction() )->register();
		( new BulkAction() )->register();

		$this->backfill = new Backfill();
		$this->backfill->register();

		/*
		 * `east_upgrade` couvre l'activation (installation vierge, $installed
		 * vide) et les mises à jour futures du plugin. `admin_init` rejoue
		 * l'amorçage — sans effet une fois l'état créé, cf. `bootstrap()` — et
		 * relance un rattrapage resté figé après une interruption brutale.
		 */
		add_action( 'east_upgrade', array( $this->backfill, 'maybe_bootstrap' ) );
		add_action( 'admin_init', array( $this, 'revive_backfill' ) );
		add_action( 'admin_post_' . self::RESTART_ACTION, array( $this, 'handle_restart' ) );
	}

	/**
	 * Amorce le rattrapage s'il ne l'est pas encore, et le relance s'il s'est figé.
	 */
	public function revive_backfill(): void {
		if ( null === $this->backfill ) {
			return;
		}

		$this->backfill->maybe_bootstrap();
		$this->backfill->revive_if_stalled();
	}

	/**
	 * Traite le clic sur « Relancer le rattrapage » de l'onglet de réglages.
	 *
	 * Aucune confirmation bloquante : relancer un balayage déjà terminé ne peut
	 * rien casser, au pire retraiter des commandes déjà à jour (ignorées
	 * immédiatement par `Backfill::process()`).
	 */
	public function handle_restart(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::RESTART_ACTION ) ) {
			wp_die( esc_html__( 'Action refusée.', 'extender-advanced-shipment-tracking' ) );
		}

		( new Backfill() )->start( true );

		// Pas de paramètre de confirmation : le bloc Diagnostic lit l'état à
		// chaque affichage, la relance y est donc visible immédiatement.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => Admin::SETTINGS_TAB,
					'section' => 'boxtal',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}
}
