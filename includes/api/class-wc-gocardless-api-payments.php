<?php
/**
 * GoCardless Payments API — WC_GoCardless_API_Payments
 *
 * Payments endpoint wrapper. Full implementation in Phase 2.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Payments
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Payments {

	/**
	 * API client.
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
	 * @param WC_GoCardless_API_Client $client API client instance.
	 */
	public function __construct( WC_GoCardless_API_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Create a GoCardless payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id      GoCardless mandate ID.
	 * @param int    $amount          Amount in minor units.
	 * @param string $currency        ISO 4217 currency code.
	 * @param string $description     Payment description.
	 * @param string $idempotency_key Unique idempotency key.
	 * @return array<string, mixed> Created payment object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create(
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
			),
		);

		return $this->client->post( '/payments', $body, $idempotency_key ?: null );
	}

	/**
	 * Retrieve a GoCardless payment by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $payment_id GoCardless payment ID.
	 * @return array<string, mixed> Payment object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get( string $payment_id ): array {
		return $this->client->get( '/payments/' . rawurlencode( $payment_id ) );
	}

	/**
	 * Create a refund for a GoCardless payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $payment_id GoCardless payment ID.
	 * @param int    $amount     Refund amount in minor units.
	 * @param string $currency   ISO 4217 currency code.
	 * @param string $reason     Refund reason.
	 * @return array<string, mixed> Refund object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_refund(
		string $payment_id,
		int $amount,
		string $currency,
		string $reason = ''
	): array {
		$body = array(
			'refunds' => array(
				'amount'                    => $amount,
				'total_amount_confirmation' => $amount,
				'reference'                 => $reason,
				'links'                     => array(
					'payment' => $payment_id,
				),
			),
		);

		return $this->client->post( '/refunds', $body );
	}

	/**
	 * Cancel a GoCardless payment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $payment_id GoCardless payment ID.
	 * @return array<string, mixed> Cancelled payment object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function cancel( string $payment_id ): array {
		return $this->client->post(
			'/payments/' . rawurlencode( $payment_id ) . '/actions/cancel',
			array()
		);
	}
}
