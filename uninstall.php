<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb, $wp_version;

if ( defined( 'WC_SHIPTASTIC_REMOVE_ALL_DATA' ) && true === WC_SHIPTASTIC_REMOVE_ALL_DATA ) {
	// Delete options.
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'eu_owb_woocommerce\_%';" );

	// Clear any cached data that has been removed
	wp_cache_flush();
}
