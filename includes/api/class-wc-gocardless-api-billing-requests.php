<?php
/**
 * GoCardless Billing Requests API
 *
 * Wraps the GoCardless /billing_requests and /billing_request_flows endpoints.
 *
 * A Billing Request is the central object for the GoCardless Payment Intentions
 * flow. It groups together:
 *   - A payment_request  (for one-time Instant Bank Pay or Direct Debit payment)
 *   - A mandate_request  (to authorise a Direct Debit mandate for future charges)
 *
 * The Billing Request Flow provides a hosted URL to which the customer is
 * redirected to complete authorisation.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Billing_Requests
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Billing_Requests {

	/**
	 * API client instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_API_Client
	 */
	private WC_GoCardless_API_Client $client;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_GoCardless_API_Client $client Shared API client.
	 */
	public function __construct( WC_GoCardless_API_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Create a Billing Request for a Direct Debit mandate + payment.
	 *
	 * This creates a combined mandate_request and payment_request so that
	 * in a single hosted flow the customer both authorises a mandate and
	 * pays the initial order amount.
	 *
	 * @since 1.0.0
	 * @href https://developer.gocardless.com/api-reference/#billing-requests-create-a-billing-request
	 *
	 * @param array<string, mixed> $args {
	 *     Request arguments.
	 *
	 *     @type int    $amount          Order amount in minor units (e.g. 1999 for £19.99).
	 *     @type string $currency        ISO 4217 currency code (e.g. 'GBP').
	 *     @type string $description     Payment description shown to the customer.
	 *     @type string $scheme          GoCardless scheme: bacs_debit, sepa_core, ach, etc.
	 *     @type string $given_name      Customer first name (pre-fills the hosted flow).
	 *     @type string $family_name     Customer last name.
	 *     @type string $email           Customer email address.
	 *     @type string $idempotency_key Unique key to prevent duplicate requests.
	 * }
	 * @return array<string, mixed> Billing Request object from GoCardless.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_for_direct_debit( array $args ): array {
		$mandate_request = array(
			'metadata' => array(
				'wc_order_id'     => $args['wc_order_id'] ?? '',
				'idempotency_key' => $args['idempotency_key'] ?? '',
				'user_email'      => $args['email'] ?? '',
			),
		);

		// Only add scheme if explicitly provided.
		if ( ! empty( $args['scheme'] ) ) {
			$mandate_request['scheme'] = $args['scheme'];
		}

		$body = array(
			'billing_requests' => array(
				'mandate_request'    => $mandate_request,
				'payment_request'    => array(
					'amount'           => $args['amount'],
					'currency'         => strtoupper( $args['currency'] ),
					'description'     => $args['description'] ?? '',
					'funds_settlement' => 'direct',
				),
				'prefilled_customer' => $this->build_prefilled_customer( $args ),
				'metadata'           => array(
					'wc_order_id' => $args['wc_order_id'] ?? '',
				),
			),
		);

		return $this->client->post(
			'/billing_requests',
			$body,
			$args['idempotency_key'] ?? null
		);
	}

	/**
	 * Create a Billing Request for an Instant Bank Pay (IBP) one-off payment.
	 *
	 * IBP uses GoCardless open-banking rails to confirm payment immediately
	 * (or near-immediately) rather than via the traditional 3–5 day Direct
	 * Debit clearing cycle. No mandate is created; this is a single-use
	 * payment authorisation.
	 *
	 * Key difference from Direct Debit: the `payment_request` carries
	 * `funds_settlement: instant` and there is NO `mandate_request` block.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     Request arguments.
	 *
	 *     @type int    $amount            Order amount in minor units (e.g. 2500 for £25.00).
	 *     @type string $currency          ISO 4217 currency code.
	 *     @type string $description       Payment description shown to the customer.
	 *     @type string $given_name        Customer first name.
	 *     @type string $family_name       Customer last name.
	 *     @type string $email             Customer email address.
	 *     @type string $address_line1     Billing address line 1.
	 *     @type string $city              Billing city.
	 *     @type string $postal_code       Billing postcode.
	 *     @type string $country_code      ISO 3166-1 alpha-2 country code.
	 *     @type string $wc_order_id       WooCommerce order ID for metadata.
	 *     @type string $idempotency_key   Unique key to prevent duplicate requests.
	 *     @type bool   $collect_mandate   Whether to also collect a mandate alongside
	 *                                     the payment (IBP + mandate combo flow).
	 *                                     Defaults to false for pure IBP.
	 * }
	 * @return array<string, mixed> Billing Request object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_for_instant_bank_pay( array $args ): array {
		$collect_mandate = ! empty( $args['collect_mandate'] ) || in_array( $args['scheme'] ?? null, array( 'ACH', 'PAD' ), true );

		$payment_request = array(
			'amount'           => $args['amount'],
			'currency'         => strtoupper( $args['currency'] ),
			'description'      => $args['description'] ?? '',
			'funds_settlement' => 'instant',
		);

		$body = array(
			'billing_requests' => array(
				'payment_request'      => $payment_request,
				'prefilled_customer'  => $this->build_prefilled_customer( $args ),
				'metadata'             => array(
					'wc_order_id'    => $args['wc_order_id'] ?? '',
					'payment_method' => 'instant_bank_pay',
				),
			),
		);

		// Optionally attach a mandate_request for IBP + mandate combo flows.
		// This allows the merchant to collect a mandate alongside the payment
		// so future renewals can be processed without redirect.
		if ( $collect_mandate ) {
			$body['billing_requests']['mandate_request'] = array(
				'scheme' => $args['scheme'] ?? null,
			);

			if ( empty( $body['billing_requests']['mandate_request']['scheme'] ) ) {
				unset( $body['billing_requests']['mandate_request']['scheme'] );
			}
		}

		return $this->client->post(
			'/billing_requests',
			$body,
			$args['idempotency_key'] ?? null
		);
	}

	/**
	 * Create a Billing Request for a mandate-only authorisation (no initial payment).
	 *
	 * Used when setting up a mandate for future subscription renewals without
	 * charging immediately (e.g. free trial or $0 initial period).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     @type string $scheme          GoCardless scheme.
	 *     @type string $given_name      Customer first name.
	 *     @type string $family_name     Customer last name.
	 *     @type string $email           Customer email.
	 *     @type string $wc_order_id     WooCommerce order ID (stored in metadata).
	 *     @type string $idempotency_key Unique idempotency key.
	 * }
	 * @return array<string, mixed> Billing Request object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_for_mandate_only( array $args ): array {
		$body = array(
			'billing_requests' => array(
				'mandate_request'    => array(
					'scheme' => $args['scheme'] ?? null,
				),
				'prefilled_customer' => $this->build_prefilled_customer( $args ),
				'metadata'           => array(
					'wc_order_id' => $args['wc_order_id'] ?? '',
				),
			),
		);

		if ( empty( $body['billing_requests']['mandate_request']['scheme'] ) ) {
			unset( $body['billing_requests']['mandate_request']['scheme'] );
		}

		return $this->client->post(
			'/billing_requests',
			$body,
			$args['idempotency_key'] ?? null
		);
	}

	/**
	 * Retrieve a Billing Request by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $billing_request_id GoCardless Billing Request ID (e.g. 'BRQ123').
	 * @return array<string, mixed> Billing Request object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get( string $billing_request_id ): array {
		return $this->client->get(
			'/billing_requests/' . rawurlencode( $billing_request_id )
		);
	}

	/**
	 * Cancel a Billing Request.
	 *
	 * Called when the customer abandons the hosted authorisation flow and
	 * returns to the store, or when an order is cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $billing_request_id GoCardless Billing Request ID.
	 * @return array<string, mixed> Cancelled Billing Request object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function cancel( string $billing_request_id ): array {
		return $this->client->post(
			'/billing_requests/' . rawurlencode( $billing_request_id ) . '/actions/cancel',
			array()
		);
	}

	/**
	 * Create a Billing Request Flow for a given Billing Request.
	 *
	 * The Flow provides:
	 *   - An `authorisation_url` the customer is redirected to.
	 *   - A `redirect_uri` where GoCardless sends the customer after completion.
	 *   - Optional pre-fill and exit URI configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $billing_request_id GoCardless Billing Request ID.
	 * @param string               $redirect_uri       URL to redirect customer after authorisation.
	 * @param string               $exit_uri           URL to redirect if customer cancels/exits.
	 * @param array<string, mixed> $options            Optional additional flow options.
	 * @return array<string, mixed> Billing Request Flow object (includes `authorisation_url`).
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_flow(
		string $billing_request_id,
		string $redirect_uri,
		string $exit_uri = '',
		array $options = array()
	): array {
		$body = array(
			'billing_request_flows' => array_merge(
				array(
					'redirect_uri'                 => $redirect_uri,
					'exit_uri'                     => $exit_uri ?: $redirect_uri,
					'show_redirect_buttons'        => false,
					'show_success_redirect_button' => true,
					'links'                        => array(
						'billing_request' => $billing_request_id,
					),
				),
				$options
			),
		);

		return $this->client->post( '/billing_request_flows', $body );
	}

	/**
	 * Notify a Billing Request (send authorisation email to customer).
	 *
	 * @since 1.0.0
	 *
	 * @param string $billing_request_id GoCardless Billing Request ID.
	 * @param string $notification_type  Notification type: 'payment_request' or 'mandate_request'.
	 * @return array<string, mixed> API response.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function notify( string $billing_request_id, string $notification_type = 'payment_request' ): array {
		return $this->client->post(
			'/billing_requests/' . rawurlencode( $billing_request_id ) . '/actions/notify',
			array(
				'data' => array(
					'notification_type' => $notification_type,
				),
			)
		);
	}

	/**
	 * Build the prefilled_customer block from order arguments.
	 *
	 * Pre-filling customer details reduces friction in the hosted flow
	 * by skipping the name/email entry step.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Arguments containing customer data.
	 * @return array<string, string> Prefilled customer data.
	 */
	private function build_prefilled_customer( array $args ): array {
		$customer = array();

		if ( ! empty( $args['given_name'] ) ) {
			$customer['given_name'] = sanitize_text_field( $args['given_name'] );
		}

		if ( ! empty( $args['family_name'] ) ) {
			$customer['family_name'] = sanitize_text_field( $args['family_name'] );
		}

		if ( ! empty( $args['email'] ) ) {
			$customer['email'] = sanitize_email( $args['email'] );
		}

		if ( ! empty( $args['address_line1'] ) ) {
			$customer['address_line1'] = sanitize_text_field( $args['address_line1'] );
		}

		if ( ! empty( $args['city'] ) ) {
			$customer['city'] = sanitize_text_field( $args['city'] );
		}

		if ( ! empty( $args['postal_code'] ) ) {
			$customer['postal_code'] = sanitize_text_field( $args['postal_code'] );
		}

		if ( ! empty( $args['country_code'] ) ) {
			$customer['country_code'] = strtoupper( sanitize_text_field( $args['country_code'] ) );
		}

		return $customer;
	}
}
