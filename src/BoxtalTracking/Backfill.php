<?php
/**
 * Rattrapage en masse du suivi Boxtal → Advanced Shipment Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

use EAST\Integration\AdvancedShipmentTracking;
use EAST\Integration\Boxtal;
use EAST\Support\BatchJob;
use EAST\Support\BatchRunner;
use EAST\Support\JobState;
use EAST\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Balaie les commandes existantes et importe le suivi de celles qui n'en ont
 * encore aucun.
 *
 * Règle non négociable, demandée explicitement : **aucune commande portant
 * déjà au moins une entrée de suivi n'est jamais touchée**, quelle qu'en soit
 * l'origine (import manuel, autre extension, précédent passage de ce
 * rattrapage). Ce n'est pas la même garde que `TrackingSync::import()`, qui
 * dédoublonne PAR NUMÉRO — utile en temps réel, où une commande multi-colis
 * légitimement reçoit plusieurs entrées au fil de l'eau. Un balayage
 * automatique de tout l'historique ne prend pas ce risque : une commande déjà
 * pourvue est considérée réglée.
 *
 * Diffère aussi du déclencheur temps réel (`TrackingSync::sync()`) sur un
 * point : **aucune relance n'est programmée**. Une commande sans expédition
 * Boxtal côté API est simplement laissée de côté ; le rattrapage avance vers
 * la suivante plutôt que de s'attarder sur une commande qui n'a probablement
 * jamais transité par Boxtal.
 */
final class Backfill implements BatchJob {

	/**
	 * Identifiant du travail.
	 */
	public const JOB_ID = 'boxtal_ast_backfill';

	/**
	 * Hook Action Scheduler d'une étape.
	 */
	public const HOOK_STEP = 'east_boxtal_backfill_step';

	/**
	 * Commandes examinées par lot.
	 *
	 * Volontairement bas : chaque commande y coûte un appel HTTP bloquant vers
	 * l'API Boxtal, contrairement aux autres traitements par lots du plugin.
	 * Même ordre de grandeur que le plafond de l'action groupée manuelle
	 * (`Config::bulk_max()`), pour la même raison.
	 */
	private const BATCH_SIZE = 20;

	/**
	 * Statuts de commande jamais candidats : aucun n'a pu être expédié.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_STATUSES = array( 'pending', 'on-hold', 'cancelled', 'refunded', 'failed', 'checkout-draft' );

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::JOB_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_hook(): string {
		return self::HOOK_STEP;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_batch_size(): int {
		return self::BATCH_SIZE;
	}

	/**
	 * Accroche le hook d'étape.
	 */
	public function register(): void {
		add_action( self::HOOK_STEP, array( $this, 'run_step' ) );
	}

	/**
	 * Exécute une étape. Point d'entrée du hook Action Scheduler.
	 */
	public function run_step(): void {
		( new BatchRunner( $this ) )->run_step();
	}

	/**
	 * Relance le travail s'il s'est interrompu.
	 */
	public function revive_if_stalled(): void {
		( new BatchRunner( $this ) )->revive_if_stalled();
	}

	/**
	 * Démarre — ou relance — le rattrapage.
	 *
	 * @param bool $force Repartir de zéro même si le travail est déjà terminé.
	 */
	public function start( bool $force = false ): void {
		( new BatchRunner( $this ) )->start( $force );
	}

	/**
	 * Amorce le rattrapage au premier chargement du module, et le démarre
	 * aussitôt.
	 *
	 * Contrairement à un rattrapage vers un service tiers, importer un suivi
	 * manquant est strictement additif : rien ici ne justifie d'attendre une
	 * validation du marchand. `JobState::bootstrap()` repose sur `add_option()`,
	 * qui échoue silencieusement si l'état existe déjà — cette méthode est donc
	 * sans effet après le tout premier appel.
	 */
	public function maybe_bootstrap(): void {
		if ( ! JobState::bootstrap( self::JOB_ID, JobState::STATUS_PENDING ) ) {
			return;
		}

		Logger::info( 'Rattrapage Boxtal → AST amorcé automatiquement.' );

		$this->start();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $cursor Nombre de commandes déjà examinées.
	 * @param int $limit  Taille du lot.
	 *
	 * @return array{cursor:int, processed:int, affected:int, done:bool}
	 */
	public function process( int $cursor, int $limit ): array {
		$order_ids = self::candidate_order_ids( $cursor, $limit );

		if ( empty( $order_ids ) ) {
			return array(
				'cursor'    => $cursor,
				'processed' => 0,
				'affected'  => 0,
				'done'      => true,
			);
		}

		$processed = 0;
		$affected  = 0;

		foreach ( $order_ids as $order_id ) {
			++$processed;

			$order = wc_get_order( $order_id );

			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			if ( ! empty( AdvancedShipmentTracking::get_tracking_items( $order_id ) ) ) {
				// Déjà du suivi, quelle qu'en soit l'origine : on n'y touche pas.
				continue;
			}

			$tracking = Boxtal::get_order_tracking( $order_id );

			if ( ! is_object( $tracking )
				|| ! property_exists( $tracking, 'shipmentsTracking' )
				|| empty( $tracking->shipmentsTracking ) ) {
				// Jamais expédiée via Boxtal (ou pas encore) : pas de relance ici,
				// contrairement au déclencheur temps réel — on avance.
				continue;
			}

			$affected += TrackingSync::import( $order, $tracking )['added'];
		}

		return array(
			'cursor'    => $cursor + count( $order_ids ),
			'processed' => $processed,
			'affected'  => $affected,
			'done'      => count( $order_ids ) < $limit,
		);
	}

	/**
	 * Commandes candidates, du plus récent au plus ancien.
	 *
	 * Les plus récentes d'abord : c'est là qu'un suivi manquant se voit et gêne
	 * le plus vite. `wc_get_orders()` plutôt qu'une requête SQL directe : seule
	 * abstraction qui fonctionne aussi bien en stockage historique qu'en HPOS.
	 *
	 * @param int $cursor Nombre de commandes déjà examinées (décalage).
	 * @param int $limit  Taille du lot.
	 *
	 * @return int[]
	 */
	private static function candidate_order_ids( int $cursor, int $limit ): array {
		$args = array(
			'status'  => self::eligible_statuses(),
			'orderby' => 'date',
			'order'   => 'DESC',
			'limit'   => $limit,
			'offset'  => $cursor,
			'return'  => 'ids',
		);

		$days = Config::backfill_days();

		if ( $days > 0 ) {
			$args['date_created'] = '>' . ( time() - $days * DAY_IN_SECONDS );
		}

		return array_map( 'absint', (array) wc_get_orders( $args ) );
	}

	/**
	 * Statuts de commande candidats au rattrapage.
	 *
	 * @return string[]
	 */
	private static function eligible_statuses(): array {
		$all = array_map(
			static function ( $status ) {
				return preg_replace( '/^wc-/', '', (string) $status );
			},
			array_keys( wc_get_order_statuses() )
		);

		/**
		 * Filtre les statuts de commande candidats au rattrapage Boxtal → AST.
		 *
		 * @param string[] $statuses Statuts éligibles par défaut.
		 */
		return (array) apply_filters(
			'east_boxtal_backfill_statuses',
			array_values( array_diff( $all, self::EXCLUDED_STATUSES ) )
		);
	}
}
