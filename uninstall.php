<?php
/**
 * Nettoyage à la suppression du plugin.
 *
 * @package ExtenderForAdvancedShipmentTracking
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Sécurité : on ne supprime rien si la constante ci-dessous est définie dans
 * wp-config.php. Pratique pour conserver les réglages entre deux réinstalls.
 */
if ( defined( 'EAST_KEEP_DATA_ON_UNINSTALL' ) && EAST_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

/**
 * Supprime toutes les options préfixées east_ du site courant.
 */
function east_uninstall_delete_options(): void {
	global $wpdb;

	$delete_data = 'yes' === get_option( 'east_delete_data_on_uninstall', 'no' );

	if ( ! $delete_data ) {
		// Rien de spécifique tant qu'aucun module ne pose de données propres :
		// seules les options ci-dessous, sans valeur hors réinstallation, sont
		// purgées inconditionnellement.
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- désinstallation ponctuelle, pas de cache pertinent.
	$option_names = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'east\\_%'"
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	foreach ( (array) $option_names as $option_name ) {
		delete_option( $option_name );
	}

	wp_clear_scheduled_hook( 'east_daily_maintenance' );

	/*
	 * Résidus de la bibliothèque de mise à jour. Elle nettoie normalement elle-même
	 * sur l'action `uninstall_{plugin}`, mais la présence de ce fichier uninstall.php
	 * court-circuite cette action : le ménage doit donc être fait ici.
	 */
	$puc_slug = 'extender-advanced-shipment-tracking';

	delete_option( 'external_updates-' . $puc_slug );
	delete_site_option( 'external_updates-' . $puc_slug );
	wp_clear_scheduled_hook( 'puc_cron_check_updates-' . $puc_slug );

	/*
	 * Données métier propres aux modules : rien pour l'instant. Chaque module qui
	 * pose des options, métas ou tables propres doit ajouter ici sa purge,
	 * conditionnée à `$delete_data` comme les autres plugins de la suite.
	 */
	unset( $delete_data );
}

if ( is_multisite() ) {
	// 'number' => 0 : sans quoi get_sites() s'arrête aux 100 premiers sites et
	// le reste du réseau garderait ses données en base, silencieusement.
	$east_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $east_site_ids as $east_site_id ) {
		switch_to_blog( (int) $east_site_id );
		east_uninstall_delete_options();
		restore_current_blog();
	}
} else {
	east_uninstall_delete_options();
}
