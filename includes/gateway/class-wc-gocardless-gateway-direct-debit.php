<?php
/**
 * Direct Debit Gateway — WC_GoCardless_Gateway_Direct_Debit
 *
 * Handles BACS, SEPA, and ACH Direct Debit flows via GoCardless Billing Requests.
 * Full implementation in Phase 2.
 *
 * @package WC_GoCardless_Payments\Gateway
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Gateway_Direct_Debit
 *
 * @since 1.0.0
 */
class WC_GoCardless_Gateway_Direct_Debit extends WC_GoCardless_Gateway {

	/**
	 * Define gateway properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function define_gateway_properties(): void {
		$this->id                 = 'gocardless_direct_debit';
		$this->method_title       = __( 'GoCardless — Direct Debit', 'wc-gocardless-payments' );
		$this->method_description = __( 'Accept Direct Debit payments via GoCardless (BACS, SEPA, ACH). Customers authorise a mandate and payments are collected automatically.', 'wc-gocardless-payments' );
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
			'subscription_payment_method_change_customer',
			'multiple_subscriptions',
		);
	}

	/**
	 * Return gateway-specific settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_gateway_form_fields(): array {
		return array(
			'direct_debit_section' => array(
				'title' => __( 'Direct Debit Settings', 'wc-gocardless-payments' ),
				'type'  => 'title',
			),
			'preferred_scheme'     => array(
				'title'   => __( 'Preferred Scheme', 'wc-gocardless-payments' ),
				'type'    => 'select',
				'options' => array(
					''          => __( 'Auto-detect based on customer location', 'wc-gocardless-payments' ),
					'bacs_debit' => __( 'BACS (UK)', 'wc-gocardless-payments' ),
					'sepa_core'  => __( 'SEPA Core (Eurozone)', 'wc-gocardless-payments' ),
					'ach'        => __( 'ACH (USA)', 'wc-gocardless-payments' ),
				),
				'default' => '',
			),
			'statement_descriptor' => array(
				'title'       => __( 'Statement Descriptor', 'wc-gocardless-payments' ),
				'type'        => 'text',
				'description' => __( 'Description that appears on the customer\'s bank statement (max 10 characters for BACS).', 'wc-gocardless-payments' ),
				'default'     => get_bloginfo( 'name' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process the payment for a given order.
	 *
	 * Full implementation provided in Phase 2.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string> Result array with 'result' and 'redirect' keys.
	 */
	public function process_payment( $order_id ): array {
		// Phase 2 implementation: creates Billing Request → redirects customer.
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
		return __( 'Direct Debit', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default customer-facing description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_default_description(): string {
		return __( 'Pay via Direct Debit. You will be redirected to authorise your bank mandate.', 'wc-gocardless-payments' );
	}
}
