<?php
/**
 * Réglages du module de suivi Boxtal → Advanced Shipment Tracking.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

namespace EAST\BoxtalTracking;

use EAST\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Résout chaque réglage dans cet ordre : constante héritée du snippet WPCode
 * (`MH_BXT_*`) → option `east_boxtal_*` → défaut.
 *
 * Une constante encore définie (dans wp-config.php, par exemple) prime : c'est
 * ce qui permet une bascule progressive sans perdre la configuration déjà en
 * place. Le champ correspondant reste néanmoins modifiable dans les réglages —
 * voir `Admin\SettingsTab::override_note()`.
 */
final class Config {

	public const KEY_PREFER_LINK       = 'boxtal_prefer_link';
	public const KEY_STATUS_SHIPPED    = 'boxtal_status_shipped';
	public const KEY_GUESS_FROM_NUMBER = 'boxtal_guess_from_number';
	public const KEY_DEBUG             = 'boxtal_debug';
	public const KEY_MAX_RETRY         = 'boxtal_max_retry';
	public const KEY_RETRY_DELAY       = 'boxtal_retry_delay';
	public const KEY_BULK_MAX          = 'boxtal_bulk_max';
	public const KEY_BACKFILL_DAYS     = 'boxtal_backfill_days';

	public const DEFAULT_MAX_RETRY     = 6;
	public const DEFAULT_RETRY_DELAY   = 900;
	public const DEFAULT_BULK_MAX      = 20;
	public const DEFAULT_BACKFILL_DAYS = 180;

	/**
	 * Correspondance clé de réglage → constante héritée du snippet WPCode.
	 *
	 * `KEY_BACKFILL_DAYS` n'a volontairement pas d'entrée : c'est un réglage
	 * nouveau, sans équivalent dans les snippets remplacés.
	 *
	 * @var array<string, string>
	 */
	private const CONSTANT_MAP = array(
		self::KEY_PREFER_LINK       => 'MH_BXT_PREFER_BOXTAL_LINK',
		self::KEY_STATUS_SHIPPED    => 'MH_BXT_STATUS_SHIPPED',
		self::KEY_GUESS_FROM_NUMBER => 'MH_BXT_GUESS_FROM_NUMBER',
		self::KEY_DEBUG             => 'MH_BXT_DEBUG',
		self::KEY_MAX_RETRY         => 'MH_BXT_MAX_RETRY',
		self::KEY_RETRY_DELAY       => 'MH_BXT_RETRY_DELAY',
		self::KEY_BULK_MAX          => 'MH_BXT_BULK_MAX',
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
	 * Préférer le lien de suivi renvoyé par Boxtal à celui construit par AST.
	 *
	 * @return bool
	 */
	public static function prefer_boxtal_link(): bool {
		return self::resolve_bool( self::KEY_PREFER_LINK, true );
	}

	/**
	 * Faire passer la commande au statut « expédié » côté AST à l'ajout du suivi.
	 *
	 * @return bool
	 */
	public static function status_shipped(): bool {
		return self::resolve_bool( self::KEY_STATUS_SHIPPED, false );
	}

	/**
	 * Autoriser la déduction du transporteur depuis le format du numéro de suivi
	 * quand aucun autre signal n'a permis de le déterminer.
	 *
	 * @return bool
	 */
	public static function guess_from_number(): bool {
		return self::resolve_bool( self::KEY_GUESS_FROM_NUMBER, true );
	}

	/**
	 * Journaliser la réponse brute de l'API Boxtal.
	 *
	 * @return bool
	 */
	public static function debug(): bool {
		return self::resolve_bool( self::KEY_DEBUG, false );
	}

	/**
	 * Nombre maximal de relances si l'API Boxtal n'a pas encore le colis.
	 *
	 * @return int
	 */
	public static function max_retry(): int {
		return self::resolve_int( self::KEY_MAX_RETRY, self::DEFAULT_MAX_RETRY );
	}

	/**
	 * Délai entre deux relances, en secondes.
	 *
	 * @return int
	 */
	public static function retry_delay(): int {
		return self::resolve_int( self::KEY_RETRY_DELAY, self::DEFAULT_RETRY_DELAY );
	}

	/**
	 * Plafond de commandes traitées par l'action groupée de la liste des
	 * commandes. Chaque commande y coûte un appel HTTP bloquant vers Boxtal.
	 *
	 * @return int
	 */
	public static function bulk_max(): int {
		return self::resolve_int( self::KEY_BULK_MAX, self::DEFAULT_BULK_MAX );
	}

	/**
	 * Ancienneté maximale (en jours) des commandes candidates au rattrapage
	 * automatique. `0` désactive la limite.
	 *
	 * @return int
	 */
	public static function backfill_days(): int {
		return self::resolve_int( self::KEY_BACKFILL_DAYS, self::DEFAULT_BACKFILL_DAYS );
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
