<?php
/**
 * HPOS-Safe Order Helper
 *
 * Provides static utility methods for reading and writing WooCommerce order
 * meta data in a manner compatible with both the legacy posts-based storage
 * and the High-Performance Order Storage (HPOS / custom_order_tables).
 *
 * All order meta operations throughout the plugin MUST use this class rather
 * than calling get_post_meta() / update_post_meta() directly, ensuring full
 * HPOS compliance.
 *
 * @package WC_GoCardless_Payments\Utilities
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Order_Helper
 *
 * @since 1.0.0
 */
class WC_GoCardless_Order_Helper {

	/**
	 * Meta key prefix for all plugin-owned order meta.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_PREFIX = '_wc_gocardless_';

	/**
	 * Retrieve a GoCardless-namespaced meta value from an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order    WooCommerce order object.
	 * @param string   $key      Meta key (without prefix).
	 * @param mixed    $default  Default value when key is absent.
	 * @return mixed Meta value or $default.
	 */
	public static function get_meta( WC_Order $order, string $key, $default = '' ) {
		$value = $order->get_meta( self::META_PREFIX . $key, true );

		return ( '' !== $value && null !== $value ) ? $value : $default;
	}

	/**
	 * Persist a GoCardless-namespaced meta value on an order.
	 *
	 * Saves the order immediately to ensure HPOS persistence. Pass
	 * $save = false to batch multiple updates before a single save().
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param string   $key   Meta key (without prefix).
	 * @param mixed    $value Meta value (must be scalar or serialisable).
	 * @param bool     $save  Whether to call $order->save() immediately.
	 * @return void
	 */
	public static function update_meta( WC_Order $order, string $key, $value, bool $save = true ): void {
		$order->update_meta_data( self::META_PREFIX . $key, $value );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Delete a GoCardless-namespaced meta key from an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param string   $key   Meta key (without prefix).
	 * @param bool     $save  Whether to call $order->save() immediately.
	 * @return void
	 */
	public static function delete_meta( WC_Order $order, string $key, bool $save = true ): void {
		$order->delete_meta_data( self::META_PREFIX . $key );

		if ( $save ) {
			$order->save();
		}
	}

	/**
	 * Persist multiple meta key-value pairs in a single save() call.
	 *
	 * Prefer this over multiple individual update_meta() calls to reduce
	 * database writes, especially important for HPOS.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order Order object.
	 * @param array<string, mixed> $meta  Associative array of key => value pairs.
	 * @return void
	 */
	public static function bulk_update_meta( WC_Order $order, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			self::update_meta( $order, $key, $value, false );
		}

		$order->save();
	}

	/**
	 * Retrieve the GoCardless payment ID stored on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string GoCardless payment ID (e.g. 'PM123ABC') or empty string.
	 */
	public static function get_payment_id( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'payment_id' );
	}

	/**
	 * Store the GoCardless payment ID on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order      WooCommerce order.
	 * @param string   $payment_id GoCardless payment ID.
	 * @return void
	 */
	public static function set_payment_id( WC_Order $order, string $payment_id ): void {
		self::update_meta( $order, 'payment_id', sanitize_text_field( $payment_id ) );
	}

	/**
	 * Retrieve the GoCardless mandate ID stored on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string GoCardless mandate ID (e.g. 'MD123ABC') or empty string.
	 */
	public static function get_mandate_id( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'mandate_id' );
	}

	/**
	 * Store the GoCardless mandate ID on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order      WooCommerce order.
	 * @param string   $mandate_id GoCardless mandate ID.
	 * @return void
	 */
	public static function set_mandate_id( WC_Order $order, string $mandate_id ): void {
		self::update_meta( $order, 'mandate_id', sanitize_text_field( $mandate_id ) );
	}

	/**
	 * Retrieve the GoCardless billing request ID stored on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Billing request ID (e.g. 'BRQ123') or empty string.
	 */
	public static function get_billing_request_id( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'billing_request_id' );
	}

	/**
	 * Store the GoCardless billing request ID on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order              WooCommerce order.
	 * @param string   $billing_request_id Billing request ID.
	 * @return void
	 */
	public static function set_billing_request_id( WC_Order $order, string $billing_request_id ): void {
		self::update_meta( $order, 'billing_request_id', sanitize_text_field( $billing_request_id ) );
	}

	/**
	 * Retrieve the GoCardless customer ID stored on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string GoCardless customer ID (e.g. 'CU123ABC') or empty string.
	 */
	public static function get_customer_id( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'customer_id' );
	}

	/**
	 * Store the GoCardless customer ID on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param string   $customer_id GoCardless customer ID.
	 * @return void
	 */
	public static function set_customer_id( WC_Order $order, string $customer_id ): void {
		self::update_meta( $order, 'customer_id', sanitize_text_field( $customer_id ) );
	}

	/**
	 * Retrieve the payment scheme (bacs_debit, sepa_core, ach) for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Scheme identifier or empty string.
	 */
	public static function get_payment_scheme( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'payment_scheme' );
	}

	/**
	 * Store the payment scheme on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $scheme GoCardless scheme identifier.
	 * @return void
	 */
	public static function set_payment_scheme( WC_Order $order, string $scheme ): void {
		$allowed = array( 'bacs_debit', 'sepa_core', 'sepa_cor1', 'ach', 'autogiro', 'becs', 'becs_nz', 'betalingsservice', 'pad' );
		if ( in_array( $scheme, $allowed, true ) ) {
			self::update_meta( $order, 'payment_scheme', $scheme );
		}
	}

	/**
	 * Retrieve the payment type stored on an order (e.g. 'instant_bank_pay', 'direct_debit').
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Payment type identifier or empty string.
	 */
	public static function get_payment_type( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'payment_type' );
	}

	/**
	 * Store the GoCardless payment type on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order        WooCommerce order.
	 * @param string   $payment_type Payment type identifier.
	 * @return void
	 */
	public static function set_payment_type( WC_Order $order, string $payment_type ): void {
		$allowed = array( 'instant_bank_pay', 'direct_debit', 'vrp' );
		if ( in_array( $payment_type, $allowed, true ) ) {
			self::update_meta( $order, 'payment_type', $payment_type );
		}
	}

	/**
	 * Retrieve the IBP (Instant Bank Pay) payment status stored on an order.
	 *
	 * IBP payments have a distinct confirmation lifecycle from Direct Debit.
	 * This stores the raw GoCardless IBP payment status for display and logic.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string GoCardless IBP status or empty string.
	 */
	public static function get_ibp_status( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'ibp_status' );
	}

	/**
	 * Store the IBP payment status on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $status GoCardless IBP status value.
	 * @param bool     $save   Whether to persist immediately.
	 * @return void
	 */
	public static function set_ibp_status( WC_Order $order, string $status, bool $save = true ): void {
		self::update_meta( $order, 'ibp_status', sanitize_text_field( $status ), $save );
	}

	/**
	 * Check whether an order has been fulfilled via a GoCardless order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True when a GoCardless payment ID is present.
	 */
	public static function has_gocardless_payment( WC_Order $order ): bool {
		return ! empty( self::get_payment_id( $order ) );
	}

	/**
	 * Retrieve the GoCardless VRP consent mandate ID stored on an order.
	 *
	 * VRP consents are stored on the initial subscription order and
	 * referenced for all subsequent renewal orders.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order (initial subscription order).
	 * @return string VRP mandate ID (e.g. 'MD123ABC') or empty string.
	 */
	public static function get_vrp_consent_id( WC_Order $order ): string {
		return (string) self::get_meta( $order, 'vrp_consent_id' );
	}

	/**
	 * Store the GoCardless VRP consent mandate ID on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order      WooCommerce order.
	 * @param string   $consent_id GoCardless VRP mandate (consent) ID.
	 * @return void
	 */
	public static function set_vrp_consent_id( WC_Order $order, string $consent_id ): void {
		self::update_meta( $order, 'vrp_consent_id', sanitize_text_field( $consent_id ) );
	}

	/**
	 * Retrieve the VRP payment amount constraint stored on an order.
	 *
	 * Records the max_amount_per_payment used when the consent was created,
	 * enabling comparison during renewals.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return int Maximum per-payment amount in minor units, or 0 if not set.
	 */
	public static function get_vrp_max_amount( WC_Order $order ): int {
		return (int) self::get_meta( $order, 'vrp_max_amount', 0 );
	}

	/**
	 * Store the VRP max-amount-per-payment constraint on an order.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order $order      WooCommerce order.
	 * @param int      $max_amount Maximum payment amount in minor units.
	 * @return void
	 */
	public static function set_vrp_max_amount( WC_Order $order, int $max_amount ): void {
		self::update_meta( $order, 'vrp_max_amount', $max_amount );
	}

	/**
	 * Retrieve a WC_Order instance safely, supporting both integer IDs and
	 * existing WC_Order objects.
	 *
	 * @since 1.0.0
	 *
	 * @param int|WC_Order $order Order ID or WC_Order object.
	 * @return WC_Order|null WC_Order instance, or null on failure.
	 */
	public static function get_order( $order ): ?WC_Order {
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		$wc_order = wc_get_order( $order );

		return ( $wc_order instanceof WC_Order ) ? $wc_order : null;
	}
}
