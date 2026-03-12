<?php
/**
 * Admin UI — WC_GoCardless_Admin
 *
 * Registers admin-facing components: the top-level settings screen pointer,
 * WooCommerce settings tab integration, and post-save credential validation.
 *
 * @package WC_GoCardless_Payments\Admin
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Admin
 *
 * @since 1.0.0
 */
class WC_GoCardless_Admin {

	/**
	 * Constructor — register admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_sections_checkout', array( $this, 'render_gocardless_section_header' ) );
		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'add_checkout_section' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_credential_warning' ) );
	}

	/**
	 * Enqueue admin JavaScript and CSS on WooCommerce settings pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// Only load on the checkout/payments tab.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( 'checkout' !== $tab ) {
			return;
		}

		wp_enqueue_script(
			'wc-gocardless-admin',
			WC_GOCARDLESS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_GOCARDLESS_VERSION,
			true
		);

		wp_localize_script(
			'wc-gocardless-admin',
			'wcGoCardlessAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wc-gocardless-admin' ),
				'i18n'     => array(
					'test_connection_success' => __( 'Connection successful.', 'wc-gocardless-payments' ),
					'test_connection_failed'  => __( 'Connection failed. Please check your access token.', 'wc-gocardless-payments' ),
				),
			)
		);

		wp_enqueue_style(
			'wc-gocardless-admin',
			WC_GOCARDLESS_URL . 'assets/css/admin.css',
			array(),
			WC_GOCARDLESS_VERSION
		);
	}

	/**
	 * Add a GoCardless section to WooCommerce → Settings → Payments.
	 *
	 * This provides a navigation anchor for the GoCardless gateway group,
	 * even though individual gateways appear in the main gateway list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $sections Existing checkout sections.
	 * @return array<string, string> Modified sections.
	 */
	public function add_checkout_section( array $sections ): array {
		// Insert after existing entries; the individual gateways are auto-listed.
		$sections['gocardless'] = __( 'GoCardless', 'wc-gocardless-payments' );

		return $sections;
	}

	/**
	 * Render a section header when the GoCardless section is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_gocardless_section_header(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : '';

		if ( 'gocardless' !== $current_section ) {
			return;
		}

		echo '<div class="wc-gocardless-section-header">';
		echo '<h2>' . esc_html__( 'GoCardless Payment Methods', 'wc-gocardless-payments' ) . '</h2>';
		echo '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: GoCardless dashboard URL */
				__( 'Configure Direct Debit, Instant Bank Pay, and Variable Recurring Payments via GoCardless. <a href="%s" target="_blank" rel="noopener noreferrer">Visit your GoCardless dashboard</a> to obtain API credentials.', 'wc-gocardless-payments' ),
				'https://manage.gocardless.com'
			)
		) . '</p>';

		// Display sandbox mode indicator.
		$settings   = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );
		$is_sandbox = isset( $settings['sandbox_mode'] ) && 'yes' === $settings['sandbox_mode'];

		if ( $is_sandbox ) {
			echo '<div class="notice notice-warning inline"><p>';
			echo '<strong>' . esc_html__( 'Sandbox Mode Active', 'wc-gocardless-payments' ) . '</strong> ';
			echo esc_html__( 'GoCardless is currently in test mode. No real transactions will be processed.', 'wc-gocardless-payments' );
			echo '</p></div>';
		}

		echo '</div>';
	}

	/**
	 * Display an admin warning when GoCardless is enabled but unconfigured.
	 *
	 * Checks whether a live or sandbox access token is present when the
	 * Direct Debit gateway is enabled, and surfaces a notice if absent.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_show_credential_warning(): void {
		// Only show on WooCommerce admin screens.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'woocommerce' ) ) {
			return;
		}

		$settings   = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );
		$is_enabled = isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
		$is_sandbox = isset( $settings['sandbox_mode'] ) && 'yes' === $settings['sandbox_mode'];

		if ( ! $is_enabled ) {
			return;
		}

		$token_key = $is_sandbox ? 'sandbox_access_token' : 'live_access_token';
		$token     = $settings[ $token_key ] ?? '';

		if ( empty( $token ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				wp_kses_post(
					sprintf(
						/* translators: %s: Settings page URL */
						__( '<strong>WooCommerce GoCardless Payments:</strong> Your gateway is enabled but no %1$s access token is configured. <a href="%2$s">Add your credentials</a>.', 'wc-gocardless-payments' ),
						$is_sandbox ? __( 'sandbox', 'wc-gocardless-payments' ) : __( 'live', 'wc-gocardless-payments' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gocardless_direct_debit' )
					)
				)
			);
		}
	}
}
