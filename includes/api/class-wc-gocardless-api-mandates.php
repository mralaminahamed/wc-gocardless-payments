<?php
/**
 * GoCardless Mandates API
 *
 * Wraps the GoCardless /mandates endpoint. Mandates represent a customer's
 * authorisation to collect Direct Debit payments from their bank account.
 * Once created, a mandate can be used to collect multiple payments without
 * requiring re-authorisation.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Mandates
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Mandates {

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
	 * Retrieve a mandate by its GoCardless mandate ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID (e.g. 'MD123ABC').
	 * @return array<string, mixed> Mandate object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get( string $mandate_id ): array {
		return $this->client->get(
			'/mandates/' . rawurlencode( $mandate_id )
		);
	}

	/**
	 * List mandates for a given GoCardless customer ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id GoCardless customer ID (e.g. 'CU123ABC').
	 * @param string $status      Optional status filter: 'pending_customer_approval',
	 *                            'pending_submission', 'submitted', 'active',
	 *                            'failed', 'cancelled', 'expired', 'consumed', 'blocked'.
	 * @return array<int, array<string, mixed>> Array of mandate objects.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function list_for_customer( string $customer_id, string $status = '' ): array {
		$params = array( 'customer' => $customer_id );

		if ( ! empty( $status ) ) {
			$params['status'] = $status;
		}

		$response = $this->client->get( '/mandates', $params );

		return $response['mandates'] ?? array();
	}

	/**
	 * Cancel a mandate.
	 *
	 * Once cancelled, the mandate cannot be used to collect payments.
	 * Any pending or submitted payments associated with this mandate will fail.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @param string $reason     Optional cancellation reason for audit trail.
	 * @return array<string, mixed> Cancelled mandate object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function cancel( string $mandate_id, string $reason = '' ): array {
		$body = array();

		if ( ! empty( $reason ) ) {
			$body['data'] = array( 'metadata' => array( 'cancellation_reason' => sanitize_text_field( $reason ) ) );
		}

		return $this->client->post(
			'/mandates/' . rawurlencode( $mandate_id ) . '/actions/cancel',
			$body
		);
	}

	/**
	 * Reinstate a cancelled or expired mandate.
	 *
	 * Only available for certain mandate schemes and statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @return array<string, mixed> Reinstated mandate object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function reinstate( string $mandate_id ): array {
		return $this->client->post(
			'/mandates/' . rawurlencode( $mandate_id ) . '/actions/reinstate',
			array()
		);
	}

	/**
	 * Determine whether a mandate is in a usable state for payment collection.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $mandate GoCardless mandate object.
	 * @return bool True when the mandate can be used to collect a payment.
	 */
	public function is_active( array $mandate ): bool {
		$mandate_data = $mandate['mandates'] ?? $mandate;
		$status       = $mandate_data['status'] ?? '';

		return in_array( $status, array( 'active', 'submitted', 'pending_submission' ), true );
	}

	/**
	 * Find the most recently created active mandate for a given customer.
	 *
	 * Iterates the customer's mandate list and returns the first active mandate,
	 * ordered by creation date descending (GoCardless returns newest first).
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id GoCardless customer ID.
	 * @return array<string, mixed>|null Active mandate object or null if not found.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function find_active_for_customer( string $customer_id ): ?array {
		$mandates = $this->list_for_customer( $customer_id, 'active' );

		return ! empty( $mandates ) ? $mandates[0] : null;
	}
}
