<?php
/**
 * GoCardless Variable Recurring Payments (VRP) API
 *
 * VRP is a GoCardless open-banking product that allows merchants to charge
 * variable amounts on demand against a pre-authorised consent, without the
 * 3–5 day settlement delay of Direct Debit.
 *
 * VRP objects map to two API resources:
 *
 *   1. /billing_requests (with VRP consent fields)
 *      A Billing Request with `mandate_request.scheme = 'faster_payments'`
 *      and `mandate_request.vrp_constraints` creates a VRP consent in a
 *      single hosted flow.  The resulting mandate acts as the VRP consent
 *      and is stored exactly like a Direct Debit mandate.
 *
 *   2. /payments (against the VRP mandate)
 *      Once a VRP consent (mandate) exists, individual payments are created
 *      via the standard /payments endpoint with the mandate link. GoCardless
 *      validates the amount against the consent constraints before accepting.
 *
 * VRP availability: UK only (Faster Payments open-banking scheme).
 * Requires GoCardless account VRP feature flag to be enabled.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_VRP
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_VRP {

	/**
	 * GoCardless scheme identifier for VRP.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCHEME = 'faster_payments';

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
	 * Create a Billing Request for a VRP consent authorisation.
	 *
	 * Builds a Billing Request containing a mandate_request with:
	 *   - scheme: 'faster_payments'
	 *   - vrp_constraints: maximum individual amount, periodic limits, currency
	 *
	 * The customer is redirected to the GoCardless hosted flow to authorise
	 * the consent with their bank via open banking. On completion, a mandate
	 * (VRP consent) is created.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     VRP consent arguments.
	 *
	 *     @type int    $max_amount_per_payment     Maximum individual payment amount in minor units.
	 *     @type string $currency                   ISO 4217 currency code (must be 'GBP' for VRP).
	 *     @type array  $periodic_limits            Array of periodic limit objects:
	 *                                              [['period'=>'month','max_total_amount'=>50000]].
	 *     @type string $given_name                 Customer first name.
	 *     @type string $family_name                Customer last name.
	 *     @type string $email                      Customer email address.
	 *     @type string $address_line1              Billing address.
	 *     @type string $city                       Billing city.
	 *     @type string $postal_code                Billing postcode.
	 *     @type string $country_code               ISO 3166-1 alpha-2 country code.
	 *     @type string $wc_order_id                WooCommerce order ID for metadata.
	 *     @type string $wc_subscription_id         WooCommerce subscription ID for metadata.
	 *     @type string $idempotency_key            Unique idempotency key.
	 * }
	 * @return array<string, mixed> Billing Request object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_consent_billing_request( array $args ): array {
		$vrp_constraints = array(
			'max_amount_per_payment' => (int) ( $args['max_amount_per_payment'] ?? 0 ),
			'currency'               => strtoupper( $args['currency'] ?? 'GBP' ),
		);

		if ( ! empty( $args['periodic_limits'] ) && is_array( $args['periodic_limits'] ) ) {
			$vrp_constraints['periodic_limits'] = $args['periodic_limits'];
		}

		$body = array(
			'billing_requests' => array(
				'mandate_request'    => array(
					'scheme'          => self::SCHEME,
					'vrp_constraints' => $vrp_constraints,
				),
				'prefilled_customer' => $this->build_prefilled_customer( $args ),
				'metadata'           => array(
					'wc_order_id'        => (string) ( $args['wc_order_id'] ?? '' ),
					'wc_subscription_id' => (string) ( $args['wc_subscription_id'] ?? '' ),
					'payment_method'     => 'vrp',
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
	 * Create a VRP payment against an existing VRP consent (mandate).
	 *
	 * Delegates to the standard /payments endpoint. GoCardless validates
	 * the requested amount against the consent's VRP constraints before
	 * accepting the payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id      GoCardless VRP mandate ID (e.g. 'MD123').
	 * @param int    $amount          Payment amount in minor units.
	 * @param string $currency        ISO 4217 currency code (must match consent currency).
	 * @param string $description     Payment description.
	 * @param string $idempotency_key Unique key for safe retry.
	 * @return array<string, mixed> Created payment object.
	 * @throws WC_GoCardless_API_Exception On API error (including constraint violations).
	 */
	public function create_payment(
		string $mandate_id,
		int $amount,
		string $currency,
		string $description = '',
		string $idempotency_key = ''
	): array {
		$body = array(
			'payments' => array(
				'amount'      => $amount,
				'currency'    => strtoupper( $currency ),
				'description' => $description,
				'links'       => array(
					'mandate' => $mandate_id,
				),
				'metadata'    => array(
					'payment_method' => 'vrp',
				),
			),
		);

		return $this->client->post( '/payments', $body, $idempotency_key ?: null );
	}

	/**
	 * Retrieve a VRP consent (mandate) by its GoCardless mandate ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @return array<string, mixed> Mandate object with VRP constraint metadata.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get_consent( string $mandate_id ): array {
		return $this->client->get(
			'/mandates/' . rawurlencode( $mandate_id )
		);
	}

	/**
	 * Cancel a VRP consent.
	 *
	 * Once cancelled, the consent cannot be used to collect further payments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @param string $reason     Optional cancellation reason stored in metadata.
	 * @return array<string, mixed> Cancelled mandate object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function cancel_consent( string $mandate_id, string $reason = '' ): array {
		$body = array();

		if ( ! empty( $reason ) ) {
			$body['data'] = array(
				'metadata' => array( 'cancellation_reason' => sanitize_text_field( $reason ) ),
			);
		}

		return $this->client->post(
			'/mandates/' . rawurlencode( $mandate_id ) . '/actions/cancel',
			$body
		);
	}

	/**
	 * Determine whether a VRP consent mandate is in a usable state.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $mandate GoCardless mandate object.
	 * @return bool True when the consent can be used to collect a payment.
	 */
	public function is_consent_active( array $mandate ): bool {
		$data   = $mandate['mandates'] ?? $mandate;
		$status = $data['status'] ?? '';
		$scheme = $data['scheme'] ?? '';

		return self::SCHEME === $scheme
		       && in_array( $status, array( 'active', 'submitted', 'pending_submission' ), true );
	}

	/**
	 * Build the prefilled_customer block from order/argument data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Arguments containing customer data.
	 * @return array<string, string> Prefilled customer fields.
	 */
	private function build_prefilled_customer( array $args ): array {
		$customer = array();

		$map = array(
			'given_name'   => 'given_name',
			'family_name'  => 'family_name',
			'email'        => 'email',
			'address_line1' => 'address_line1',
			'city'         => 'city',
			'postal_code'  => 'postal_code',
		);

		foreach ( $map as $arg_key => $field ) {
			if ( ! empty( $args[ $arg_key ] ) ) {
				$customer[ $field ] = 'email' === $field
					? sanitize_email( $args[ $arg_key ] )
					: sanitize_text_field( $args[ $arg_key ] );
			}
		}

		if ( ! empty( $args['country_code'] ) ) {
			$customer['country_code'] = strtoupper( sanitize_text_field( $args['country_code'] ) );
		}

		return $customer;
	}
}
