<?php
/**
 * Module : suivi Boxtal → Advanced Shipment Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Modules;

use EAST\BoxtalTracking\OrderAction;
use EAST\BoxtalTracking\SnippetGuard;
use EAST\BoxtalTracking\TrackingLink;
use EAST\BoxtalTracking\TrackingSync;
use EAST\Integration\Boxtal;

defined( 'ABSPATH' ) || exit;

/**
 * Dès qu'un bordereau est généré côté Boxtal, importe le numéro et le lien de
 * suivi dans Advanced Shipment Tracking. La logique vit dans le namespace
 * `EAST\BoxtalTracking\` ; cette classe ne fait qu'accrocher les hooks.
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
	}
}
