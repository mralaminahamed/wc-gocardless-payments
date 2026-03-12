<?php

define( 'TEST_WC_GOCARDLESS_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'TEST_WC_DIR', dirname( TEST_WC_GOCARDLESS_PLUGIN_DIR, 1 ) . '/woocommerce' );
define( 'TEST_WC_GOCARDLESS_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/wc-gocardless-payments.php' );

require_once TEST_WC_GOCARDLESS_PLUGIN_DIR . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : getenv( 'WP_PHPUNIT__DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

define( 'WP_TESTS_DIR', $_tests_dir );

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

function wc_gocardless_payments_truncate_table_data(): void {
	$tables = [
		'warranty_cart_orders',
		'warranty_cart_products',
	];
	global $wpdb;
	foreach ( $tables as $table_name ) {
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$table_name}" );
	}
}

function _manually_load_plugins() {
	require dirname( TEST_WC_GOCARDLESS_PLUGIN_DIR ) . '/woocommerce/woocommerce.php';
	require TEST_WC_GOCARDLESS_PLUGIN_DIR . '/wc-gocardless-payments.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugins' );

function install_wc() {
	define( 'WP_UNINSTALL_PLUGIN', true );
	define( 'WC_REMOVE_ALL_DATA', true );

	include TEST_WC_DIR . '/uninstall.php';

	WC_Install::install();

	if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
		$GLOBALS['wp_roles']->reinit();
	} else {
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}

	echo esc_html( 'Installing WooCommerce...' . PHP_EOL );
}

function install_wc_gocardless_payments() {
	echo 'Installing Warranty Cart...' . PHP_EOL;
	wc_gocardless_payments_truncate_table_data();

	wc_gocardless_payments_activate();
}

tests_add_filter( 'setup_theme', 'install_wc' );
tests_add_filter( 'setup_theme', 'install_wc_gocardless_payments' );

require $_tests_dir . '/includes/bootstrap.php';
