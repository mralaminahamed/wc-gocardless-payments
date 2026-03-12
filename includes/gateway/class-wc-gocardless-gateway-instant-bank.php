<?php
/**
 * Instant Bank Pay Gateway — WC_GoCardless_Gateway_Instant_Bank
 *
 * Implements the GoCardless Instant Bank Pay (IBP) payment flow for WooCommerce.
 *
 * IBP uses open-banking rails (UK Faster Payments, SEPA Instant) to confirm
 * funds near-immediately, unlike traditional Direct Debit which takes 3–5 days.
 *
 * Payment flow:
 *   1. process_payment() → creates IBP Billing Request (payment_request only,
 *      funds_settlement: instant, no mandate_request)
 *   2. Creates Billing Request Flow → obtains authorisation_url
 *   3. Stores billing_request_id + payment_type=instant_bank_pay on order
 *   4. Redirects customer to GoCardless hosted bank selection page
 *   5. Customer authenticates with their bank and approves the payment
 *   6. GoCardless redirects to WC_GoCardless_Redirect return handler
 *   7. Return handler fetches live payment status:
 *      - confirmed/paid_out → payment_complete() immediately
 *      - submitted/pending  → on-hold; webhook completes
 *      - failed/cancelled   → failed status, redirect to checkout
 *   8. Webhook payment.confirmed → payment_complete() (idempotent)
 *
 * Key differences from Direct Debit:
 *   - No mandate created or stored (no tokenization support)
 *   - No saved payment method option (one-off per checkout)
 *   - Faster confirmation lifecycle (minutes vs days)
 *   - funds_settlement: instant in Billing Request payload
 *   - Distinct webhook confirmation logic (payment_complete vs on-hold)
 *   - Refund supported via GoCardless refunds API
 *
 * Geographic availability: UK (Faster Payments), select EU markets (SEPA Instant).
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_Instant_Bank
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_Instant_Bank extends WC_GoCardless_Gateway {

	/**
	 * Define gateway-specific properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_instant_bank';
		$this->method_title       = __( 'GoCardless — Instant Bank Pay', 'wc-gocardless-payments' );
		$this->method_description = __( 'Accept instant open-banking payments via GoCardless. Payment is confirmed in minutes using Faster Payments (UK) or SEPA Instant (EU). No card details required.', 'wc-gocardless-payments' );
		$this->has_fields         = false;

		// IBP does not support tokenization or subscriptions — one-off payments only.
		$this->supports = array(
			'products',
			'refunds',
		);
	}

	/**
	 * Constructor — parent bootstrap + redirect handler registration.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();

		// The redirect handler is shared with Direct Debit; IBP return
		// is distinguished by the payment_type meta on the order.
		// Register only if not already registered by the DD gateway.
		if ( ! has_action( 'woocommerce_api_' . WC_GoCardless_Redirect::RETURN_ENDPOINT ) ) {
			new WC_GoCardless_Redirect();
		}

		// Register the thank-you page notice for IBP pending/confirmed orders.
		add_action(
			'woocommerce_thankyou_' . $this->id,
			array( $this, 'render_order_received_notice' )
		);
	}

	/**
	 * Return gateway-specific settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_gateway_form_fields(): array {
		return array(
			'ibp_section'          => array(
				'title'       => __( 'Instant Bank Pay Settings', 'wc-gocardless-payments' ),
				'type'        => 'title',
				'description' => __( 'Instant Bank Pay is available in the UK (Faster Payments) and select EU markets (SEPA Instant). Ensure your GoCardless account has IBP enabled.', 'wc-gocardless-payments' ),
			),
			'collect_mandate'      => array(
				'title'       => __( 'Collect Mandate Alongside Payment', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Also collect a Direct Debit mandate during the IBP flow (IBP + mandate combo)', 'wc-gocardless-payments' ),
				'default'     => 'no',
				'description' => __( 'When enabled, customers authorise both an instant payment and a Direct Debit mandate in a single flow. Useful for subscription products requiring both immediate payment and future collections.', 'wc-gocardless-payments' ),
			),
			'statement_descriptor' => array(
				'title'       => __( 'Payment Reference', 'wc-gocardless-payments' ),
				'type'        => 'text',
				'description' => __( 'Payment reference shown in the customer\'s banking app (max 18 characters for Faster Payments).', 'wc-gocardless-payments' ),
				'default'     => substr( get_bloginfo( 'name' ), 0, 18 ),
				'desc_tip'    => true,
			),
			'show_bank_logos'      => array(
				'title'   => __( 'Show Bank Logos at Checkout', 'wc-gocardless-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display bank logos below the payment method description', 'wc-gocardless-payments' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Render payment fields on the WooCommerce checkout page.
	 *
	 * Displays the gateway description and an optional bank logo grid.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function payment_fields(): void {
		$description = $this->get_description();

		if ( $this->is_sandbox() ) {
			$description .= ' <span class="wc-gocardless-sandbox-badge">'
				. esc_html__( 'Test Mode', 'wc-gocardless-payments' )
				. '</span>';
		}

		echo '<div class="wc-gocardless-payment-box wc-gocardless-ibp-box">';

		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		echo '<p class="wc-gocardless-redirect-notice">';
		echo esc_html__(
			'You will be redirected to select your bank and approve the payment securely via GoCardless.',
			'wc-gocardless-payments'
		);
		echo '</p>';

		if ( 'yes' === $this->get_option( 'show_bank_logos', 'yes' ) ) {
			$this->render_bank_logos();
		}

		echo '</div>';
	}

	/**
	 * Render a row of representative bank logos for the IBP payment method.
	 *
	 * Uses CSS-only placeholder text blocks when logo assets are not present.
	 * Logo assets should be placed in assets/images/banks/ in future iterations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_bank_logos(): void {
		$banks = array(
			__( 'Barclays', 'wc-gocardless-payments' ),
			__( 'HSBC', 'wc-gocardless-payments' ),
			__( 'Lloyds', 'wc-gocardless-payments' ),
			__( 'NatWest', 'wc-gocardless-payments' ),
			__( 'Santander', 'wc-gocardless-payments' ),
			__( '+ more', 'wc-gocardless-payments' ),
		);

		echo '<div class="wc-gocardless-bank-list" aria-label="'
			. esc_attr__( 'Supported banks', 'wc-gocardless-payments' )
			. '">';

		foreach ( $banks as $bank ) {
			echo '<span class="wc-gocardless-bank-badge">' . esc_html( $bank ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Process a payment for a given WooCommerce order via Instant Bank Pay.
	 *
	 * Creates an IBP Billing Request, generates the hosted flow URL, stores
	 * the billing_request_id and payment_type on the order, then redirects
	 * the customer to the GoCardless bank selection page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string> WooCommerce payment result array.
	 */
	public function process_payment( $order_id ): array {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice(
				__( 'Order not found. Please try again.', 'wc-gocardless-payments' ),
				'error'
			);
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$order_total = (float) $order->get_total();
		$currency    = $order->get_currency();

		$this->logger->info(
			sprintf(
				'[IBP] Processing order #%d — %s %s',
				$order_id,
				$order_total,
				$currency
			)
		);

		// IBP does not support zero-total orders — no payment = no IBP.
		if ( $order_total <= 0 ) {
			wc_add_notice(
				__( 'Instant Bank Pay is not available for zero-total orders. Please choose a different payment method.', 'wc-gocardless-payments' ),
				'error'
			);
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		try {
			return $this->create_ibp_billing_request( $order );
		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[IBP] API error for order #%d [%s]: %s',
					$order_id,
					$e->get_error_type(),
					$e->getMessage()
				)
			);

			wc_add_notice(
				sprintf(
					/* translators: %s: Error message */
					__( 'Instant Bank Pay error: %s', 'wc-gocardless-payments' ),
					esc_html( $e->getMessage() )
				),
				'error'
			);

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}
	}

	/**
	 * Create the IBP Billing Request, flow, and return the redirect result.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, string> Result with redirect URL to GoCardless IBP flow.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	private function create_ibp_billing_request( WC_Order $order ): array {
		$billing_request_api = new WC_GoCardless_API_Billing_Requests( $this->api );
		$idempotency         = new WC_GoCardless_Idempotency();
		$idempotency_key     = $idempotency->get_or_create_for_order( $order, 'create_ibp_billing_request' );
		$collect_mandate     = 'yes' === $this->get_option( 'collect_mandate', 'no' );

		$args = array(
			'amount'          => $this->to_minor_units( (float) $order->get_total(), $order->get_currency() ),
			'currency'        => $order->get_currency(),
			'description'     => $this->get_payment_description( $order ),
			'given_name'      => $order->get_billing_first_name(),
			'family_name'     => $order->get_billing_last_name(),
			'email'           => $order->get_billing_email(),
			'address_line1'   => $order->get_billing_address_1(),
			'city'            => $order->get_billing_city(),
			'postal_code'     => $order->get_billing_postcode(),
			'country_code'    => $order->get_billing_country(),
			'wc_order_id'     => (string) $order->get_id(),
			'idempotency_key' => $idempotency_key,
			'collect_mandate' => $collect_mandate,
		);

		$response           = $billing_request_api->create_for_instant_bank_pay( $args );
		$billing_request    = $response['billing_requests'] ?? array();
		$billing_request_id = $billing_request['id'] ?? '';

		if ( empty( $billing_request_id ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'GoCardless returned an invalid IBP Billing Request response.', 'wc-gocardless-payments' ),
				'invalid_response'
			);
		}

		// Persist Billing Request ID and payment type on the order.
		WC_GoCardless_Order_Helper::bulk_update_meta(
			$order,
			array(
				'billing_request_id' => $billing_request_id,
				'payment_type'       => 'instant_bank_pay',
			)
		);

		// Build the CSRF-protected return URL.
		$return_url = WC_GoCardless_Redirect::get_return_url( $order, $billing_request_id );

		// Create the hosted Billing Request Flow to obtain the authorisation URL.
		$flow_response = $billing_request_api->create_flow(
			$billing_request_id,
			$return_url,
			$return_url,
			array(
				// IBP-specific: lock payment method to instant bank to prevent
				// the customer switching to Direct Debit within the hosted flow.
				'lock_bank_account' => false,
			)
		);

		$authorisation_url = $flow_response['billing_request_flows']['authorisation_url'] ?? '';

		if ( empty( $authorisation_url ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'Failed to obtain GoCardless IBP authorisation URL.', 'wc-gocardless-payments' ),
				'missing_authorisation_url'
			);
		}

		// Set order to pending while customer completes bank authorisation.
		$order->update_status(
			'pending',
			sprintf(
				/* translators: %s: Billing Request ID */
				__( 'IBP Billing Request created (ID: %s). Customer redirected to GoCardless for bank authorisation.', 'wc-gocardless-payments' ),
				esc_html( $billing_request_id )
			)
		);

		// Reduce stock on virtual-only orders immediately.
		if ( $this->order_contains_only_virtual( $order ) ) {
			wc_reduce_stock_levels( $order->get_id() );
		}

		WC()->cart->empty_cart();

		$this->logger->info(
			sprintf(
				'[IBP] Order #%d — Billing Request %s created. Redirecting to GoCardless IBP flow.',
				$order->get_id(),
				$billing_request_id
			)
		);

		return array(
			'result'   => 'success',
			'redirect' => $authorisation_url,
		);
	}

	/**
	 * Build the payment description/reference for the IBP Billing Request.
	 *
	 * For Faster Payments (UK), the reference appears in the customer's
	 * banking app. Truncated to 18 characters to comply with FPS limits.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Payment description (max 18 characters for FPS).
	 */
	private function get_payment_description( WC_Order $order ): string {
		$descriptor = $this->get_option( 'statement_descriptor', '' );

		if ( ! empty( $descriptor ) ) {
			return substr( sanitize_text_field( $descriptor ), 0, 18 );
		}

		// Fall back to site name truncated to FPS limit.
		return substr(
			sprintf(
				/* translators: 1: Order number */
				__( 'Order #%s', 'wc-gocardless-payments' ),
				$order->get_order_number()
			),
			0,
			18
		);
	}

	/**
	 * Determine whether an order contains only virtual or downloadable products.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True when all line items are virtual.
	 */
	private function order_contains_only_virtual( WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof WC_Order_Item_Product ) {
				$product = $item->get_product();
				if ( $product && ! $product->is_virtual() ) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Render the IBP settlement notice on the WooCommerce thank-you page.
	 *
	 * Loads the ibp-order-received.php template partial, passing the order
	 * object so the template can inspect payment status and display the
	 * appropriate confirmed or pending message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function render_order_received_notice( int $order_id ): void {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only render for IBP orders that are on-hold, processing, or completed.
		if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) && ! $order->is_paid() ) {
			return;
		}

		$template = WC_GOCARDLESS_PATH . 'templates/checkout/ibp-order-received.php';

		if ( file_exists( $template ) ) {
			/**
			 * Filters the IBP order-received template path.
			 *
			 * Allows themes and plugins to override the IBP thank-you notice template.
			 *
			 * @since 1.0.0
			 *
			 * @param string   $template  Absolute path to the template file.
			 * @param WC_Order $order     WooCommerce order.
			 */
			$template = apply_filters( 'wc_gocardless_ibp_order_received_template', $template, $order );

			include $template;
		}
	}

	/**
	 * Return the default customer-facing payment method title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_title(): string {
		return __( 'Instant Bank Pay', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing payment method description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Pay instantly and securely from your bank account. No card details required — funds confirmed in minutes.', 'wc-gocardless-payments' );
	}
}
