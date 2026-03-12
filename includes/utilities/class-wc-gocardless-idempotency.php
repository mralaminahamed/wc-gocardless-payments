<?php
/**
 * Idempotency Key Management
 *
 * Generates, stores, and validates idempotency keys for GoCardless API
 * requests. Prevents duplicate payments caused by network retries or
 * accidental double-submits.
 *
 * @package WC_GoCardless_Payments\Utilities
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Idempotency
 *
 * GoCardless requires a unique `Idempotency-Key` header on all POST requests.
 * This class:
 *   - Generates cryptographically random keys.
 *   - Derives deterministic keys from order+action pairs to support safe retries.
 *   - Stores used keys against orders via HPOS-safe meta.
 *
 * @since 1.0.0
 */
class WC_GoCardless_Idempotency {

	/**
	 * Meta key suffix used to store the idempotency key on an order.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_SUFFIX = 'idempotency_key';

	/**
	 * Generate a new random idempotency key.
	 *
	 * Returns a 64-character hexadecimal string derived from a cryptographically
	 * secure random source, prefixed with the plugin identifier for traceability.
	 *
	 * @since 1.0.0
	 *
	 * @return string Random idempotency key.
	 */
	public function generate(): string {
		return 'wc-gc-' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Derive a deterministic idempotency key from an order and action.
	 *
	 * Deterministic keys allow safe retries: the same order+action pair
	 * will always produce the same key, so retrying a failed request will
	 * not create a duplicate API resource.
	 *
	 * The key is an HMAC-SHA256 of "order_id:action" using the WordPress
	 * AUTH_KEY as the HMAC secret.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $order_id WooCommerce order ID.
	 * @param string $action   Action identifier (e.g. 'create_payment', 'create_mandate').
	 * @return string Deterministic idempotency key (74 characters max).
	 */
	public function for_order( int $order_id, string $action ): string {
		$secret  = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$payload = $order_id . ':' . $action;

		return 'wc-gc-' . hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Persist an idempotency key against a WooCommerce order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order           WooCommerce order.
	 * @param string   $action          Action identifier.
	 * @param string   $idempotency_key The key to store.
	 * @return void
	 */
	public function store_for_order( WC_Order $order, string $action, string $idempotency_key ): void {
		$meta_key = self::META_KEY_SUFFIX . '_' . sanitize_key( $action );
		WC_GoCardless_Order_Helper::update_meta( $order, $meta_key, $idempotency_key );
	}

	/**
	 * Retrieve the stored idempotency key for an order action.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $action Action identifier.
	 * @return string Stored key, or empty string if not yet set.
	 */
	public function get_for_order( WC_Order $order, string $action ): string {
		$meta_key = self::META_KEY_SUFFIX . '_' . sanitize_key( $action );
		return (string) WC_GoCardless_Order_Helper::get_meta( $order, $meta_key );
	}

	/**
	 * Retrieve or generate-and-store a deterministic idempotency key for an order action.
	 *
	 * This is the preferred method for payment-critical operations: it ensures
	 * that repeated process_payment() calls (e.g. on page reload) reuse the same
	 * key, preventing duplicate GoCardless resources.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $action Action identifier.
	 * @return string Idempotency key.
	 */
	public function get_or_create_for_order( WC_Order $order, string $action ): string {
		$existing = $this->get_for_order( $order, $action );

		if ( ! empty( $existing ) ) {
			return $existing;
		}

		$key = $this->for_order( $order->get_id(), $action );
		$this->store_for_order( $order, $action, $key );

		return $key;
	}
}
