<?php
/**
 * Plugin Uninstall Handler
 *
 * Executed when the plugin is deleted from the WordPress admin.
 * Removes all plugin-owned options and scheduled actions.
 *
 * NOTE: Order meta (mandate IDs, payment IDs) is intentionally preserved
 * on uninstall to maintain financial audit records. Only plugin settings
 * and transients are removed.
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove gateway settings for all variants.
$gateway_option_keys = array(
	'woocommerce_gocardless_direct_debit_settings',
	'woocommerce_gocardless_instant_bank_settings',
	'woocommerce_gocardless_vrp_settings',
);

foreach ( $gateway_option_keys as $option_key ) {
	delete_option( $option_key );
}

// Remove email notification settings (Phase 5).
delete_option( 'woocommerce_wc_gocardless_mandate_confirmed_settings' );

// Remove any plugin transients.
delete_transient( 'wc_gocardless_activation_notice' );

// Clear any scheduled WooCommerce Action Scheduler tasks.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wc_gocardless_process_renewal' );
}
