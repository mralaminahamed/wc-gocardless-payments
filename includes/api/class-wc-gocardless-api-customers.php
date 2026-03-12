<?php
/**
 * GoCardless Customers API
 *
 * Wraps the GoCardless /customers endpoint. Manages the mapping between
 * WooCommerce customers and their GoCardless customer objects, enabling
 * mandate reuse across multiple orders.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Customers
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Customers {

	/**
	 * User meta key for storing the GoCardless customer ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const USER_META_CUSTOMER_ID = '_wc_gocardless_customer_id';

	/**
	 * User meta key for storing the GoCardless customer ID in sandbox mode.
	 *
	 * Separate meta ensures sandbox and live IDs never collide.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const USER_META_SANDBOX_CUSTOMER_ID = '_wc_gocardless_sandbox_customer_id';

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
	 * Create a new GoCardless customer from a WooCommerce order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order containing billing details.
	 * @return array<string, mixed> Created GoCardless customer object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function create_from_order( WC_Order $order ): array {
		$body = array(
			'customers' => array(
				'email'         => sanitize_email( $order->get_billing_email() ),
				'given_name'    => sanitize_text_field( $order->get_billing_first_name() ),
				'family_name'   => sanitize_text_field( $order->get_billing_last_name() ),
				'address_line1' => sanitize_text_field( $order->get_billing_address_1() ),
				'address_line2' => sanitize_text_field( $order->get_billing_address_2() ),
				'city'          => sanitize_text_field( $order->get_billing_city() ),
				'postal_code'   => sanitize_text_field( $order->get_billing_postcode() ),
				'country_code'  => strtoupper( sanitize_text_field( $order->get_billing_country() ) ),
				'phone_number'  => sanitize_text_field( $order->get_billing_phone() ),
				'metadata'      => array(
					'wc_customer_id' => (string) $order->get_customer_id(),
					'wc_order_id'    => (string) $order->get_id(),
				),
			),
		);

		$response = $this->client->post( '/customers', $body );

		// Persist the GoCardless customer ID against the WC user account.
		if ( $order->get_customer_id() > 0 ) {
			$this->store_customer_id_for_user(
				$order->get_customer_id(),
				$response['customers']['id'] ?? ''
			);
		}

		return $response;
	}

	/**
	 * Retrieve a GoCardless customer by their ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id GoCardless customer ID (e.g. 'CU123ABC').
	 * @return array<string, mixed> Customer object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get( string $customer_id ): array {
		return $this->client->get(
			'/customers/' . rawurlencode( $customer_id )
		);
	}

	/**
	 * Update a GoCardless customer record.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $customer_id GoCardless customer ID.
	 * @param array<string, mixed> $data        Fields to update (same shape as create).
	 * @return array<string, mixed> Updated customer object.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function update( string $customer_id, array $data ): array {
		return $this->client->put(
			'/customers/' . rawurlencode( $customer_id ),
			array( 'customers' => $data )
		);
	}

	/**
	 * Retrieve the stored GoCardless customer ID for a WooCommerce user.
	 *
	 * Returns separate IDs for sandbox vs live mode to prevent cross-environment
	 * contamination.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $wp_user_id WooCommerce/WordPress user ID.
	 * @param bool $sandbox    Whether to retrieve the sandbox customer ID.
	 * @return string GoCardless customer ID, or empty string if not found.
	 */
	public function get_customer_id_for_user( int $wp_user_id, bool $sandbox = false ): string {
		$meta_key = $sandbox ? self::USER_META_SANDBOX_CUSTOMER_ID : self::USER_META_CUSTOMER_ID;
		return (string) get_user_meta( $wp_user_id, $meta_key, true );
	}

	/**
	 * Persist the GoCardless customer ID against a WooCommerce user.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $wp_user_id  WooCommerce/WordPress user ID.
	 * @param string $customer_id GoCardless customer ID to store.
	 * @return void
	 */
	public function store_customer_id_for_user( int $wp_user_id, string $customer_id ): void {
		$is_sandbox = $this->client->is_sandbox();
		$meta_key   = $is_sandbox ? self::USER_META_SANDBOX_CUSTOMER_ID : self::USER_META_CUSTOMER_ID;

		update_user_meta( $wp_user_id, $meta_key, sanitize_text_field( $customer_id ) );
	}

	/**
	 * Get or create a GoCardless customer for a WooCommerce order.
	 *
	 * If the logged-in customer already has a GoCardless customer ID stored,
	 * that ID is returned to enable mandate reuse. Otherwise a new customer
	 * is created and the ID stored for future orders.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string GoCardless customer ID.
	 * @throws WC_GoCardless_API_Exception On API error.
	 */
	public function get_or_create_for_order( WC_Order $order ): string {
		$wc_customer_id = $order->get_customer_id();

		// For logged-in customers, try to reuse an existing GoCardless customer.
		if ( $wc_customer_id > 0 ) {
			$existing_id = $this->get_customer_id_for_user(
				$wc_customer_id,
				$this->client->is_sandbox()
			);

			if ( ! empty( $existing_id ) ) {
				return $existing_id;
			}
		}

		// Create a new GoCardless customer from the order billing details.
		$response    = $this->create_from_order( $order );
		$customer_id = $response['customers']['id'] ?? '';

		// Also store against the order meta for reference.
		WC_GoCardless_Order_Helper::set_customer_id( $order, $customer_id );

		return $customer_id;
	}
}
