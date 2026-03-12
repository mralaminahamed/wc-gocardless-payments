<?php
/**
 * Billing Request Redirect Handler — WC_GoCardless_Redirect
 *
 * Handles the return URL after a customer completes (or abandons) the
 * GoCardless hosted Billing Request authorisation flow.
 *
 * Flow:
 *   1. Customer submits checkout → process_payment() creates Billing Request.
 *   2. Customer is redirected to GoCardless hosted flow URL.
 *   3. Customer completes/cancels authorisation.
 *   4. GoCardless redirects customer to:
 *      {site_url}/wc-api/wc_gocardless_return/?order_id=X&billing_request_id=BRQ&nonce=Y
 *   5. This handler verifies the nonce, fetches the Billing Request status,
 *      and redirects to the appropriate WooCommerce confirmation page.
 *
 * @package WC_GoCardless_Payments\Frontend
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Redirect
 *
 * @since 1.0.0
 */
class WC_GoCardless_Redirect {

	/**
	 * WC API endpoint key for the return URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const RETURN_ENDPOINT = 'wc_gocardless_return';

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger
	 */
	private WC_GoCardless_Logger $logger;

	/**
	 * Constructor — register the WC API return endpoint.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = wc_gocardless()->logger;
		add_action( 'woocommerce_api_' . self::RETURN_ENDPOINT, array( $this, 'handle_return' ) );
	}

	/**
	 * Handle customer return from GoCardless hosted flow.
	 *
	 * Validates the nonce, retrieves the Billing Request status from GoCardless,
	 * and routes the customer to the order received page or failure page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_return(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$order_id           = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$billing_request_id = isset( $_GET['billing_request_id'] )
			? sanitize_text_field( wp_unslash( $_GET['billing_request_id'] ) )
			: '';
		$nonce              = isset( $_GET['nonce'] )
			? sanitize_text_field( wp_unslash( $_GET['nonce'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->logger->info(
			sprintf(
				'[Return] Received return for order #%d, Billing Request: %s',
				$order_id,
				$billing_request_id
			)
		);

		// Validate required parameters.
		if ( ! $order_id || empty( $billing_request_id ) ) {
			$this->logger->warning( '[Return] Missing order_id or billing_request_id.' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Verify the nonce to protect against CSRF on the return URL.
		if ( ! wp_verify_nonce( $nonce, 'wc_gocardless_return_' . $order_id ) ) {
			$this->logger->warning(
				sprintf( '[Return] Invalid nonce for order #%d.', $order_id )
			);
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = WC_GoCardless_Order_Helper::get_order( $order_id );

		if ( ! $order ) {
			$this->logger->error(
				sprintf( '[Return] Order #%d not found.', $order_id )
			);
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// If already processed (e.g. webhook arrived first), redirect to confirmation.
		if ( $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) ) ) {
			wp_safe_redirect( $this->get_success_url( $order ) );
			exit;
		}

		// If order was already cancelled, redirect to checkout with message.
		if ( $order->has_status( 'cancelled' ) ) {
			wc_add_notice(
				__( 'Your payment was cancelled. Please try again or choose a different payment method.', 'wc-gocardless-payments' ),
				'error'
			);
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		try {
			$this->process_billing_request_return( $order, $billing_request_id );
		} catch ( WC_GoCardless_API_Exception $e ) {
			$this->logger->error(
				sprintf(
					'[Return] API error processing return for order #%d: %s',
					$order_id,
					$e->getMessage()
				)
			);

			wc_add_notice(
				__( 'There was an error confirming your payment. Please contact us if payment was deducted.', 'wc-gocardless-payments' ),
				'error'
			);

			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Process the Billing Request return: fetch status and update the order.
	 *
	 * Examines the Billing Request status and takes the appropriate action:
	 *   - fulfilled   → mark on-hold, store mandate/payment IDs, redirect to success
	 *   - cancelled   → cancel order, redirect to checkout with error
	 *   - pending_*   → keep on-hold (webhook will complete), redirect to success
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order              WooCommerce order.
	 * @param string   $billing_request_id GoCardless Billing Request ID.
	 * @return void
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	private function process_billing_request_return( WC_Order $order, string $billing_request_id ): void {
		$billing_request_api = new WC_GoCardless_API_Billing_Requests( wc_gocardless()->api );
		$response            = $billing_request_api->get( $billing_request_id );
		$billing_request     = $response['billing_requests'] ?? array();
		$status              = $billing_request['status'] ?? '';

		$this->logger->info(
			sprintf(
				'[Return] Billing Request %s status: %s for order #%d',
				$billing_request_id,
				$status,
				$order->get_id()
			)
		);

		switch ( $status ) {
			case 'fulfilled':
				$this->handle_fulfilled_return( $order, $billing_request );
				wp_safe_redirect( $this->get_success_url( $order ) );
				exit;

			case 'cancelled':
				$order->update_status(
					'cancelled',
					sprintf(
						/* translators: %s: Billing Request ID */
						__( 'Customer cancelled GoCardless authorisation (Billing Request: %s).', 'wc-gocardless-payments' ),
						esc_html( $billing_request_id )
					)
				);

				wc_add_notice(
					__( 'Payment cancelled. You may try again with a different payment method.', 'wc-gocardless-payments' ),
					'error'
				);

				wp_safe_redirect( wc_get_checkout_url() );
				exit;

			case 'pending_customer_approval':
			case 'pending_submission':
			default:
				// Payment pending bank processing — order remains on-hold.
				// The webhook will transition the order when confirmed.
				$order->add_order_note(
					sprintf(
						/* translators: %s: Billing Request ID */
						__( 'Customer returned from GoCardless. Awaiting bank confirmation (Billing Request: %s).', 'wc-gocardless-payments' ),
						esc_html( $billing_request_id )
					)
				);

				wp_safe_redirect( $this->get_success_url( $order ) );
				exit;
		}
	}

	/**
	 * Handle a fulfilled Billing Request: extract mandate/payment IDs and store.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order           WooCommerce order.
	 * @param array<string, mixed> $billing_request GoCardless Billing Request object.
	 * @return void
	 */
	private function handle_fulfilled_return( WC_Order $order, array $billing_request ): void {
		$links      = $billing_request['links'] ?? array();
		$mandate_id = $links['mandate'] ?? '';
		$payment_id = $links['payment'] ?? '';

		// Store mandate and payment IDs on the order.
		$meta = array();

		if ( ! empty( $mandate_id ) ) {
			$meta['mandate_id'] = $mandate_id;
		}

		if ( ! empty( $payment_id ) ) {
			$meta['payment_id'] = $payment_id;
		}

		if ( ! empty( $meta ) ) {
			WC_GoCardless_Order_Helper::bulk_update_meta( $order, $meta );
		}

		// Attempt to save the mandate as a WC Payment Token for the customer.
		if ( ! empty( $mandate_id ) && $order->get_customer_id() > 0 ) {
			$this->maybe_save_mandate_token( $order, $mandate_id, $billing_request );
		}

		// Move order to on-hold pending webhook confirmation (unless already processing).
		if ( $order->has_status( 'pending' ) ) {
			$order->update_status(
				'on-hold',
				sprintf(
					/* translators: 1: Mandate ID 2: Payment ID */
					__( 'GoCardless mandate authorised (Mandate: %1$s, Payment: %2$s). Awaiting bank confirmation.', 'wc-gocardless-payments' ),
					esc_html( $mandate_id ),
					esc_html( $payment_id )
				)
			);
		}

		/**
		 * Fires after a Billing Request is successfully fulfilled.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order             $order           WooCommerce order.
		 * @param array<string, mixed> $billing_request GoCardless Billing Request object.
		 * @param string               $mandate_id      GoCardless mandate ID.
		 * @param string               $payment_id      GoCardless payment ID.
		 */
		do_action(
			'wc_gocardless_billing_request_fulfilled',
			$order,
			$billing_request,
			$mandate_id,
			$payment_id
		);
	}

	/**
	 * Attempt to create a WC Payment Token for a fulfilled mandate.
	 *
	 * Fetches the mandate and associated bank account from GoCardless to
	 * populate the display fields (bank name, account ending) before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order           WooCommerce order.
	 * @param string               $mandate_id      GoCardless mandate ID.
	 * @param array<string, mixed> $billing_request GoCardless Billing Request object.
	 * @return void
	 */
	private function maybe_save_mandate_token(
		WC_Order $order,
		string $mandate_id,
		array $billing_request
	): void {
		try {
			$mandate_api  = new WC_GoCardless_API_Mandates( wc_gocardless()->api );
			$mandate_resp = $mandate_api->get( $mandate_id );
			$mandate_data = $mandate_resp['mandates'] ?? array();

			// Determine the gateway ID from order payment method.
			$gateway_id = $order->get_payment_method();

			// Check whether a token for this mandate already exists.
			$existing = WC_GoCardless_Payment_Token_Mandate::find_by_mandate_id(
				$mandate_id,
				$order->get_customer_id(),
				$gateway_id
			);

			if ( $existing ) {
				// Update status in case it changed.
				$existing->set_status( $mandate_data['status'] ?? 'active' );
				$existing->save();
				return;
			}

			// Fetch bank account details for display.
			$bank_account_id   = $mandate_data['links']['customer_bank_account'] ?? '';
			$bank_account_data = array();

			if ( ! empty( $bank_account_id ) ) {
				$ba_response       = wc_gocardless()->api->get(
					'/customer_bank_accounts/' . rawurlencode( $bank_account_id )
				);
				$bank_account_data = $ba_response['customer_bank_accounts'] ?? array();
			}

			WC_GoCardless_Payment_Token_Mandate::create_from_mandate(
				$mandate_resp,
				array( 'customer_bank_accounts' => $bank_account_data ),
				$order->get_customer_id(),
				$gateway_id
			);

		} catch ( WC_GoCardless_API_Exception $e ) {
			// Non-fatal — token save failure should not block order confirmation.
			$this->logger->warning(
				sprintf(
					'[Return] Failed to save mandate token for order #%d: %s',
					$order->get_id(),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Build the WooCommerce order-received (thank you) URL for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Thank you page URL.
	 */
	private function get_success_url( WC_Order $order ): string {
		return $order->get_checkout_order_received_url();
	}

	/**
	 * Generate the return URL for a given order.
	 *
	 * This URL is passed to the GoCardless Billing Request Flow as the
	 * redirect_uri and exit_uri. It includes a nonce for CSRF protection.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order              WooCommerce order.
	 * @param string   $billing_request_id GoCardless Billing Request ID.
	 * @return string Full return URL.
	 */
	public static function get_return_url( WC_Order $order, string $billing_request_id ): string {
		return add_query_arg(
			array(
				'order_id'           => $order->get_id(),
				'billing_request_id' => rawurlencode( $billing_request_id ),
				'nonce'              => wp_create_nonce( 'wc_gocardless_return_' . $order->get_id() ),
			),
			home_url( '/wc-api/' . self::RETURN_ENDPOINT . '/' )
		);
	}
}
