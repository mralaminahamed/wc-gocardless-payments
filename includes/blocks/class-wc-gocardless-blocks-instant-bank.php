<?php
/**
 * Instant Bank Pay Blocks Integration — WC_GoCardless_Blocks_Instant_Bank
 *
 * Registers the GoCardless Instant Bank Pay gateway with the WooCommerce
 * Cart and Checkout blocks.
 *
 * IBP is a one-off open-banking payment — no mandate is saved, no saved
 * token selector is rendered. The component displays the redirect notice
 * and an optional list of supported bank logos.
 *
 * @package WC_GoCardless_Payments\Blocks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Blocks_Instant_Bank
 *
 * @since 1.0.0
 */
class WC_GoCardless_Blocks_Instant_Bank extends WC_GoCardless_Blocks_Integration {

	/**
	 * Unique identifier — must match the gateway ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'gocardless_instant_bank';

	/**
	 * Return server-side data for the Instant Bank Pay React component.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		$data = parent::get_payment_method_data();

		$show_bank_logos = $this->get_setting( 'show_bank_logos', 'yes' );

		return array_merge(
			$data,
			array(
				'gateway_id'      => $this->name,
				'show_bank_logos' => 'yes' === $show_bank_logos,
				'bank_logos'      => $this->get_bank_list(),
				'i18n'            => array(
					'redirect_notice' => esc_html__(
						'You will be redirected to GoCardless to authorise your Instant Bank Pay payment.',
						'wc-gocardless-payments'
					),
					'sandbox_notice'  => esc_html__( 'Test Mode — no real payments will be taken.', 'wc-gocardless-payments' ),
					'supported_banks' => esc_html__( 'Supported banks include:', 'wc-gocardless-payments' ),
					'aria_label'      => esc_html__( 'GoCardless Instant Bank Pay', 'wc-gocardless-payments' ),
				),
			)
		);
	}

	/**
	 * Return the list of supported bank names for display in the checkout block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	private function get_bank_list(): array {
		/**
		 * Filters the list of bank names displayed in the IBP checkout block.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $banks List of bank display names.
		 */
		return (array) apply_filters(
			'wc_gocardless_ibp_bank_list',
			array(
				'Barclays',
				'HSBC',
				'Lloyds',
				'NatWest',
				'Santander',
				'Halifax',
				'Nationwide',
				'Monzo',
				'Starling',
			)
		);
	}
}
