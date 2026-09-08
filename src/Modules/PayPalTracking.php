<?php
/**
 * Module : suivi AST → PayPal Package Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\Modules;

use EAST\Admin\PayPalTrackingPage;
use EAST\Integration\PayPalPayments;
use EAST\PayPalTracking\Config;
use EAST\PayPalTracking\OrderAction;
use EAST\PayPalTracking\Push;
use EAST\PayPalTracking\SnippetGuard;
use EAST\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Dès qu'un suivi est ajouté dans AST — par le pont Boxtal, une saisie
 * manuelle, l'action groupée ou l'API REST, peu importe l'origine — transmet
 * son numéro à PayPal via l'API interne de PayPal Payments. Indépendant du
 * module Boxtal → AST : aucune dépendance envers lui.
 */
final class PayPalTracking extends AbstractModule {

	/**
	 * {@inheritDoc}
	 */
	protected $id = 'paypal_tracking';

	/**
	 * {@inheritDoc}
	 */
	protected $title = 'Suivi AST → PayPal';

	/**
	 * {@inheritDoc}
	 */
	protected $dependencies = array( PayPalPayments::class );

	/**
	 * {@inheritDoc}
	 *
	 * N'accroche aucun hook tant que le snippet WPCode remplacé est détecté —
	 * voir `SnippetGuard`.
	 */
	public function register(): void {
		if ( SnippetGuard::snippet_is_active() ) {
			add_action( 'admin_notices', array( SnippetGuard::class, 'render_notice' ) );

			return;
		}

		/*
		 * AST déclenche ce hook depuis add_tracking_item() ET
		 * insert_tracking_item(), après l'écriture de sa méta de suivi. Il
		 * couvre donc toutes les origines : pont Boxtal, saisie manuelle,
		 * action groupée, API REST.
		 */
		add_action( 'update_order_status_after_adding_tracking', array( $this, 'schedule_push' ), 20, 2 );
		add_action( Push::HOOK_PUSH, array( Push::class, 'push' ), 10, 1 );

		( new OrderAction() )->register();

		if ( is_admin() ) {
			( new PayPalTrackingPage() )->register();
		}
	}

	/**
	 * Programme un envoi différé après l'ajout d'un suivi dans AST.
	 *
	 * Le délai laisse le temps à tous les colis d'une même expédition
	 * d'arriver, pour qu'un seul passage les traite tous ; le dédoublonnage
	 * par argument de `Support\Scheduler` absorbe les ajouts successifs sur la
	 * même commande.
	 *
	 * @param mixed $status_shipped Non utilisé : argument émis par AST.
	 * @param mixed $order          Commande concernée.
	 */
	public function schedule_push( $status_shipped, $order ): void {
		unset( $status_shipped );

		if ( ! Config::enabled() || ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		Scheduler::schedule( time() + Config::delay(), Push::HOOK_PUSH, $order->get_id(), true );
	}
}
