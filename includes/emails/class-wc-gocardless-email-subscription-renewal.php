<?php
/**
 * Subscription Renewal Email — WC_GoCardless_Email_Subscription_Renewal
 *
 * Sends a customer-facing email when a subscription renewal payment is successfully
 * processed via GoCardless. This reassures the customer that their recurring payment
 * has been collected.
 *
 * Triggers:
 *   - Fired by `wc_gocardless_subscription_renewal_processed` action when
 *     a renewal payment is confirmed.
 *
 * Email ID:         wc_gocardless_subscription_renewal
 * Default heading:  Subscription payment received
 * Default subject:  Your subscription payment is confirmed — {site_title}
 * Recipient:        Customer (billing email)
 *
 * Template path:    templates/emails/subscription-renewal.php
 * Override path:    {theme}/woocommerce/emails/subscription-renewal.php
 *
 * @package WC_GoCardless_Payments\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Email_Subscription_Renewal
 *
 * @since 1.0.0
 */
class WC_GoCardless_Email_Subscription_Renewal extends WC_Email {

	/**
	 * The GoCardless payment ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $payment_id = '';

	/**
	 * The subscription ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $subscription_id = '';

	/**
	 * Constructor — configure email properties and bind trigger.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wc_gocardless_subscription_renewal';
		$this->customer_email = true;
		$this->title          = __( 'GoCardless — Subscription Renewal', 'wc-gocardless-payments' );
		$this->description    = __( 'Sent to the customer when a subscription renewal payment is successfully processed via GoCardless.', 'wc-gocardless-payments' );

		$this->template_html  = 'emails/subscription-renewal.php';
		$this->template_plain = 'emails/plain/subscription-renewal.php';
		$this->template_base  = WC_GOCARDLESS_PATH . 'templates/';

		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		add_action( 'wc_gocardless_subscription_renewal_processed', array( $this, 'trigger' ), 10, 4 );

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
		return __( 'Your subscription payment is confirmed — {site_title}', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Subscription payment received', 'wc-gocardless-payments' );
	}

	/**
	 * Trigger the email for a subscription renewal.
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order             $order            WooCommerce order.
	 * @param WC_Subscription      $subscription     WooCommerce subscription.
	 * @param array<string, mixed> $payment         GoCardless payment object.
	 * @param string               $payment_id      GoCardless payment ID.
	 * @return void
	 */
	public function trigger(
		WC_Order $order,
		$subscription,
		array $payment,
		string $payment_id
	): void {
		$this->setup_locale();

		if ( ! $this->is_enabled() ) {
			$this->restore_locale();
			return;
		}

		$this->object          = $order;
		$this->payment_id      = $payment_id;
		$this->subscription_id = $subscription ? $subscription->get_id() : '';
		$this->recipient       = $order->get_billing_email();

		if ( ! $this->recipient ) {
			$this->restore_locale();
			return;
		}

		$this->placeholders['{order_number}']    = $order->get_order_number();
		$this->placeholders['{order_date}']      = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{payment_id}']      = $payment_id;
		$this->placeholders['{subscription_id}'] = $this->subscription_id;

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
				'order'           => $this->object,
				'payment_id'      => $this->payment_id,
				'subscription_id' => $this->subscription_id,
				'email_heading'   => $this->get_heading(),
				'sent_to_admin'   => false,
				'plain_text'      => false,
				'email'           => $this,
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
				'order'           => $this->object,
				'payment_id'      => $this->payment_id,
				'subscription_id' => $this->subscription_id,
				'email_heading'   => $this->get_heading(),
				'sent_to_admin'   => false,
				'plain_text'      => true,
				'email'           => $this,
			),
			'',
			$this->template_base
		);
	}
}
