<?php
/**
 * Payment Failed Email — WC_GoCardless_Email_Payment_Failed
 *
 * Sends a customer-facing email when a GoCardless payment fails. This informs
 * the customer that their payment was not successful and they need to take action.
 *
 * Triggers:
 *   - Fired by `wc_gocardless_payment_failed` action when a payment
 *     status changes to "failed".
 *
 * Email ID:         wc_gocardless_payment_failed
 * Default heading:  Payment failed
 * Default subject:  Payment failed for Order {order_number}
 * Recipient:        Customer (billing email)
 *
 * Template path:    templates/emails/payment-failed.php
 * Override path:    {theme}/woocommerce/emails/payment-failed.php
 *
 * @package WC_GoCardless_Payments\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Email_Payment_Failed
 *
 * @since 1.0.0
 */
class WC_GoCardless_Email_Payment_Failed extends WC_Email {

	/**
	 * The GoCardless payment ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $payment_id = '';

	/**
	 * The failure reason.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $failure_reason = '';

	/**
	 * Constructor — configure email properties and bind trigger.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wc_gocardless_payment_failed';
		$this->customer_email = true;
		$this->title          = __( 'GoCardless — Payment Failed', 'wc-gocardless-payments' );
		$this->description    = __( 'Sent to the customer when their GoCardless payment fails.', 'wc-gocardless-payments' );

		$this->template_html  = 'emails/payment-failed.php';
		$this->template_plain = 'emails/plain/payment-failed.php';
		$this->template_base  = WC_GOCARDLESS_PATH . 'templates/';

		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		add_action( 'wc_gocardless_payment_failed', array( $this, 'trigger' ), 10, 4 );

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
		return __( 'Payment failed for Order {order_number}', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Payment failed', 'wc-gocardless-payments' );
	}

	/**
	 * Trigger the email for a failed payment.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order          WooCommerce order.
	 * @param array<string, mixed> $payment       GoCardless payment object.
	 * @param string               $payment_id     GoCardless payment ID.
	 * @param string               $failure_reason Human-readable failure reason.
	 * @return void
	 */
	public function trigger(
		WC_Order $order,
		array $payment,
		string $payment_id,
		string $failure_reason = ''
	): void {
		$this->setup_locale();

		if ( ! $this->is_enabled() ) {
			$this->restore_locale();
			return;
		}

		$this->object         = $order;
		$this->payment_id     = $payment_id;
		$this->failure_reason = $failure_reason;
		$this->recipient      = $order->get_billing_email();

		if ( ! $this->recipient ) {
			$this->restore_locale();
			return;
		}

		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{payment_id}']   = $payment_id;

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
				'order'          => $this->object,
				'payment_id'     => $this->payment_id,
				'failure_reason' => $this->failure_reason,
				'email_heading'  => $this->get_heading(),
				'sent_to_admin'  => false,
				'plain_text'     => false,
				'email'          => $this,
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
				'order'          => $this->object,
				'payment_id'     => $this->payment_id,
				'failure_reason' => $this->failure_reason,
				'email_heading'  => $this->get_heading(),
				'sent_to_admin'  => false,
				'plain_text'     => true,
				'email'          => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Return additional email settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();

		$this->form_fields['failure_instructions'] = array(
			'title'   => __( 'Include Failure Instructions', 'wc-gocardless-payments' ),
			'type'    => 'checkbox',
			'label'   => __( 'Include instructions for the customer on how to resolve the payment issue', 'wc-gocardless-payments' ),
			'default' => 'yes',
		);
	}
}
