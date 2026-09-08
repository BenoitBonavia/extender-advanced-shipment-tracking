<?php
/**
 * Réglages du module de suivi AST → PayPal.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\PayPalTracking;

use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Résout chaque réglage dans cet ordre : constante héritée du snippet WPCode
 * (`MH_PPT_*`) → option `east_paypal_*` → défaut — même schéma que
 * `BoxtalTracking\Config`.
 */
final class Config {

	public const KEY_ENABLED       = 'paypal_enabled';
	public const KEY_DELAY         = 'paypal_delay';
	public const KEY_STATUS        = 'paypal_status';
	public const KEY_BACKFILL_DAYS = 'paypal_backfill_days';
	public const KEY_BACKFILL_MAX  = 'paypal_backfill_max';
	public const KEY_SPACING       = 'paypal_spacing';
	public const KEY_DEBUG         = 'paypal_debug';
	public const KEY_SCAN_TTL      = 'paypal_scan_ttl';

	public const DEFAULT_DELAY         = 60;
	public const DEFAULT_STATUS        = 'SHIPPED';
	public const DEFAULT_BACKFILL_DAYS = 90;
	public const DEFAULT_BACKFILL_MAX  = 600;
	public const DEFAULT_SPACING       = 4;
	public const DEFAULT_SCAN_TTL      = 300;

	/**
	 * Statuts de suivi acceptés par l'API PayPal Package Tracking.
	 *
	 * @var string[]
	 */
	public const VALID_STATUSES = array( 'SHIPPED', 'ON_HOLD', 'DELIVERED', 'CANCELLED' );

	/**
	 * Correspondance clé de réglage → constante héritée du snippet WPCode.
	 *
	 * @var array<string, string>
	 */
	private const CONSTANT_MAP = array(
		self::KEY_ENABLED       => 'MH_PPT_ENABLED',
		self::KEY_DELAY         => 'MH_PPT_DELAY',
		self::KEY_STATUS        => 'MH_PPT_STATUS',
		self::KEY_BACKFILL_DAYS => 'MH_PPT_BACKFILL_DAYS',
		self::KEY_BACKFILL_MAX  => 'MH_PPT_BACKFILL_MAX',
		self::KEY_SPACING       => 'MH_PPT_SPACING',
		self::KEY_DEBUG         => 'MH_PPT_DEBUG',
		self::KEY_SCAN_TTL      => 'MH_PPT_SCAN_TTL',
	);

	/**
	 * Réglages actuellement imposés par une constante encore définie.
	 *
	 * @return array<string, string> Clé de réglage => nom de la constante.
	 */
	public static function constant_overrides(): array {
		$overrides = array();

		foreach ( self::CONSTANT_MAP as $key => $constant ) {
			if ( defined( $constant ) ) {
				$overrides[ $key ] = $constant;
			}
		}

		return $overrides;
	}

	/**
	 * Interrupteur général de l'envoi automatique.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return self::resolve_bool( self::KEY_ENABLED, true );
	}

	/**
	 * Délai avant traitement d'un ajout de suivi, en secondes.
	 *
	 * Laisse le temps à tous les colis d'une même expédition d'être ajoutés à
	 * AST avant qu'un seul passage ne les traite tous.
	 *
	 * @return int
	 */
	public static function delay(): int {
		return self::resolve_int( self::KEY_DELAY, self::DEFAULT_DELAY );
	}

	/**
	 * Statut transmis à PayPal, contraint aux quatre valeurs valides.
	 *
	 * @return string
	 */
	public static function status(): string {
		$constant = self::CONSTANT_MAP[ self::KEY_STATUS ];
		$value    = defined( $constant ) ? (string) constant( $constant ) : (string) Settings::get( self::KEY_STATUS, self::DEFAULT_STATUS );
		$value    = strtoupper( trim( $value ) );

		return in_array( $value, self::VALID_STATUSES, true ) ? $value : self::DEFAULT_STATUS;
	}

	/**
	 * Ancienneté maximale (en jours) des commandes analysées par l'écran de
	 * rattrapage.
	 *
	 * @return int
	 */
	public static function backfill_days(): int {
		return self::resolve_int( self::KEY_BACKFILL_DAYS, self::DEFAULT_BACKFILL_DAYS );
	}

	/**
	 * Plafond de commandes analysées par l'écran de rattrapage.
	 *
	 * @return int
	 */
	public static function backfill_max(): int {
		return self::resolve_int( self::KEY_BACKFILL_MAX, self::DEFAULT_BACKFILL_MAX );
	}

	/**
	 * Espacement entre deux envois lors d'un rattrapage groupé, en secondes.
	 *
	 * @return int
	 */
	public static function spacing(): int {
		return self::resolve_int( self::KEY_SPACING, self::DEFAULT_SPACING );
	}

	/**
	 * Journaliser la charge utile envoyée à PayPal.
	 *
	 * @return bool
	 */
	public static function debug(): bool {
		return self::resolve_bool( self::KEY_DEBUG, false );
	}

	/**
	 * Durée du cache de l'analyse de rattrapage, en secondes.
	 *
	 * @return int
	 */
	public static function scan_ttl(): int {
		return self::resolve_int( self::KEY_SCAN_TTL, self::DEFAULT_SCAN_TTL );
	}

	/**
	 * Résout un réglage booléen.
	 *
	 * @param string $key     Clé de réglage.
	 * @param bool   $default Valeur de repli.
	 *
	 * @return bool
	 */
	private static function resolve_bool( string $key, bool $default ): bool {
		$constant = self::CONSTANT_MAP[ $key ] ?? '';

		if ( '' !== $constant && defined( $constant ) ) {
			return (bool) constant( $constant );
		}

		return Settings::get_bool( $key, $default );
	}

	/**
	 * Résout un réglage entier.
	 *
	 * @param string $key     Clé de réglage.
	 * @param int    $default Valeur de repli.
	 *
	 * @return int
	 */
	private static function resolve_int( string $key, int $default ): int {
		$constant = self::CONSTANT_MAP[ $key ] ?? '';

		if ( '' !== $constant && defined( $constant ) ) {
			return (int) constant( $constant );
		}

		$value = Settings::get( $key, $default );

		return is_numeric( $value ) ? (int) $value : $default;
	}
}
