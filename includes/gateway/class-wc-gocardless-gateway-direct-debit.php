<?php
/**
 * Direct Debit Gateway — WC_GoCardless_Gateway_Direct_Debit
 *
 * Implements the GoCardless Direct Debit payment flow for WooCommerce.
 *
 * New order flow:
 *   1. process_payment() → creates Billing Request (mandate + payment)
 *   2. Creates Billing Request Flow → obtains authorisation_url
 *   3. Stores billing_request_id on order
 *   4. Redirects customer to GoCardless hosted authorisation page
 *   5. Customer completes mandate authorisation
 *   6. GoCardless redirects to WC_GoCardless_Redirect return handler
 *   7. Return handler stores mandate_id, payment_id; sets order to on-hold
 *   8. Webhook event payment.confirmed → transitions order to processing
 *
 * Saved mandate (returning customer) flow:
 *   1. process_payment() → retrieves saved WC_Payment_Token for mandate_id
 *   2. Creates GoCardless payment directly against existing mandate
 *   3. Sets order to on-hold; webhook completes confirmation
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_Direct_Debit
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_Direct_Debit extends WC_GoCardless_Gateway {

	/**
	 * Define gateway-specific properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_direct_debit';
		$this->method_title       = __( 'GoCardless — Direct Debit', 'wc-gocardless-payments' );
		$this->method_description = __( 'Accept Direct Debit payments via GoCardless (BACS, SEPA, ACH). Customers authorise a mandate and payments are collected automatically.', 'wc-gocardless-payments' );
		$this->has_fields         = false;

		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'multiple_subscriptions',
			'pre-orders',
		);
	}

	/**
	 * Constructor — parent bootstrap + additional hook registration.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();

		// Register the return handler for Billing Request redirects.
		new WC_GoCardless_Redirect();

		// Register checkout frontend helpers (token class mapping, display filters).
		new WC_GoCardless_Checkout();

		// Mandate invalidation handler (from webhook processor).
		add_action(
			'wc_gocardless_mandate_invalidated',
			array( $this, 'handle_mandate_invalidation' ),
			10,
			3
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
			'direct_debit_section'        => array(
				'title' => __( 'Direct Debit Settings', 'wc-gocardless-payments' ),
				'type'  => 'title',
			),
			'preferred_scheme'            => array(
				'title'       => __( 'Preferred Scheme', 'wc-gocardless-payments' ),
				'type'        => 'select',
				'description' => __( 'Leave blank to let GoCardless auto-detect based on the customer\'s country.', 'wc-gocardless-payments' ),
				'desc_tip'    => true,
				'options'     => array(
					''           => __( 'Auto-detect based on customer country', 'wc-gocardless-payments' ),
					'bacs_debit' => __( 'BACS Direct Debit (UK)', 'wc-gocardless-payments' ),
					'sepa_core'  => __( 'SEPA Core Direct Debit (Eurozone)', 'wc-gocardless-payments' ),
					'ach'        => __( 'ACH Direct Debit (USA)', 'wc-gocardless-payments' ),
					'autogiro'   => __( 'Autogiro (Sweden)', 'wc-gocardless-payments' ),
					'becs'       => __( 'BECS (Australia)', 'wc-gocardless-payments' ),
					'becs_nz'    => __( 'BECS NZ (New Zealand)', 'wc-gocardless-payments' ),
					'pad'        => __( 'PAD (Canada)', 'wc-gocardless-payments' ),
				),
				'default'     => '',
			),
			'statement_descriptor'        => array(
				'title'       => __( 'Statement Descriptor', 'wc-gocardless-payments' ),
				'type'        => 'text',
				'description' => __( 'Text appearing on customer\'s bank statement. Max 10 chars for BACS, 140 for SEPA.', 'wc-gocardless-payments' ),
				'default'     => substr( get_bloginfo( 'name' ), 0, 10 ),
				'desc_tip'    => true,
			),
			'save_mandate'                => array(
				'title'       => __( 'Save Mandate for Future Payments', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Allow customers to save their mandate for faster checkout', 'wc-gocardless-payments' ),
				'default'     => 'yes',
				'description' => __( 'Saved mandates allow repeat customers to pay without re-authorising their bank account.', 'wc-gocardless-payments' ),
			),
			'mandate_only_for_zero_total' => array(
				'title'       => __( 'Mandate-Only for Zero-Total Orders', 'wc-gocardless-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Create mandate without payment for free-trial / £0 orders', 'wc-gocardless-payments' ),
				'default'     => 'yes',
				'description' => __( 'When enabled, orders with a £0 total (e.g. free trials) create a mandate without an immediate charge.', 'wc-gocardless-payments' ),
			),
		);
	}

	/**
	 * Render payment fields on the WooCommerce checkout page.
	 *
	 * Displays saved mandate options for returning customers and the
	 * gateway description with redirect notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function payment_fields(): void {
		if ( is_user_logged_in() && 'yes' === $this->get_option( 'save_mandate', 'yes' ) ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		WC_GoCardless_Checkout::render_payment_fields( $this );

		if ( is_user_logged_in() && 'yes' === $this->get_option( 'save_mandate', 'yes' ) ) {
			$this->save_payment_method_checkbox();
		}
	}

	/**
	 * Process a payment for a given WooCommerce order.
	 *
	 * Routes to the saved-mandate or new Billing Request flow depending
	 * on the customer's selection at checkout.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string> WooCommerce payment result array.
	 */
	public function process_payment( $order_id ): array {
		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found. Please try again.', 'wc-gocardless-payments' ), 'error' );
			return array( 'result' => 'failure', 'redirect' => '' );
		}

		$this->logger->info(
			sprintf(
				'[DirectDebit] Processing order #%d — total: %s %s',
				$order_id,
				$order->get_total(),
				$order->get_currency()
			)
		);

		try {
			// Check for a saved mandate selection.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$wc_token_id = isset( $_POST['wc-gocardless_direct_debit-payment-token'] )
				? absint( wp_unslash( $_POST['wc-gocardless_direct_debit-payment-token'] ) )
				: 0;

			if ( $wc_token_id && 'new' !== (string) $wc_token_id ) {
				return $this->process_with_saved_mandate( $order, $wc_token_id );
			}

			return $this->process_with_billing_request( $order );

		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[DirectDebit] API error for order #%d [%s]: %s',
					$order_id,
					$e->get_error_type(),
					$e->getMessage()
				)
			);

			wc_add_notice(
				sprintf(
					/* translators: %s: API error message */
					__( 'Payment error: %s', 'wc-gocardless-payments' ),
					esc_html( $e->getMessage() )
				),
				'error'
			);

			return array( 'result' => 'failure', 'redirect' => '' );
		}
	}

	/**
	 * Create a Billing Request and redirect the customer to authorise.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, string> Success result with redirect URL.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	private function process_with_billing_request( WC_Order $order ): array {
		$billing_request_api = new WC_GoCardless_API_Billing_Requests( $this->api );
		$idempotency         = new WC_GoCardless_Idempotency();
		$idempotency_key     = $idempotency->get_or_create_for_order( $order, 'create_billing_request' );

		$order_total = (float) $order->get_total();
		$currency    = $order->get_currency();
		$scheme      = $this->get_option( 'preferred_scheme', '' );

		$customer_args = array(
			'given_name'      => $order->get_billing_first_name(),
			'family_name'     => $order->get_billing_last_name(),
			'email'           => $order->get_billing_email(),
			'address_line1'   => $order->get_billing_address_1(),
			'city'            => $order->get_billing_city(),
			'postal_code'     => $order->get_billing_postcode(),
			'country_code'    => $order->get_billing_country(),
			'scheme'          => $scheme,
			'wc_order_id'     => (string) $order->get_id(),
			'idempotency_key' => $idempotency_key,
		);

		// Zero-total: mandate-only (no immediate payment).
		$is_zero_total = $order_total <= 0;
		$mandate_only  = $is_zero_total && 'yes' === $this->get_option( 'mandate_only_for_zero_total', 'yes' );

		if ( $mandate_only ) {
			$this->logger->info(
				sprintf( '[DirectDebit] Zero-total order #%d — mandate-only flow.', $order->get_id() )
			);
			$response = $billing_request_api->create_for_mandate_only( $customer_args );
		} else {
			$customer_args['amount']      = $this->to_minor_units( $order_total, $currency );
			$customer_args['currency']    = $currency;
			$customer_args['description'] = $this->get_payment_description( $order );

			$response = $billing_request_api->create_for_direct_debit( $customer_args );
		}

		$billing_request    = $response['billing_requests'] ?? array();
		$billing_request_id = $billing_request['id'] ?? '';

		if ( empty( $billing_request_id ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'GoCardless returned an invalid Billing Request response.', 'wc-gocardless-payments' ),
				'invalid_response'
			);
		}

		// Persist Billing Request ID for return handler lookup.
		WC_GoCardless_Order_Helper::set_billing_request_id( $order, $billing_request_id );

		// Build return URL with nonce.
		$return_url = WC_GoCardless_Redirect::get_return_url( $order, $billing_request_id );

		// Create the hosted flow — get the authorisation URL.
		$flow_response = $billing_request_api->create_flow(
			$billing_request_id,
			$return_url,
			$return_url
		);

		$authorisation_url = $flow_response['billing_request_flows']['authorisation_url'] ?? '';

		if ( empty( $authorisation_url ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'Failed to obtain GoCardless authorisation URL.', 'wc-gocardless-payments' ),
				'missing_authorisation_url'
			);
		}

		// Set order pending while customer completes authorisation.
		$order->update_status(
			'pending',
			sprintf(
				/* translators: %s: Billing Request ID */
				__( 'Billing Request created (ID: %s). Customer redirected to GoCardless for authorisation.', 'wc-gocardless-payments' ),
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
				'[DirectDebit] Order #%d — Billing Request %s → GoCardless flow created.',
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
	 * Process payment using a previously saved mandate token.
	 *
	 * Directly creates a GoCardless payment against the mandate. No redirect needed.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param int      $wc_token_id WooCommerce payment token ID.
	 * @return array<string, string> Payment result array.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	private function process_with_saved_mandate( WC_Order $order, int $wc_token_id ): array {
		$token = WC_Payment_Tokens::get( $wc_token_id );

		if (
			! $token instanceof WC_GoCardless_Payment_Token_Mandate
			|| (int) $token->get_user_id() !== (int) $order->get_customer_id()
			|| $token->get_gateway_id() !== $this->id
		) {
			wc_add_notice(
				__( 'Invalid saved payment method. Please try again or choose a different method.', 'wc-gocardless-payments' ),
				'error'
			);
			return array( 'result' => 'failure', 'redirect' => '' );
		}

		if ( ! $token->is_active() ) {
			wc_add_notice(
				__( 'This Direct Debit mandate is no longer active. Please set up a new mandate.', 'wc-gocardless-payments' ),
				'error'
			);
			return array( 'result' => 'failure', 'redirect' => '' );
		}

		$mandate_id  = $token->get_mandate_id();
		$order_total = (float) $order->get_total();
		$currency    = $order->get_currency();
		$idempotency = new WC_GoCardless_Idempotency();
		$idempotency_key = $idempotency->get_or_create_for_order( $order, 'create_payment' );

		$this->logger->info(
			sprintf(
				'[DirectDebit] Order #%d — saved mandate %s selected.',
				$order->get_id(),
				$mandate_id
			)
		);

		// Zero-total with a valid saved mandate — complete immediately.
		if ( $order_total <= 0 ) {
			WC_GoCardless_Order_Helper::bulk_update_meta(
				$order,
				array(
					'mandate_id'     => $mandate_id,
					'payment_scheme' => $token->get_scheme(),
				)
			);

			$order->payment_complete();
			$order->add_order_note(
				sprintf(
					/* translators: %s: Mandate ID */
					__( 'Zero-total order completed. Existing mandate: %s.', 'wc-gocardless-payments' ),
					esc_html( $mandate_id )
				)
			);

			WC()->cart->empty_cart();
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			);
		}

		// Create a GoCardless payment against the mandate.
		$payments_api     = new WC_GoCardless_API_Payments( $this->api );
		$payment_response = $payments_api->create(
			$mandate_id,
			$this->to_minor_units( $order_total, $currency ),
			$currency,
			$this->get_payment_description( $order ),
			$idempotency_key
		);

		$payment    = $payment_response['payments'] ?? array();
		$payment_id = $payment['id'] ?? '';

		if ( empty( $payment_id ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'Invalid payment response from GoCardless — missing payment ID.', 'wc-gocardless-payments' ),
				'invalid_response'
			);
		}

		WC_GoCardless_Order_Helper::bulk_update_meta(
			$order,
			array(
				'payment_id'     => $payment_id,
				'mandate_id'     => $mandate_id,
				'payment_scheme' => $token->get_scheme(),
			)
		);

		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: 1: Payment ID 2: Mandate ID */
				__( 'GoCardless payment created (Payment ID: %1$s) against mandate %2$s. Awaiting bank confirmation.', 'wc-gocardless-payments' ),
				esc_html( $payment_id ),
				esc_html( $mandate_id )
			)
		);

		$order->add_payment_token( $token );

		wc_reduce_stock_levels( $order->get_id() );
		WC()->cart->empty_cart();

		$this->logger->info(
			sprintf(
				'[DirectDebit] Order #%d — payment %s created against mandate %s.',
				$order->get_id(),
				$payment_id,
				$mandate_id
			)
		);

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}

	/**
	 * Handle GoCardless mandate invalidation.
	 *
	 * Marks the stored WC Payment Token as inactive and places any active
	 * WooCommerce Subscriptions on-hold when a mandate is cancelled/failed.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $mandate_id GoCardless mandate ID.
	 * @param string   $action     Invalidation reason (cancelled|expired|failed).
	 * @param WC_Order $order      Associated WooCommerce order.
	 * @return void
	 */
	public function handle_mandate_invalidation( string $mandate_id, string $action, WC_Order $order ): void {
		// Update the WC Payment Token status.
		if ( $order->get_customer_id() > 0 ) {
			$tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_customer_id(), $this->id );

			foreach ( $tokens as $token ) {
				if (
					$token instanceof WC_GoCardless_Payment_Token_Mandate
					&& $token->get_mandate_id() === $mandate_id
				) {
					$token->set_status( $action );
					$token->save();

					$this->logger->info(
						sprintf(
							'[DirectDebit] Mandate token %s status updated to "%s".',
							$mandate_id,
							$action
						)
					);
				}
			}
		}

		// Suspend related active WooCommerce Subscriptions.
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->has_status( 'active' ) ) {
					$subscription->update_status(
						'on-hold',
						sprintf(
							/* translators: 1: Mandate ID 2: Action */
							__( 'Subscription placed on-hold: GoCardless mandate %1$s %2$s. New payment method required.', 'wc-gocardless-payments' ),
							esc_html( $mandate_id ),
							esc_html( $action )
						)
					);
				}
			}
		}
	}

	/**
	 * Build the GoCardless payment description for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Payment description (max 140 characters).
	 */
	private function get_payment_description( WC_Order $order ): string {
		$descriptor = $this->get_option( 'statement_descriptor', '' );

		if ( ! empty( $descriptor ) ) {
			return substr( sanitize_text_field( $descriptor ), 0, 140 );
		}

		return substr(
			sprintf(
				/* translators: 1: Site name 2: Order number */
				__( '%1$s — Order #%2$s', 'wc-gocardless-payments' ),
				get_bloginfo( 'name' ),
				$order->get_order_number()
			),
			0,
			140
		);
	}

	/**
	 * Determine whether an order contains only virtual or downloadable products.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True when all items are virtual.
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
		return __( 'Direct Debit', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing payment method description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Pay via Direct Debit. You will be securely redirected to authorise your bank mandate with GoCardless.', 'wc-gocardless-payments' );
	}
}
