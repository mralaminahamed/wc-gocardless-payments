<?php
/**
 * GoCardless Mandate Payment Token
 *
 * Extends WC_Payment_Token to store GoCardless mandate data as a saved
 * payment method on the customer's WooCommerce account. This enables
 * mandate reuse across orders and subscription renewals without re-authorising.
 *
 * Stored token data:
 *   - mandate_id       : GoCardless mandate ID (e.g. 'MD123ABC')
 *   - scheme           : Direct Debit scheme (bacs_debit, sepa_core, ach, etc.)
 *   - bank_name        : Bank name for display (e.g. 'Barclays')
 *   - account_number_ending : Last 4 digits of the bank account number
 *   - customer_id      : GoCardless customer ID (e.g. 'CU123ABC')
 *   - status           : Mandate status (active, pending_submission, etc.)
 *
 * @package WC_GoCardless_Payments\PaymentToken
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Payment_Token_Mandate
 *
 * @since 1.0.0
 */
class WC_GoCardless_Payment_Token_Mandate extends WC_Payment_Token {

	/**
	 * Token type identifier used by WooCommerce to distinguish token classes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $type = 'gocardless_mandate';

	/**
	 * Extra data fields and their default values.
	 *
	 * These are stored in the `token_data` column via WooCommerce's
	 * payment token meta system.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $extra_data = array(
		'mandate_id'              => '',
		'scheme'                  => '',
		'bank_name'               => '',
		'account_number_ending'   => '',
		'customer_id'             => '',
		'status'                  => 'active',
	);

	/**
	 * Validate the token data before saving.
	 *
	 * Requires a non-empty mandate_id as the minimum valid condition.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when token data is valid.
	 */
	public function validate(): bool {
		if ( false === parent::validate() ) {
			return false;
		}

		return ! empty( $this->get_mandate_id() );
	}

	// -------------------------------------------------------------------------
	// Getters
	// -------------------------------------------------------------------------

	/**
	 * Retrieve the GoCardless mandate ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string
	 */
	public function get_mandate_id( string $context = 'view' ): string {
		return (string) $this->get_prop( 'mandate_id', $context );
	}

	/**
	 * Retrieve the Direct Debit scheme identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string Scheme (e.g. 'bacs_debit', 'sepa_core', 'ach').
	 */
	public function get_scheme( string $context = 'view' ): string {
		return (string) $this->get_prop( 'scheme', $context );
	}

	/**
	 * Retrieve the bank name for display purposes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string Bank name (e.g. 'Barclays').
	 */
	public function get_bank_name( string $context = 'view' ): string {
		return (string) $this->get_prop( 'bank_name', $context );
	}

	/**
	 * Retrieve the last four characters of the bank account number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string Last 4 digits/characters (e.g. '1234').
	 */
	public function get_account_number_ending( string $context = 'view' ): string {
		return (string) $this->get_prop( 'account_number_ending', $context );
	}

	/**
	 * Retrieve the GoCardless customer ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string GoCardless customer ID (e.g. 'CU123ABC').
	 */
	public function get_customer_id( string $context = 'view' ): string {
		return (string) $this->get_prop( 'customer_id', $context );
	}

	/**
	 * Retrieve the mandate status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context View or edit context.
	 * @return string Mandate status.
	 */
	public function get_status( string $context = 'view' ): string {
		return (string) $this->get_prop( 'status', $context );
	}

	// -------------------------------------------------------------------------
	// Setters
	// -------------------------------------------------------------------------

	/**
	 * Set the GoCardless mandate ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @return void
	 */
	public function set_mandate_id( string $mandate_id ): void {
		$this->set_prop( 'mandate_id', sanitize_text_field( $mandate_id ) );
	}

	/**
	 * Set the Direct Debit scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scheme Scheme identifier.
	 * @return void
	 */
	public function set_scheme( string $scheme ): void {
		$this->set_prop( 'scheme', sanitize_text_field( $scheme ) );
	}

	/**
	 * Set the bank name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $bank_name Bank name for display.
	 * @return void
	 */
	public function set_bank_name( string $bank_name ): void {
		$this->set_prop( 'bank_name', sanitize_text_field( $bank_name ) );
	}

	/**
	 * Set the account number ending (last 4 digits/characters).
	 *
	 * @since 1.0.0
	 *
	 * @param string $ending Last 4 digits.
	 * @return void
	 */
	public function set_account_number_ending( string $ending ): void {
		$this->set_prop( 'account_number_ending', sanitize_text_field( $ending ) );
	}

	/**
	 * Set the GoCardless customer ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $customer_id GoCardless customer ID.
	 * @return void
	 */
	public function set_customer_id( string $customer_id ): void {
		$this->set_prop( 'customer_id', sanitize_text_field( $customer_id ) );
	}

	/**
	 * Set the mandate status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Mandate status.
	 * @return void
	 */
	public function set_status( string $status ): void {
		$this->set_prop( 'status', sanitize_text_field( $status ) );
	}

	// -------------------------------------------------------------------------
	// Display helpers
	// -------------------------------------------------------------------------

	/**
	 * Generate the customer-facing display string for this saved mandate.
	 *
	 * Shown in My Account → Payment methods and at checkout when
	 * selecting a previously authorised mandate.
	 *
	 * @since 1.0.0
	 *
	 * @return string HTML-safe display string.
	 */
	public function get_display_name(): string {
		$scheme_label = $this->get_scheme_label();
		$bank         = $this->get_bank_name();
		$ending       = $this->get_account_number_ending();

		if ( ! empty( $bank ) && ! empty( $ending ) ) {
			return sprintf(
				/* translators: 1: Scheme label 2: Bank name 3: Account ending */
				esc_html__( '%1$s — %2$s (account ending %3$s)', 'wc-gocardless-payments' ),
				esc_html( $scheme_label ),
				esc_html( $bank ),
				esc_html( $ending )
			);
		}

		if ( ! empty( $bank ) ) {
			return sprintf(
				/* translators: 1: Scheme label 2: Bank name */
				esc_html__( '%1$s — %2$s', 'wc-gocardless-payments' ),
				esc_html( $scheme_label ),
				esc_html( $bank )
			);
		}

		return sprintf(
			/* translators: %s: Mandate ID */
			esc_html__( 'Direct Debit mandate (%s)', 'wc-gocardless-payments' ),
			esc_html( $this->get_mandate_id() )
		);
	}

	/**
	 * Return a human-readable label for the Direct Debit scheme.
	 *
	 * @since 1.0.0
	 *
	 * @return string Scheme label.
	 */
	public function get_scheme_label(): string {
		$labels = array(
			'bacs_debit'         => __( 'BACS Direct Debit', 'wc-gocardless-payments' ),
			'sepa_core'          => __( 'SEPA Direct Debit', 'wc-gocardless-payments' ),
			'sepa_cor1'          => __( 'SEPA COR1 Direct Debit', 'wc-gocardless-payments' ),
			'ach'                => __( 'ACH Direct Debit', 'wc-gocardless-payments' ),
			'autogiro'           => __( 'Autogiro', 'wc-gocardless-payments' ),
			'becs'               => __( 'BECS Direct Debit', 'wc-gocardless-payments' ),
			'becs_nz'            => __( 'BECS NZ Direct Debit', 'wc-gocardless-payments' ),
			'betalingsservice'   => __( 'Betalingsservice', 'wc-gocardless-payments' ),
			'pad'                => __( 'PAD', 'wc-gocardless-payments' ),
		);

		return $labels[ $this->get_scheme() ] ?? __( 'Direct Debit', 'wc-gocardless-payments' );
	}

	/**
	 * Determine whether this token represents an active, usable mandate.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the mandate can be used for payment collection.
	 */
	public function is_active(): bool {
		return in_array(
			$this->get_status(),
			array( 'active', 'submitted', 'pending_submission' ),
			true
		);
	}

	/**
	 * Create and save a mandate token from a GoCardless mandate object.
	 *
	 * Factory method for converting a raw GoCardless API mandate response
	 * into a persisted WC_Payment_Token record.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $mandate     GoCardless mandate object (from API response).
	 * @param array<string, mixed> $bank_account GoCardless customer bank account object.
	 * @param int                  $wc_user_id  WooCommerce user ID (0 for guests).
	 * @param string               $gateway_id  WooCommerce gateway ID.
	 * @return WC_GoCardless_Payment_Token_Mandate|null Saved token or null on failure.
	 */
	public static function create_from_mandate(
		array $mandate,
		array $bank_account,
		int $wc_user_id,
		string $gateway_id
	): ?WC_GoCardless_Payment_Token_Mandate {
		$mandate_data      = $mandate['mandates'] ?? $mandate;
		$bank_account_data = $bank_account['customer_bank_accounts'] ?? $bank_account;

		if ( empty( $mandate_data['id'] ) ) {
			return null;
		}

		$token = new self();
		$token->set_mandate_id( $mandate_data['id'] );
		$token->set_scheme( $mandate_data['scheme'] ?? '' );
		$token->set_status( $mandate_data['status'] ?? 'active' );
		$token->set_customer_id( $mandate_data['links']['customer'] ?? '' );
		$token->set_token( $mandate_data['id'] ); // WC_Payment_Token requires a token string.
		$token->set_gateway_id( $gateway_id );
		$token->set_user_id( $wc_user_id );

		// Bank account details for display.
		if ( ! empty( $bank_account_data ) ) {
			$token->set_bank_name(
				$bank_account_data['bank_name'] ?? ''
			);
			$token->set_account_number_ending(
				$bank_account_data['account_number_ending'] ?? ''
			);
		}

		if ( $token->validate() ) {
			$token->save();
			return $token;
		}

		return null;
	}

	/**
	 * Find an existing mandate token for a given mandate ID and user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mandate_id GoCardless mandate ID.
	 * @param int    $wc_user_id WooCommerce user ID.
	 * @param string $gateway_id WooCommerce gateway ID.
	 * @return WC_GoCardless_Payment_Token_Mandate|null Existing token or null.
	 */
	public static function find_by_mandate_id(
		string $mandate_id,
		int $wc_user_id,
		string $gateway_id
	): ?WC_GoCardless_Payment_Token_Mandate {
		$tokens = WC_Payment_Tokens::get_customer_tokens( $wc_user_id, $gateway_id );

		foreach ( $tokens as $token ) {
			if (
				$token instanceof WC_GoCardless_Payment_Token_Mandate
				&& $token->get_mandate_id() === $mandate_id
			) {
				return $token;
			}
		}

		return null;
	}
}
