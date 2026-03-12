<?php
/**
 * Instant Bank Pay Gateway — WC_GoCardless_Gateway_Instant_Bank
 *
 * Handles GoCardless Instant Bank Pay (open banking) flow.
 * Full implementation in Phase 3.
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_Instant_Bank
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_Instant_Bank extends WC_GoCardless_Gateway {

	/**
	 * Define gateway properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_instant_bank';
		$this->method_title       = __( 'GoCardless — Instant Bank Pay', 'wc-gocardless-payments' );
		$this->method_description = __( 'Accept instant open banking payments via GoCardless. Funds are confirmed immediately.', 'wc-gocardless-payments' );
		$this->has_fields         = false;

		$this->supports = array(
			'products',
			'refunds',
		);
	}

	/**
	 * Process the payment for a given order.
	 *
	 * Full implementation provided in Phase 3.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		return array(
			'result'   => 'failure',
			'redirect' => '',
		);
	}

	/**
	 * Return the default customer-facing title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_title(): string {
		return __( 'Instant Bank Pay', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Pay instantly via your bank account. Secure, fast, and no card details required.', 'wc-gocardless-payments' );
	}
}
