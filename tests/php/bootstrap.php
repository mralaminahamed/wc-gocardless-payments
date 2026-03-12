<?php
/**
 * Bootstrap file for PHPUnit tests.
 *
 * Sets up Brain Monkey for WordPress function mocking.
 *
 * @package WC_GoCardless_Payments
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'WPINC', 'wp-includes' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    require_once $_tests_dir . '/includes/functions.php';
}

function _manually_load_plugin() {
    define( 'WC_GOCARDLESS_VERSION', '1.0.0' );
    define( 'WC_GOCARDLESS_PATH', __DIR__ . '/../' );
    define( 'WC_GOCARDLESS_URL', 'http://example.org/wp-content/plugins/wc-gocardless-payments' );
    define( 'WC_GOCARDLESS_FILE', __DIR__ . '/../wc-gocardless-payments.php' );
    define( 'WC_GOCARDLESS_MIN_WC_VERSION', '8.0' );
    define( 'WC_GOCARDLESS_MIN_PHP_VERSION', '7.4' );

    require_once __DIR__ . '/../vendor/autoload.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once $_tests_dir . '/includes/bootstrap.php';

unset( $_tests_dir );
