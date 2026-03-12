<?php
/**
 * Abstract Blocks Integration — WC_GoCardless_Blocks_Integration
 *
 * Base class for registering GoCardless payment methods with the
 * WooCommerce Cart and Checkout blocks (Gutenberg-based checkout).
 *
 * Architecture:
 *   WooCommerce Blocks discovers payment method integrations via the
 *   `__experimentalRegisterPaymentMethod` JS API (frontend) and via
 *   the `woocommerce_blocks_payment_method_type_registration` action
 *   (backend). The PHP class provides server-side configuration data
 *   (gateway settings, saved token data, sandbox state) that is
 *   serialised and passed to the React component via `get_payment_method_data()`.
 *
 * Each concrete subclass covers one gateway variant (DD / IBP / VRP).
 * They share a single compiled JS bundle (`blocks/index.min.js`) that
 * self-registers all three methods via the Blocks registry.
 *
 * WC Blocks compatibility: WooCommerce 8.0+ / Blocks 11.0+
 *
 * @package WC_GoCardless_Payments\Blocks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_GoCardless_Blocks_Integration
 *
 * @since 1.0.0
 */
abstract class WC_GoCardless_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * The WooCommerce gateway instance this integration wraps.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Gateway|null
	 */
	protected ?WC_GoCardless_Gateway $gateway = null;

	/**
	 * Initialize — load gateway settings so data is available when
	 * `get_payment_method_data()` is called.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( isset( $gateways[ $this->name ] ) ) {
			$this->gateway  = $gateways[ $this->name ];
			$this->settings = $this->gateway->settings;
		}
	}

	/**
	 * Determine whether this payment method is active and should be offered
	 * in the Blocks checkout.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the gateway is enabled and credentials are set.
	 */
	public function is_active(): bool {
		if ( ! $this->gateway instanceof WC_GoCardless_Gateway ) {
			return false;
		}

		return $this->gateway->is_available();
	}

	/**
	 * Return the script handles that must be enqueued for this payment method.
	 *
	 * All three GoCardless methods share a single compiled JS bundle to
	 * minimise HTTP requests. The bundle registers each payment method
	 * component independently via `@woocommerce/blocks-registry`.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of registered script handles.
	 */
	public function get_payment_method_script_handles(): array {
		$this->register_blocks_script();

		return array( 'wc-gocardless-blocks' );
	}

	/**
	 * Return the script handles needed in the block editor (admin).
	 *
	 * The same bundle is used in both frontend and editor contexts because
	 * the WC Blocks payment method editor preview renders the same component.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function get_payment_method_editor_script_handles(): array {
		$this->register_blocks_script();

		return array( 'wc-gocardless-blocks' );
	}

	/**
	 * Return server-side data to be passed to the JS payment method component.
	 *
	 * This data is serialised into the page source as JSON and made available
	 * to the React component as `props.paymentMethodData` (via the WC Blocks
	 * `getSetting()` helper under the key `{gateway_id}_data`).
	 *
	 * Concrete subclasses should call `parent::get_payment_method_data()` and
	 * merge gateway-specific data on top.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Data to pass to the JS component.
	 */
	public function get_payment_method_data(): array {
		$gateway = $this->gateway;

		$saved_tokens     = array();
		$is_valid_gateway = $gateway instanceof WC_GoCardless_Gateway;

		// Pass saved payment tokens (mandate tokens) for logged-in users.
		if ( $is_valid_gateway && is_user_logged_in() ) {
			$saved_tokens = $this->get_serialized_saved_tokens();
		}

		return array(
			'title'           => $is_valid_gateway ? $gateway->get_title() : '',
			'description'     => $is_valid_gateway ? $gateway->get_description() : '',
			'supports'        => $is_valid_gateway ? array_values( $gateway->supports ) : array(),
			'is_sandbox'      => $is_valid_gateway && $gateway->is_sandbox(),
			'sandbox_label'   => esc_html__( 'Test Mode', 'wc-gocardless-payments' ),
			'redirect_notice' => esc_html__(
				'You will be redirected to GoCardless to authorise your payment.',
				'wc-gocardless-payments'
			),
			'saved_tokens'    => $saved_tokens,
			'icons'           => $this->get_icons(),
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'wc-gocardless-blocks-' . $this->name ),
		);
	}

	// -------------------------------------------------------------------------
	// Protected helpers
	// -------------------------------------------------------------------------

	/**
	 * Register the shared blocks JS bundle if not already registered.
	 *
	 * Uses `SCRIPT_DEBUG` to serve source vs minified file, matching the
	 * pattern used by the classic checkout enqueue logic.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_blocks_script(): void {
		if ( wp_script_is( 'wc-gocardless-blocks', 'registered' ) ) {
			return;
		}

		wp_register_script(
			'wc-gocardless-blocks',
			WC_GOCARDLESS_URL . 'assets/js/blocks/index.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			WC_GOCARDLESS_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'wc-gocardless-blocks',
				'wc-gocardless-payments',
				WC_GOCARDLESS_PATH . 'languages'
			);
		}
	}

	/**
	 * Return serialized saved payment tokens for the current user.
	 *
	 * Formats mandate tokens for consumption by the React component so it
	 * can render a "Use saved mandate" UI without a PHP round-trip.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> Array of token data objects.
	 */
	protected function get_serialized_saved_tokens(): array {
		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			return array();
		}

		$tokens     = WC_Payment_Tokens::get_customer_tokens( $customer_id, $this->name );
		$serialized = array();

		foreach ( $tokens as $token ) {
			if ( ! ( $token instanceof WC_GoCardless_Payment_Token_Mandate ) ) {
				continue;
			}

			if ( ! $token->is_active() ) {
				continue;
			}

			$serialized[] = array(
				'tokenId'       => (string) $token->get_id(),
				'mandateId'     => $token->get_mandate_id(),
				'displayName'   => $token->get_display_name(),
				'scheme'        => $token->get_scheme(),
				'bankName'      => $token->get_bank_name(),
				'accountEnding' => $token->get_account_number_ending(),
				'isDefault'     => $token->is_default(),
			);
		}

		return $serialized;
	}

	/**
	 * Return icon data for this gateway.
	 *
	 * Subclasses may override to return an array of icon URLs/labels.
	 * Returns an empty array by default so the component renders no icons.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function get_icons(): array {
		return array(
			array(
				'url' => WC_GOCARDLESS_URL . 'assets/images/gocardless-logo.svg',
				'alt' => esc_html__( 'GoCardless', 'wc-gocardless-payments' ),
			),
		);
	}
}
