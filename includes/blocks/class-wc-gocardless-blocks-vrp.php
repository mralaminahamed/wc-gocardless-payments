<?php
/**
 * VRP Blocks Integration — WC_GoCardless_Blocks_VRP
 *
 * Registers the GoCardless Variable Recurring Payments gateway with the
 * WooCommerce Cart and Checkout blocks.
 *
 * VRP is UK open-banking only. The component displays the consent
 * authorisation redirect notice and the configurable consent limit
 * summary (max per payment, max per month) for customer transparency.
 *
 * No saved token selector is rendered — VRP consents are stored on the
 * subscription order and used automatically for renewals.
 *
 * @package WC_GoCardless_Payments\Blocks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Blocks_VRP
 *
 * @since 1.0.0
 */
class WC_GoCardless_Blocks_VRP extends WC_GoCardless_Blocks_Integration {

	/**
	 * Unique identifier — must match the gateway ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'gocardless_vrp';

	/**
	 * Return server-side data for the VRP React component.
	 *
	 * Passes the consent constraint configuration so the component can
	 * render the transparency summary without an additional API call.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		$data = parent::get_payment_method_data();

		$max_per_payment = (float) $this->get_setting( 'max_amount_per_payment', '500' );
		$max_per_month   = (float) $this->get_setting( 'max_amount_per_month', '2000' );
		$currency        = get_woocommerce_currency();

		return array_merge(
			$data,
			array(
				'gateway_id'      => $this->name,
				'max_per_payment' => $max_per_payment,
				'max_per_month'   => $max_per_month,
				'currency'        => $currency,
				'currency_symbol' => get_woocommerce_currency_symbol( $currency ),
				'i18n'            => array(
					'redirect_notice' => esc_html__(
						'You will be redirected to GoCardless to authorise your Variable Recurring Payment consent.',
						'wc-gocardless-payments'
					),
					'consent_limits'  => esc_html__( 'Consent limits:', 'wc-gocardless-payments' ),
					/* translators: %s: Formatted amount */
					'per_payment'     => esc_html__( 'up to %s per payment', 'wc-gocardless-payments' ),
					/* translators: %s: Formatted amount */
					'per_month'       => esc_html__( 'up to %s per month', 'wc-gocardless-payments' ),
					'sandbox_notice'  => esc_html__( 'Test Mode — no real payments will be taken.', 'wc-gocardless-payments' ),
					'uk_only_notice'  => esc_html__( 'Variable Recurring Payments are available for UK bank accounts only.', 'wc-gocardless-payments' ),
					'aria_label'      => esc_html__( 'GoCardless Variable Recurring Payments', 'wc-gocardless-payments' ),
				),
			)
		);
	}
}
