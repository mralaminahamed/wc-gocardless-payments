<?php
/**
 * WooCommerce Subscriptions Integration — WC_GoCardless_Subscriptions
 *
 * Registers hooks for WooCommerce Subscriptions (Automattic) and
 * compatible subscription plugins. Full implementation in Phase 4.
 *
 * @package WC_GoCardless_Payments\Subscriptions
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Subscriptions
 *
 * @since 1.0.0
 */
class WC_GoCardless_Subscriptions {

	/**
	 * Constructor — register subscription lifecycle hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Scheduled renewal payment hooks — Phase 4 implementation.
		add_action(
			'woocommerce_scheduled_subscription_payment_gocardless_direct_debit',
			array( $this, 'process_scheduled_renewal' ),
			10,
			2
		);

		add_action(
			'woocommerce_scheduled_subscription_payment_gocardless_vrp',
			array( $this, 'process_scheduled_renewal' ),
			10,
			2
		);
	}

	/**
	 * Process a scheduled subscription renewal payment.
	 *
	 * Full implementation provided in Phase 4.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount_to_charge Amount to charge for renewal.
	 * @param WC_Order $renewal_order    WooCommerce renewal order.
	 * @return void
	 */
	public function process_scheduled_renewal( float $amount_to_charge, WC_Order $renewal_order ): void {
		// Phase 4 implementation.
	}
}
