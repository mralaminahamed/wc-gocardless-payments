<?php
/**
 * Variable Recurring Payments Gateway — WC_GoCardless_Gateway_VRP
 *
 * Handles GoCardless Variable Recurring Payments (VRP) for subscription renewals.
 * Full implementation in Phase 4.
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_VRP
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_VRP extends WC_GoCardless_Gateway {

	/**
	 * Define gateway properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_vrp';
		$this->method_title       = __( 'GoCardless — Variable Recurring Payments', 'wc-gocardless-payments' );
		$this->method_description = __( 'Variable Recurring Payments via GoCardless open banking. Supports flexible subscription amounts.', 'wc-gocardless-payments' );
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
			'multiple_subscriptions',
		);
	}

	/**
	 * Process the payment for a given order.
	 *
	 * Full implementation provided in Phase 4.
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
		return __( 'Variable Recurring Payment', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Flexible recurring bank payments via GoCardless open banking.', 'wc-gocardless-payments' );
	}
}
