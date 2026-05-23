<?php
/**
 * Uninstall hook for AR Design DPD for WooCommerce.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Odstranit pouze interní pomocné runtime stavy.
delete_option( 'ar_design_dpd_energy_surcharge_monitor_state' );
delete_option( 'ard_dpd_statusdata_processed_files' );
delete_option( 'ard_dpd_statusdata_remote_downloads' );

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'ard_dpd_tracking_sync_event' );
	wp_clear_scheduled_hook( 'ard_dpd_energy_surcharge_sync_event' );
}

// WooCommerce nastavení dopravy, exportní konfigurace a order meta ponecháváme zachované,
// dokud nebude definovaná detailní retenční politika.
