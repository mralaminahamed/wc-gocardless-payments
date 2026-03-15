<?php
/**
 * Plugin Name:       WooCommerce GoCardless Payments
 * Plugin URI:        https://github.com/mralaminahamed/wc-gocardless-payments
 * Description:       A production-ready WooCommerce payment gateway integrating GoCardless for Direct Debit (ACH/BACS/SEPA), Instant Bank Pay, Variable Recurring Payments (VRP), and Payment Intentions.
 * Version:           1.0.0
 * Author:            Al Amin Ahamed
 * Author URI:        https://github.com/mralaminahamed
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-gocardless-payments
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 *
 * @package WC_GoCardless_Payments
 * @wordpress-plugin
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'WC_GOCARDLESS_VERSION', '1.0.0' );
define( 'WC_GOCARDLESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GOCARDLESS_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_GOCARDLESS_FILE', __FILE__ );
define( 'WC_GOCARDLESS_MIN_WC_VERSION', '8.0' );
define( 'WC_GOCARDLESS_MIN_PHP_VERSION', '8.0' );

require_once __DIR__ . '/vendor/autoload.php';

// Declare HPOS (High-Performance Order Storage) compatibility.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);

			// Declare Cart & Checkout Blocks compatibility.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Initialise the plugin after all plugins have loaded.
 *
 * We hook into `plugins_loaded` to guarantee WooCommerce is available
 * before attempting to extend its classes.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_init(): void {
	// PHP version gate.
	if ( version_compare( PHP_VERSION, WC_GOCARDLESS_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'wc_gocardless_payments_php_version_notice' );
		return;
	}

	// WooCommerce availability gate.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_gocardless_payments_woocommerce_missing_notice' );
		return;
	}

	// WooCommerce version gate.
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WC_GOCARDLESS_MIN_WC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'wc_gocardless_payments_woocommerce_version_notice' );
		return;
	}

	// Load plugin text domain for translations.
	load_plugin_textdomain(
		'wc-gocardless-payments',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Autoload all plugin classes.
	// wc_gocardless_payments_autoload();

	// Boot the core plugin singleton.
	WC_GoCardless_Payments::instance();
}
add_action( 'plugins_loaded', 'wc_gocardless_payments_init' );

/**
 * Admin notice: PHP version requirement not met.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_php_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		wp_kses_post(
			sprintf(
			/* translators: 1: Required PHP version 2: Current PHP version */
				__( '<strong>WooCommerce GoCardless Payments</strong> requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade PHP or contact your hosting provider.', 'wc-gocardless-payments' ),
				WC_GOCARDLESS_MIN_PHP_VERSION,
				PHP_VERSION
			)
		)
	);
}

/**
 * Admin notice: WooCommerce is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_woocommerce_missing_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		wp_kses_post(
			__( '<strong>WooCommerce GoCardless Payments</strong> requires WooCommerce to be installed and active.', 'wc-gocardless-payments' )
		)
	);
}

/**
 * Admin notice: WooCommerce version requirement not met.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_woocommerce_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		wp_kses_post(
			sprintf(
			/* translators: 1: Required WooCommerce version 2: Current WooCommerce version */
				__( '<strong>WooCommerce GoCardless Payments</strong> requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'wc-gocardless-payments' ),
				WC_GOCARDLESS_MIN_WC_VERSION,
				defined( 'WC_VERSION' ) ? WC_VERSION : __( 'unknown', 'wc-gocardless-payments' )
			)
		)
	);
}

/**
 * Activation hook handler.
 *
 * Sets a transient to trigger a post-activation admin notice and
 * flushes rewrite rules to register any custom endpoints.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_activate(): void {
	set_transient( 'wc_gocardless_activation_notice', true, 30 );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_gocardless_payments_activate' );

/**
 * Deactivation hook handler.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_gocardless_payments_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_gocardless_payments_deactivate' );
