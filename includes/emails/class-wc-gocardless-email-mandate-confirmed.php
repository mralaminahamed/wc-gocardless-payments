<?php
/**
 * Mandate Confirmed Email — WC_GoCardless_Email_Mandate_Confirmed
 *
 * Sends a customer-facing email when a GoCardless Direct Debit mandate or
 * VRP consent has been successfully authorised. This reassures the customer
 * that their bank account is now linked and that future collections will
 * proceed automatically.
 *
 * Triggers:
 *   - Direct Debit: fired by the `wc_gocardless_billing_request_fulfilled`
 *     action hook when a Billing Request is fulfilled on return from the
 *     GoCardless hosted flow.
 *   - VRP: fired by the same hook when a VRP consent Billing Request is
 *     fulfilled (mandate authorised, VRP consent active).
 *
 * Email ID:         wc_gocardless_mandate_confirmed
 * Default heading:  Your payment mandate is confirmed
 * Default subject:  Your direct debit mandate has been set up — {site_title}
 * Recipient:        Customer (billing email)
 *
 * Template path:    templates/emails/mandate-confirmation.php
 * Override path:    {theme}/woocommerce/emails/mandate-confirmation.php
 *
 * @package WC_GoCardless_Payments\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Email_Mandate_Confirmed
 *
 * @since 1.0.0
 */
class WC_GoCardless_Email_Mandate_Confirmed extends WC_Email {

	/**
	 * The GoCardless mandate ID to include in the email.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $mandate_id = '';

	/**
	 * The payment method type ('direct_debit' or 'vrp').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $payment_type = '';

	/**
	 * Constructor — configure email properties and bind trigger.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wc_gocardless_mandate_confirmed';
		$this->customer_email = true;
		$this->title          = __( 'GoCardless — Mandate/Consent Confirmed', 'wc-gocardless-payments' );
		$this->description    = __( 'Sent to the customer when their GoCardless Direct Debit mandate or VRP consent is successfully authorised.', 'wc-gocardless-payments' );

		// Template paths.
		$this->template_html  = 'emails/mandate-confirmation.php';
		$this->template_plain = 'emails/plain/mandate-confirmation.php';
		$this->template_base  = WC_GOCARDLESS_PATH . 'templates/';

		// Default subject and heading.
		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		// Bind the trigger action.
		add_action( 'wc_gocardless_billing_request_fulfilled', array( $this, 'trigger' ), 10, 4 );

		// Call parent constructor — this loads settings from the DB.
		parent::__construct();
	}

	/**
	 * Return the default email subject.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your Direct Debit mandate has been set up — {site_title}', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Your payment mandate is confirmed', 'wc-gocardless-payments' );
	}

	/**
	 * Trigger the email for a fulfilled Billing Request.
	 *
	 * Called by the `wc_gocardless_billing_request_fulfilled` hook fired
	 * from `WC_GoCardless_Redirect::handle_fulfilled_return()`.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order           WooCommerce order.
	 * @param array<string, mixed> $billing_request GoCardless Billing Request object.
	 * @param string               $mandate_id      GoCardless mandate ID (empty for IBP-only).
	 * @param string               $payment_id      GoCardless payment ID.
	 * @return void
	 */
	public function trigger(
		WC_Order $order,
		array $billing_request,
		string $mandate_id,
		string $payment_id
	): void {
		$this->setup_locale();

		// Only send when a mandate was actually created.
		if ( empty( $mandate_id ) ) {
			$this->restore_locale();
			return;
		}

		// IBP-only flows do not create mandates — skip.
		$payment_method_meta = $billing_request['metadata']['payment_method'] ?? '';
		if ( 'instant_bank_pay' === $payment_method_meta && empty( $billing_request['links']['mandate'] ?? '' ) ) {
			$this->restore_locale();
			return;
		}

		// Check this email type is enabled.
		if ( ! $this->is_enabled() ) {
			$this->restore_locale();
			return;
		}

		$this->object       = $order;
		$this->mandate_id   = $mandate_id;
		$this->payment_type = WC_GoCardless_Order_Helper::get_payment_type( $order );
		$this->recipient    = $order->get_billing_email();

		if ( ! $this->recipient ) {
			$this->restore_locale();
			return;
		}

		// Merge subject/heading placeholders.
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{mandate_id}']   = $mandate_id;

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();
	}

	/**
	 * Return the HTML content for the email.
	 *
	 * @since 1.0.0
	 *
	 * @return string HTML email body.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'mandate_id'    => $this->mandate_id,
				'payment_type'  => $this->payment_type,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Return the plain-text content for the email.
	 *
	 * @since 1.0.0
	 *
	 * @return string Plain-text email body.
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'mandate_id'    => $this->mandate_id,
				'payment_type'  => $this->payment_type,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Return additional email settings fields for the WooCommerce email settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		// Call parent to get base fields (enabled, subject, heading, etc.).
		parent::init_form_fields();

		// Append a note about IBP exclusion for merchant clarity.
		$this->form_fields['include_mandate_reference'] = array(
			'title'   => __( 'Include Mandate Reference', 'wc-gocardless-payments' ),
			'type'    => 'checkbox',
			'label'   => __( 'Include the GoCardless mandate reference number in the email body', 'wc-gocardless-payments' ),
			'default' => 'yes',
		);
	}
}
