<?php
/**
 * Direct Debit Blocks Integration — WC_GoCardless_Blocks_Direct_Debit
 *
 * Registers the GoCardless Direct Debit gateway with the WooCommerce
 * Cart and Checkout blocks.
 *
 * Server-side data passed to the JS component:
 *   - Standard data from parent: title, description, sandbox flag, saved tokens.
 *   - DD-specific: preferred_scheme, mandate-save setting, token UI strings.
 *
 * Saved tokens (mandates):
 *   When the customer has stored mandate tokens, the React component
 *   renders a saved-method selector (token radio list + "Use new mandate"
 *   option) before the redirect notice. Token selection is passed in
 *   `paymentData['token_id']` to `process_payment()` server-side.
 *
 * @package WC_GoCardless_Payments\Blocks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Blocks_Direct_Debit
 *
 * @since 1.0.0
 */
class WC_GoCardless_Blocks_Direct_Debit extends WC_GoCardless_Blocks_Integration {

	/**
	 * Unique identifier — must match the gateway ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $name = 'gocardless_direct_debit';

	/**
	 * Return server-side data for the Direct Debit React component.
	 *
	 * Merges parent data with Direct Debit-specific configuration:
	 * the preferred payment scheme and whether mandate-save is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		$data = parent::get_payment_method_data();

		$preferred_scheme = $this->get_setting( 'preferred_scheme', '' );
		$save_mandate     = $this->get_setting( 'save_mandate', 'yes' );

		return array_merge(
			$data,
			array(
				'gateway_id'       => $this->name,
				'preferred_scheme' => $preferred_scheme,
				'save_mandate'     => 'yes' === $save_mandate,
				'show_save_option' => is_user_logged_in() && 'yes' === $save_mandate,
				'i18n'             => array(
					'use_new_mandate' => esc_html__( 'Use a new bank account', 'wc-gocardless-payments' ),
					'saved_mandate'   => esc_html__( 'Use saved mandate:', 'wc-gocardless-payments' ),
					'save_mandate'    => esc_html__( 'Save this bank account for future payments', 'wc-gocardless-payments' ),
					'redirect_notice' => esc_html__(
						'You will be redirected to GoCardless to authorise your Direct Debit mandate.',
						'wc-gocardless-payments'
					),
					'sandbox_notice'  => esc_html__( 'Test Mode — no real payments will be taken.', 'wc-gocardless-payments' ),
					'aria_label'      => esc_html__( 'GoCardless Direct Debit', 'wc-gocardless-payments' ),
				),
			)
		);
	}
}
