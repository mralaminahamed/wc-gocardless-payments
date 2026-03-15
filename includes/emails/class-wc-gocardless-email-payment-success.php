<?php
/**
 * Payment Success Email — WC_GoCardless_Email_Payment_Success
 *
 * Sends a customer-facing email when a GoCardless payment is successfully
 * confirmed. This provides the customer with confirmation that their payment
 * has been processed.
 *
 * Triggers:
 *   - Fired by `wc_gocardless_payment_confirmed` action when a payment
 *     status changes to "confirmed".
 *
 * Email ID:         wc_gocardless_payment_success
 * Default heading:  Payment received
 * Default subject:  Your payment is confirmed — Order {order_number}
 * Recipient:        Customer (billing email)
 *
 * Template path:    templates/emails/payment-success.php
 * Override path:    {theme}/woocommerce/emails/payment-success.php
 *
 * @package WC_GoCardless_Payments\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Email_Payment_Success
 *
 * @since 1.0.0
 */
class WC_GoCardless_Email_Payment_Success extends WC_Email {

	/**
	 * The GoCardless payment ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $payment_id = '';

	/**
	 * Constructor — configure email properties and bind trigger.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wc_gocardless_payment_success';
		$this->customer_email = true;
		$this->title          = __( 'GoCardless — Payment Success', 'wc-gocardless-payments' );
		$this->description    = __( 'Sent to the customer when their GoCardless payment is successfully confirmed.', 'wc-gocardless-payments' );

		$this->template_html  = 'emails/payment-success.php';
		$this->template_plain = 'emails/plain/payment-success.php';
		$this->template_base  = WC_GOCARDLESS_PATH . 'templates/';

		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		add_action( 'wc_gocardless_payment_confirmed', array( $this, 'trigger' ), 10, 3 );

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
		return __( 'Your payment is confirmed — Order {order_number}', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Payment received', 'wc-gocardless-payments' );
	}

	/**
	 * Trigger the email for a confirmed payment.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order      WooCommerce order.
	 * @param array<string, mixed> $payment    GoCardless payment object.
	 * @param string               $payment_id GoCardless payment ID.
	 * @return void
	 */
	public function trigger(
		WC_Order $order,
		array $payment,
		string $payment_id
	): void {
		$this->setup_locale();

		if ( ! $this->is_enabled() ) {
			$this->restore_locale();
			return;
		}

		$this->object     = $order;
		$this->payment_id = $payment_id;
		$this->recipient  = $order->get_billing_email();

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
				'order'         => $this->object,
				'payment_id'    => $this->payment_id,
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
				'payment_id'    => $this->payment_id,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
