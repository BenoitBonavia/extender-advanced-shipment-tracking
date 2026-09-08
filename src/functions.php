<?php
/**
 * Helpers globaux du plugin.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'east' ) ) {
	/**
	 * Accesseur global à l'instance du plugin.
	 *
	 * @return \EAST\Plugin
	 */
	function east(): \EAST\Plugin {
		return \EAST\Plugin::instance();
	}
}

if ( ! function_exists( 'east_log' ) ) {
	/**
	 * Raccourci de journalisation.
	 *
	 * @param string $message Message.
	 * @param string $level   Niveau PSR-3.
	 * @param array  $context Contexte additionnel.
	 */
	function east_log( string $message, string $level = 'info', array $context = array() ): void {
		\EAST\Support\Logger::log( $message, $level, $context );
	}
}
