<?php
/**
 * Core plugin singleton — WC_GoCardless
 *
 * Responsible for bootstrapping all subsystems: registering payment gateways,
 * loading the API client, wiring webhook handlers, and hooking into
 * WooCommerce's lifecycle events.
 *
 * @package WC_GoCardless_Payments
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless
 *
 * Central plugin loader. Instantiated once via ::instance() and stored
 * in a static property. All subsystem classes are initialised here.
 *
 * @since 1.0.0
 */
final class WC_GoCardless_Payments {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Payments|null
	 */
	private static ?WC_GoCardless_Payments $instance = null;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $version = WC_GOCARDLESS_VERSION;

	/**
	 * API client instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_API_Client|null
	 */
	public ?WC_GoCardless_API_Client $api = null;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger|null
	 */
	public ?WC_GoCardless_Logger $logger = null;

	/**
	 * Retrieve or create the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_Payments
	 */
	public static function instance(): WC_GoCardless_Payments {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Convenience accessor for the global plugin instance.
	 *
	 * Equivalent to calling WC_GoCardless::instance() but useful in
	 * contexts where method chaining from the global function is preferred.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_Payments
	 */
	public static function get_instance(): WC_GoCardless_Payments {
		return self::instance();
	}

	/**
	 * Private constructor — use ::instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'wc-gocardless-payments' ), '1.0.0' );
	}

	/**
	 * Unserializing is forbidden.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing is forbidden.', 'wc-gocardless-payments' ), '1.0.0' );
	}

	/**
	 * Initialise all plugin subsystems and register hooks.
	 *
	 * Execution order:
	 *   1. Core utilities (logger, order helper, idempotency).
	 *   2. API client.
	 *   3. Payment gateway registration.
	 *   4. Webhook handler.
	 *   5. Admin UI.
	 *   6. Subscriptions integration (conditional).
	 *   7. Post-activation notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init(): void {
		$this->init_utilities();
		$this->init_api();
		$this->register_gateways();
		$this->init_webhook_handler();
		$this->init_admin();
		$this->init_subscriptions();
		$this->init_emails();
		$this->register_hooks();
	}

	/**
	 * Phase 2 accessor: Billing Requests API endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_API_Billing_Requests
	 */
	public function billing_requests(): WC_GoCardless_API_Billing_Requests {
		return new WC_GoCardless_API_Billing_Requests( $this->api );
	}

	/**
	 * Phase 2 accessor: Mandates API endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_API_Mandates
	 */
	public function mandates(): WC_GoCardless_API_Mandates {
		return new WC_GoCardless_API_Mandates( $this->api );
	}

	/**
	 * Phase 2 accessor: Customers API endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_API_Customers
	 */
	public function customers(): WC_GoCardless_API_Customers {
		return new WC_GoCardless_API_Customers( $this->api );
	}

	/**
	 * Phase 4 accessor: VRP API endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_GoCardless_API_VRP
	 */
	public function vrp(): WC_GoCardless_API_VRP {
		return new WC_GoCardless_API_VRP( $this->api );
	}

	/**
	 * Instantiate core utility classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_utilities(): void {
		$this->logger = new WC_GoCardless_Logger();
	}

	/**
	 * Instantiate the GoCardless API client.
	 *
	 * The client reads its credentials from the gateway settings; we pass
	 * a settings resolver callable to defer option retrieval until after
	 * WooCommerce settings are fully loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_api(): void {
		$this->api = new WC_GoCardless_API_Client();
	}

	/**
	 * Register all GoCardless payment gateways with WooCommerce.
	 *
	 * Each gateway variant is registered as a separate payment method,
	 * allowing merchants to selectively enable individual flows.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_gateways(): void {
		add_filter(
			'woocommerce_payment_gateways',
			array( $this, 'add_payment_gateways' )
		);
	}

	/**
	 * Add GoCardless gateway classes to WooCommerce's gateway registry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string|object> $gateways Registered gateway class names or instances.
	 * @return array<int, string|object> Modified gateway list.
	 */
	public function add_payment_gateways( array $gateways ): array {
		$gateways[] = 'WC_GoCardless_Gateway_Direct_Debit';
		$gateways[] = 'WC_GoCardless_Gateway_Instant_Bank';
		$gateways[] = 'WC_GoCardless_Gateway_VRP';

		return $gateways;
	}

	/**
	 * Initialise the webhook endpoint and event processor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_webhook_handler(): void {
		new WC_GoCardless_Webhook_Handler();
	}

	/**
	 * Initialise admin-facing components when in admin context.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_admin(): void {
		if ( is_admin() ) {
			new WC_GoCardless_Admin();
		}
	}

	/**
	 * Conditionally initialise WooCommerce Subscriptions integration.
	 *
	 * The integration class is only loaded when the Subscriptions plugin
	 * (either Automattic's or a compatible variant) is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_subscriptions(): void {
		if ( $this->is_subscriptions_active() ) {
			new WC_GoCardless_Subscriptions();
		}
	}

	/**
	 * Register GoCardless email notification classes with WooCommerce.
	 *
	 * Hooks into `woocommerce_email_classes` to inject the mandate confirmation
	 * email into WooCommerce's email management system, making it configurable
	 * via WooCommerce → Settings → Emails.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_emails(): void {
		add_filter(
			'woocommerce_email_classes',
			array( $this, 'register_email_classes' )
		);
	}

	/**
	 * Add GoCardless email classes to the WooCommerce email registry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, WC_Email> $email_classes Registered email class instances.
	 * @return array<string, WC_Email> Modified email class list.
	 */
	public function register_email_classes( array $email_classes ): array {
		$email_classes['WC_GoCardless_Email_Mandate_Confirmed'] = new WC_GoCardless_Email_Mandate_Confirmed();

		return $email_classes;
	}

	/**
	 * Register miscellaneous plugin-level hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Post-activation admin notice.
		add_action( 'admin_notices', array( $this, 'maybe_show_activation_notice' ) );

		// Register plugin action links on the plugins screen.
		add_filter(
			'plugin_action_links_' . plugin_basename( WC_GOCARDLESS_FILE ),
			array( $this, 'plugin_action_links' )
		);

		// Enqueue frontend assets on checkout pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Display a one-time admin notice after plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_show_activation_notice(): void {
		if ( ! get_transient( 'wc_gocardless_activation_notice' ) ) {
			return;
		}

		delete_transient( 'wc_gocardless_activation_notice' );

		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gocardless_direct_debit' );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			wp_kses_post(
				sprintf(
				/* translators: %s: Settings page URL */
					__( '<strong>WooCommerce GoCardless Payments</strong> is active. <a href="%s">Configure your settings</a> to get started.', 'wc-gocardless-payments' ),
					esc_url( $settings_url )
				)
			)
		);
	}

	/**
	 * Add Settings and Documentation links to the plugin row on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Existing plugin action links.
	 * @return array<int, string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$plugin_links = array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gocardless_direct_debit' ) ),
				esc_html__( 'Settings', 'wc-gocardless-payments' )
			),
			'docs'     => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://developer.gocardless.com/api-reference/' ),
				esc_html__( 'API Docs', 'wc-gocardless-payments' )
			),
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Enqueue frontend JavaScript and CSS assets on checkout pages.
	 *
	 * Scripts are only enqueued when a GoCardless gateway is available and enabled,
	 * preventing unnecessary asset loading on non-checkout pages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! is_checkout() && ! is_wc_endpoint_url() ) {
			return;
		}

		wp_register_script(
			'wc-gocardless-checkout',
			WC_GOCARDLESS_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-checkout' ),
			WC_GOCARDLESS_VERSION,
			true
		);

		wp_register_style(
			'wc-gocardless-checkout',
			WC_GOCARDLESS_URL . 'assets/css/checkout.css',
			array(),
			WC_GOCARDLESS_VERSION
		);

		// Localise the script with checkout-specific data.
		wp_localize_script(
			'wc-gocardless-checkout',
			'wcGoCardlessParams',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wc-gocardless-checkout' ),
				'return_url' => wc_get_checkout_url(),
				'i18n'       => array(
					'processing' => __( 'Processing payment…', 'wc-gocardless-payments' ),
					'error'      => __( 'An error occurred. Please try again.', 'wc-gocardless-payments' ),
				),
			)
		);

		wp_enqueue_script( 'wc-gocardless-checkout' );
		wp_enqueue_style( 'wc-gocardless-checkout' );
	}

	/**
	 * Determine whether a WooCommerce Subscriptions-compatible plugin is active.
	 *
	 * Supports both Automattic's WooCommerce Subscriptions and any third-party
	 * plugin that exposes the `WC_Subscriptions` class.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a compatible subscriptions plugin is active.
	 */
	public function is_subscriptions_active(): bool {
		return class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Subscriptions_Core_Plugin' );
	}
}

/**
 * Global accessor function for the WC_GoCardless singleton.
 *
 * Mirrors WooCommerce's own `WC()` pattern for ergonomic access throughout
 * the codebase:  wc_gocardless_payments()->api->payments->create( ... )
 *
 * @since 1.0.0
 *
 * @return WC_GoCardless_Payments
 */
function wc_gocardless_payments(): WC_GoCardless_Payments {
	return WC_GoCardless_Payments::instance();
}
