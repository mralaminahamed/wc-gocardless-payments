<?php
/**
 * Abstract Base Gateway — WC_GoCardless_Gateway
 *
 * Provides shared functionality for all GoCardless gateway variants:
 * - Settings definition and retrieval
 * - API client access
 * - Shared process_refund() implementation
 * - Order note and status helpers
 * - Shared admin settings fields
 *
 * Concrete subclasses must implement:
 *   - define_gateway_properties()  — set id, title, description, method_title
 *   - get_form_fields()            — define gateway-specific settings fields
 *   - process_payment()            — handle the payment flow
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway
 *
 * @since 1.0.0
 */
abstract class WC_GoCardless_Gateway extends WC_Payment_Gateway {

	/**
	 * Whether the gateway is operating in sandbox/test mode.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	protected bool $sandbox_mode = false;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	protected bool $debug_logging = false;

	/**
	 * GoCardless API client instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_API_Client
	 */
	protected WC_GoCardless_API_Client $api;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger
	 */
	protected WC_GoCardless_Logger $logger;

	/**
	 * Constructor.
	 *
	 * Calls define_gateway_properties() on the subclass, then initialises
	 * form fields, loads settings, and registers shared hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Allow subclasses to set id, title, method_title, etc.
		$this->define_gateway_properties();

		// Standard WooCommerce gateway bootstrap sequence.
		$this->init_form_fields();
		$this->init_settings();

		// Hydrate protected properties from saved settings.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->enabled       = $this->get_option( 'enabled' );
		$this->sandbox_mode  = 'yes' === $this->get_option( 'sandbox_mode', 'yes' );
		$this->debug_logging = 'yes' === $this->get_option( 'debug_logging', 'no' );

		// Retrieve shared services from the plugin singleton.
		$this->api    = wc_gocardless_payments()->api;
		$this->logger = wc_gocardless_payments()->logger;

		// Save settings hook for the admin screen.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		// Refresh API credentials when settings are saved.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this->api, 'refresh_credentials' )
		);
	}

	/**
	 * Define gateway-specific properties.
	 *
	 * Subclasses must set at minimum:
	 *   $this->id
	 *   $this->method_title
	 *   $this->method_description
	 *   $this->has_fields  (bool)
	 *   $this->supports    (array)
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract protected function define_gateway_properties(): void;

	/**
	 * Initialise form fields with both shared and gateway-specific fields.
	 *
	 * Merges shared fields (enabled, title, description, API credentials,
	 * sandbox mode, logging) with fields returned by get_gateway_form_fields().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array_merge(
			$this->get_shared_form_fields(),
			$this->get_gateway_form_fields()
		);
	}

	/**
	 * Return form fields shared across all GoCardless gateway variants.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> WooCommerce settings field definitions.
	 */
	protected function get_shared_form_fields(): array {
		return array(
			'enabled'              => array(
				'title'   => __( 'Enable/Disable', 'wc-gocardless-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this payment method', 'wc-gocardless-payments' ),
				'default' => 'no',
			),
			'title'                => array(
				'title'       => __( 'Title', 'wc-gocardless-payments' ),
				'type'        => 'text',
				'description' => __( 'Payment method title displayed to customers at checkout.', 'wc-gocardless-payments' ),
				'default'     => $this->get_default_title(),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'wc-gocardless-payments' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description displayed to customers at checkout.', 'wc-gocardless-payments' ),
				'default'     => $this->get_default_description(),
			),
			'api_credentials'      => array(
				'title'       => __( 'API Credentials', 'wc-gocardless-payments' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: GoCardless developer portal URL */
					__( 'Obtain your API credentials from the <a href="%s" target="_blank" rel="noopener noreferrer">GoCardless developer portal</a>.', 'wc-gocardless-payments' ),
					'https://manage.gocardless.com/developers'
				),
			),
			'sandbox_mode'         => array(
				'title'       => __( 'Sandbox Mode', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable sandbox (test) mode', 'wc-gocardless-payments' ),
				'default'     => 'yes',
				'description' => __( 'Use the GoCardless sandbox environment for testing. Disable for live transactions.', 'wc-gocardless-payments' ),
			),
			'live_access_token'    => array(
				'title'       => __( 'Live Access Token', 'wc-gocardless-payments' ),
				'type'        => 'password',
				'description' => __( 'Your GoCardless live API access token.', 'wc-gocardless-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'sandbox_access_token' => array(
				'title'       => __( 'Sandbox Access Token', 'wc-gocardless-payments' ),
				'type'        => 'password',
				'description' => __( 'Your GoCardless sandbox API access token for testing.', 'wc-gocardless-payments' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'webhook_secret'       => array(
				'title'       => __( 'Webhook Secret', 'wc-gocardless-payments' ),
				'type'        => 'password',
				'description' => sprintf(
					/* translators: %s: Webhook endpoint URL */
					__( 'Webhook secret from GoCardless. Configure your endpoint at: <code>%s</code>', 'wc-gocardless-payments' ),
					esc_url( home_url( '/wc-api/wc_gocardless_webhook/' ) )
				),
				'default'     => '',
			),
			'advanced'             => array(
				'title' => __( 'Advanced', 'wc-gocardless-payments' ),
				'type'  => 'title',
			),
			'debug_logging'        => array(
				'title'       => __( 'Debug Logging', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable debug logging', 'wc-gocardless-payments' ),
				'default'     => 'no',
				'description' => sprintf(
					/* translators: %s: WooCommerce logs URL */
					__( 'Log plugin events for debugging. <a href="%s">View logs</a>.', 'wc-gocardless-payments' ),
					admin_url( 'admin.php?page=wc-status&tab=logs' )
				),
			),
		);
	}

	/**
	 * Return gateway-specific form fields.
	 *
	 * Subclasses override this to append additional settings fields
	 * specific to their payment flow.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Additional settings fields.
	 */
	protected function get_gateway_form_fields(): array {
		return array();
	}

	/**
	 * Return the default customer-facing title for this gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_title(): string {
		return __( 'Bank Payment (GoCardless)', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing description for this gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Pay securely via bank payment using GoCardless.', 'wc-gocardless-payments' );
	}

	/**
	 * Process a refund for a GoCardless payment.
	 *
	 * Shared across all gateway variants. Delegates to the Payments API
	 * endpoint to create a GoCardless refund object.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $order_id WooCommerce order ID.
	 * @param float|null $amount   Amount to refund (null for full refund).
	 * @param string     $reason   Refund reason.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'invalid_order',
				__( 'Order not found.', 'wc-gocardless-payments' )
			);
		}

		$payment_id = WC_GoCardless_Order_Helper::get_payment_id( $order );

		if ( empty( $payment_id ) ) {
			return new WP_Error(
				'missing_payment_id',
				__( 'No GoCardless payment ID found for this order. Refunds can only be processed for orders paid via GoCardless.', 'wc-gocardless-payments' )
			);
		}

		// Convert amount to pence/cents (GoCardless expects integers in minor units).
		$amount_minor = $this->to_minor_units( $amount ?? (float) $order->get_total(), $order->get_currency() );

		try {
			$refund_api = new WC_GoCardless_API_Payments( $this->api );
			$refund     = $refund_api->create_refund(
				$payment_id,
				$amount_minor,
				$order->get_currency(),
				sanitize_text_field( $reason )
			);

			$this->logger->info(
				sprintf( '[Refund] Created refund %s for order #%d.', $refund['id'] ?? 'unknown', $order_id )
			);

			/* translators: 1: Refund ID 2: Amount with currency */
			$order->add_order_note(
				sprintf(
					__( 'GoCardless refund created (Refund ID: %1$s, Amount: %2$s).', 'wc-gocardless-payments' ),
					esc_html( $refund['id'] ?? '' ),
					wc_price( $amount, array( 'currency' => $order->get_currency() ) )
				)
			);

			return true;

		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf( '[Refund] Failed for order #%d: %s', $order_id, $e->getMessage() )
			);

			return new WP_Error(
				'refund_failed',
				sprintf(
					/* translators: %s: API error message */
					__( 'GoCardless refund failed: %s', 'wc-gocardless-payments' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Determine whether refunds are supported for a given order.
	 *
	 * Refunds require a confirmed GoCardless payment ID on the order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public function can_refund_order( $order_id ): bool {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		return ! empty( WC_GoCardless_Order_Helper::get_payment_id( $order ) );
	}

	/**
	 * Convert a decimal amount to the currency's minor unit (e.g. pence, cents).
	 *
	 * GoCardless expects all monetary values as integers in the currency's
	 * smallest unit (100 = £1.00, 100 = $1.00, 100 = €1.00).
	 *
	 * Zero-decimal currencies (e.g. JPY) are passed through unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $amount   Decimal amount.
	 * @param string $currency ISO 4217 currency code.
	 * @return int Amount in minor units.
	 */
	protected function to_minor_units( float $amount, string $currency ): int {
		$zero_decimal_currencies = array( 'JPY', 'BIF', 'CLP', 'GNF', 'KMF', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal_currencies, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Add an order note and optionally update the order status.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order    $order      WooCommerce order.
	 * @param string      $note       Note content.
	 * @param string|null $new_status Optional new order status (without 'wc-' prefix).
	 * @return void
	 */
	protected function add_order_note( WC_Order $order, string $note, ?string $new_status = null ): void {
		$order->add_order_note( $note );

		if ( null !== $new_status ) {
			$order->update_status( $new_status, $note );
		}
	}

	/**
	 * Determine whether the gateway is currently in sandbox mode.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_sandbox(): bool {
		return $this->sandbox_mode;
	}

	/**
	 * Retrieve the webhook secret for this gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return string Webhook secret, or empty string if not configured.
	 */
	public function get_webhook_secret(): string {
		return (string) $this->get_option( 'webhook_secret', '' );
	}
}
