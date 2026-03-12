<?php
/**
 * Variable Recurring Payments Gateway — WC_GoCardless_Gateway_VRP
 *
 * Implements GoCardless Variable Recurring Payments (VRP) for WooCommerce.
 *
 * VRP is a GoCardless open-banking product allowing merchants to collect
 * variable amounts on demand against a pre-authorised consent, without
 * the 3–5 day Direct Debit clearing delay. VRP uses Faster Payments rails
 * and is currently available for UK merchants only.
 *
 * Initial checkout flow:
 *   1. process_payment() → creates a VRP consent Billing Request
 *      (mandate_request with scheme=faster_payments + vrp_constraints)
 *   2. Creates Billing Request Flow → obtains authorisation_url
 *   3. Stores billing_request_id + payment_type=vrp on order
 *   4. Redirects customer to GoCardless hosted open-banking consent page
 *   5. Customer authenticates with bank and approves VRP consent
 *   6. GoCardless redirects to WC_GoCardless_Redirect return handler
 *   7. Return handler stores mandate_id (VRP consent) on order; sets on-hold
 *   8. If initial payment also requested: webhook payment.confirmed completes
 *
 * Subscription renewal flow (handled by WC_GoCardless_Renewal_Handler):
 *   1. WC Subscriptions fires scheduled_subscription_payment_gocardless_vrp
 *   2. Renewal handler retrieves consent ID from parent order
 *   3. Verifies consent is active and amount is within constraints
 *   4. Creates payment via WC_GoCardless_API_VRP::create_payment()
 *   5. Webhook payment.confirmed transitions the renewal order to processing
 *
 * Key differences from Direct Debit:
 *   - Scheme: faster_payments (open banking) vs bacs_debit/sepa_core/ach
 *   - VRP constraints: max_amount_per_payment, periodic_limits
 *   - UK-only availability
 *   - Faster settlement (open banking vs traditional ACH/BACS)
 *   - No tokenization — consent stored as mandate_id order meta
 *   - Refunds supported via standard payments API
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_VRP
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_VRP extends WC_GoCardless_Gateway {

	/**
	 * Define gateway-specific properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_vrp';
		$this->method_title       = __( 'GoCardless — Variable Recurring Payments', 'wc-gocardless-payments' );
		$this->method_description = __( 'Variable Recurring Payments (VRP) via GoCardless open banking. Customers authorise a consent for flexible subscription amounts — UK only (Faster Payments).', 'wc-gocardless-payments' );
		$this->has_fields         = false;

		$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'multiple_subscriptions',
		);
	}

	/**
	 * Constructor — parent bootstrap + redirect handler and thank-you hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();

		// Redirect handler shared with DD and IBP gateways.
		if ( ! has_action( 'woocommerce_api_' . WC_GoCardless_Redirect::RETURN_ENDPOINT ) ) {
			new WC_GoCardless_Redirect();
		}

		// Thank-you page notice for VRP consent pending/confirmed.
		add_action(
			'woocommerce_thankyou_' . $this->id,
			array( $this, 'render_order_received_notice' )
		);

		// Register checkout display helper once (may already be registered by DD).
		if ( ! has_filter( 'woocommerce_payment_token_class',
			array( new WC_GoCardless_Checkout(), 'register_token_class' ) )
		) {
			new WC_GoCardless_Checkout();
		}
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
			'vrp_section'              => array(
				'title'       => __( 'Variable Recurring Payment Settings', 'wc-gocardless-payments' ),
				'type'        => 'title',
				'description' => __( 'VRP is available for UK merchants only, using Faster Payments open-banking rails. Ensure your GoCardless account has VRP enabled.', 'wc-gocardless-payments' ),
			),
			'max_amount_per_payment'   => array(
				'title'       => __( 'Maximum Amount per Payment (£)', 'wc-gocardless-payments' ),
				'type'        => 'number',
				'description' => __( 'Maximum single payment amount the VRP consent authorises (in £). GoCardless will reject payments over this limit.', 'wc-gocardless-payments' ),
				'default'     => '500',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'max_amount_per_month'     => array(
				'title'       => __( 'Maximum Amount per Month (£)', 'wc-gocardless-payments' ),
				'type'        => 'number',
				'description' => __( 'Maximum total amount collectable per calendar month under the VRP consent (in £).', 'wc-gocardless-payments' ),
				'default'     => '2000',
				'desc_tip'    => true,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'collect_initial_payment'  => array(
				'title'       => __( 'Collect Initial Payment', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Collect the initial order payment alongside the VRP consent (combined flow)', 'wc-gocardless-payments' ),
				'default'     => 'yes',
				'description' => __( 'When enabled, the first payment is collected during the consent authorisation flow. When disabled, only the consent is collected and the first payment is charged separately.', 'wc-gocardless-payments' ),
			),
			'statement_descriptor'     => array(
				'title'       => __( 'Payment Reference', 'wc-gocardless-payments' ),
				'type'        => 'text',
				'description' => __( 'Reference shown in the customer\'s banking app (max 18 characters for Faster Payments).', 'wc-gocardless-payments' ),
				'default'     => substr( get_bloginfo( 'name' ), 0, 18 ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Render payment fields on the WooCommerce checkout page.
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

		echo '<div class="wc-gocardless-payment-box wc-gocardless-vrp-box">';

		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		echo '<p class="wc-gocardless-redirect-notice">';
		echo esc_html__(
			'You will be redirected to authorise a Variable Recurring Payment consent with your bank via GoCardless.',
			'wc-gocardless-payments'
		);
		echo '</p>';

		// Render VRP constraint summary for customer transparency.
		$this->render_vrp_consent_summary();

		echo '</div>';
	}

	/**
	 * Render a summary of the VRP consent constraints for the customer.
	 *
	 * Transparency about consent limits is a regulatory requirement in UK
	 * open banking. This renders a brief summary of what the customer is
	 * authorising.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_vrp_consent_summary(): void {
		$max_per_payment = (float) $this->get_option( 'max_amount_per_payment', '500' );
		$max_per_month   = (float) $this->get_option( 'max_amount_per_month', '2000' );

		echo '<p class="wc-gocardless-vrp-consent-summary">';
		printf(
		/* translators: 1: Max per payment 2: Max per month */
			esc_html__( 'Consent limits: up to %1$s per payment, up to %2$s per month.', 'wc-gocardless-payments' ),
			wp_kses_post( wc_price( $max_per_payment ) ),
			wp_kses_post( wc_price( $max_per_month ) )
		);
		echo '</p>';
	}

	/**
	 * Process a payment for a given WooCommerce order via VRP consent flow.
	 *
	 * Creates a VRP consent Billing Request, generates the hosted flow URL,
	 * stores the billing_request_id on the order, then redirects to GoCardless.
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
			return array( 'result' => 'failure', 'redirect' => '' );
		}

		$this->logger->info(
			sprintf(
				'[VRP] Processing order #%d — %s %s',
				$order_id,
				$order->get_total(),
				$order->get_currency()
			)
		);

		try {
			return $this->create_vrp_consent_flow( $order );
		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[VRP] API error for order #%d [%s]: %s',
					$order_id,
					$e->get_error_type(),
					$e->getMessage()
				)
			);

			wc_add_notice(
				sprintf(
				/* translators: %s: Error message */
					__( 'Variable Recurring Payment error: %s', 'wc-gocardless-payments' ),
					esc_html( $e->getMessage() )
				),
				'error'
			);

			return array( 'result' => 'failure', 'redirect' => '' );
		}
	}

	/**
	 * Create the VRP consent Billing Request and hosted flow redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, string> Payment result with GoCardless flow URL.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	private function create_vrp_consent_flow( WC_Order $order ): array {
		$vrp_api         = new WC_GoCardless_API_VRP( wc_gocardless_payments()->api );
		$billing_req_api = new WC_GoCardless_API_Billing_Requests( $this->api );
		$idempotency     = new WC_GoCardless_Idempotency();
		$idempotency_key = $idempotency->get_or_create_for_order( $order, 'vrp_consent' );

		$currency         = $order->get_currency();
		$max_per_payment  = (int) round( (float) $this->get_option( 'max_amount_per_payment', '500' ) * 100 );
		$max_per_month    = (int) round( (float) $this->get_option( 'max_amount_per_month', '2000' ) * 100 );

		$args = array(
			'max_amount_per_payment' => $max_per_payment,
			'currency'               => $currency,
			'periodic_limits'        => array(
				array(
					'period'           => 'month',
					'max_total_amount' => $max_per_month,
				),
			),
			'given_name'             => $order->get_billing_first_name(),
			'family_name'            => $order->get_billing_last_name(),
			'email'                  => $order->get_billing_email(),
			'address_line1'          => $order->get_billing_address_1(),
			'city'                   => $order->get_billing_city(),
			'postal_code'            => $order->get_billing_postcode(),
			'country_code'           => $order->get_billing_country(),
			'wc_order_id'            => (string) $order->get_id(),
			'wc_subscription_id'     => $this->get_subscription_id( $order ),
			'idempotency_key'        => $idempotency_key,
		);

		$response           = $vrp_api->create_consent_billing_request( $args );
		$billing_request    = $response['billing_requests'] ?? array();
		$billing_request_id = $billing_request['id'] ?? '';

		if ( empty( $billing_request_id ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'GoCardless returned an invalid VRP Billing Request response.', 'wc-gocardless-payments' ),
				'invalid_response'
			);
		}

		// Persist Billing Request ID, consent metadata, and payment type.
		WC_GoCardless_Order_Helper::bulk_update_meta(
			$order,
			array(
				'billing_request_id' => $billing_request_id,
				'payment_type'       => 'vrp',
				'vrp_max_amount'     => $max_per_payment,
			)
		);

		// Build the CSRF-protected return URL.
		$return_url = WC_GoCardless_Redirect::get_return_url( $order, $billing_request_id );

		// Create the Billing Request Flow.
		$flow_response = $billing_req_api->create_flow(
			$billing_request_id,
			$return_url,
			$return_url
		);

		$authorisation_url = $flow_response['billing_request_flows']['authorisation_url'] ?? '';

		if ( empty( $authorisation_url ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'Failed to obtain GoCardless VRP authorisation URL.', 'wc-gocardless-payments' ),
				'missing_authorisation_url'
			);
		}

		$order->update_status(
			'pending',
			sprintf(
			/* translators: %s: Billing Request ID */
				__( 'VRP consent Billing Request created (ID: %s). Customer redirected to GoCardless for consent authorisation.', 'wc-gocardless-payments' ),
				esc_html( $billing_request_id )
			)
		);

		if ( $this->order_contains_only_virtual( $order ) ) {
			wc_reduce_stock_levels( $order->get_id() );
		}

		WC()->cart->empty_cart();

		$this->logger->info(
			sprintf(
				'[VRP] Order #%d — VRP consent Billing Request %s created. Redirecting to GoCardless.',
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
	 * Render the VRP consent status notice on the WooCommerce thank-you page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function render_order_received_notice( int $order_id ): void {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order || ! $order->has_status( array( 'on-hold', 'pending', 'processing', 'completed' ) ) ) {
			return;
		}

		$mandate_id = WC_GoCardless_Order_Helper::get_mandate_id( $order );

		if ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			echo '<div class="wc-gocardless-pending-notice">';
			echo '<p><strong>' . esc_html__( 'VRP Consent Authorised', 'wc-gocardless-payments' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Your Variable Recurring Payment consent has been authorised. Your subscription will be processed automatically on each renewal date.', 'wc-gocardless-payments' ) . '</p>';

			if ( $mandate_id ) {
				echo '<p><small>' . sprintf(
					/* translators: %s: Consent ID */
						esc_html__( 'Consent reference: %s', 'wc-gocardless-payments' ),
						'<code>' . esc_html( $mandate_id ) . '</code>'
					) . '</small></p>';
			}

			echo '</div>';
		}
	}

	/**
	 * Retrieve the WooCommerce subscription ID associated with an order.
	 *
	 * Returns empty string for non-subscription orders or when
	 * WooCommerce Subscriptions is not active.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Subscription ID or empty string.
	 */
	private function get_subscription_id( WC_Order $order ): string {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return '';
		}

		$subscriptions = wcs_get_subscriptions_for_order( $order );

		foreach ( $subscriptions as $subscription ) {
			return (string) $subscription->get_id();
		}

		return '';
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
	 * Return the default customer-facing payment method title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_title(): string {
		return __( 'Variable Recurring Payment', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing payment method description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Flexible recurring bank payments via GoCardless open banking. Authorise once — we collect each subscription renewal automatically.', 'wc-gocardless-payments' );
	}
}
