<?php
/**
 * Admin Webhook Error Email — WC_GoCardless_Email_Admin_Webhook_Error
 *
 * Sends an admin notification when a GoCardless webhook fails to process.
 * This allows the merchant to investigate potential issues with their integration.
 *
 * Triggers:
 *   - Fired by `wc_gocardless_webhook_error` action when webhook
 *     processing fails.
 *
 * Email ID:         wc_gocardless_admin_webhook_error
 * Default heading:  GoCardless Webhook Error
 * Default subject:  [GoCardless] Webhook processing failed — {site_title}
 * Recipient:        Admin (Site Admin email)
 *
 * Template path:    templates/emails/admin-webhook-error.php
 * Override path:    {theme}/woocommerce/emails/admin-webhook-error.php
 *
 * @package WC_GoCardless_Payments\Emails
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Email_Admin_Webhook_Error
 *
 * @since 1.0.0
 */
class WC_GoCardless_Email_Admin_Webhook_Error extends WC_Email {

	/**
	 * The event type that failed.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $event_type = '';

	/**
	 * The error message.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $error_message = '';

	/**
	 * The event payload (limited for security).
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	public array $event_data = array();

	/**
	 * Constructor — configure email properties and bind trigger.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id             = 'wc_gocardless_admin_webhook_error';
		$this->customer_email = false;
		$this->title          = __( 'GoCardless — Admin Webhook Error', 'wc-gocardless-payments' );
		$this->description    = __( 'Sent to the admin when a GoCardless webhook fails to process.', 'wc-gocardless-payments' );

		$this->template_html  = 'emails/admin-webhook-error.php';
		$this->template_plain = 'emails/plain/admin-webhook-error.php';
		$this->template_base  = WC_GOCARDLESS_PATH . 'templates/';

		$this->subject = $this->get_default_subject();
		$this->heading = $this->get_default_heading();

		$this->recipient = get_option( 'admin_email' );

		add_action( 'wc_gocardless_webhook_error', array( $this, 'trigger' ), 10, 4 );

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
		return __( '[GoCardless] Webhook processing failed — {site_title}', 'wc-gocardless-payments' );
	}

	/**
	 * Return the default email heading.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'GoCardless Webhook Error', 'wc-gocardless-payments' );
	}

	/**
	 * Trigger the email for a webhook error.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $event_type    The GoCardless event type.
	 * @param string               $error_message The error message.
	 * @param array<string, mixed> $event_data    The event payload.
	 * @param string|null          $order_id      Related order ID if available.
	 * @return void
	 */
	public function trigger(
		string $event_type,
		string $error_message,
		array $event_data,
		?string $order_id = null
	): void {
		$this->setup_locale();

		if ( ! $this->is_enabled() ) {
			$this->restore_locale();
			return;
		}

		$this->event_type    = $event_type;
		$this->error_message = $error_message;
		$this->event_data    = $this->sanitize_event_data( $event_data );
		$this->recipient     = get_option( 'admin_email' );

		if ( ! $this->recipient ) {
			$this->restore_locale();
			return;
		}

		$this->placeholders['{event_type}']    = $event_type;
		$this->placeholders['{order_id}']      = $order_id ?? 'N/A';
		$this->placeholders['{error_message}'] = $error_message;

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
	 * Sanitize event data for email to remove sensitive information.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $event_data Raw event data.
	 * @return array<string, mixed> Sanitized event data.
	 */
	private function sanitize_event_data( array $event_data ): array {
		$sanitized = $event_data;

		$sensitive_keys = array(
			'account_number',
			'bank_code',
			'bic',
			'iban',
			'sort_code',
			'customer_bank_account',
			'mandate_reference',
		);

		foreach ( $sensitive_keys as $key ) {
			if ( isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = '***REDACTED***';
			}

			if ( isset( $sanitized['links'][ $key ] ) ) {
				$sanitized['links'][ $key ] = '***REDACTED***';
			}
		}

		return $sanitized;
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
				'event_type'    => $this->event_type,
				'error_message' => $this->error_message,
				'event_data'    => $this->event_data,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
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
				'event_type'    => $this->event_type,
				'error_message' => $this->error_message,
				'event_data'    => $this->event_data,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => true,
				'plain_text'    => true,
				'email'         => $this,
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

		$this->form_fields['include_event_data'] = array(
			'title'   => __( 'Include Event Data', 'wc-gocardless-payments' ),
			'type'    => 'checkbox',
			'label'   => __( 'Include sanitized event data in the notification', 'wc-gocardless-payments' ),
			'default' => 'yes',
		);

		$this->form_fields['error_threshold'] = array(
			'title'    => __( 'Error Notification Threshold', 'wc-gocardless-payments' ),
			'type'     => 'number',
			'desc'     => __( 'Minimum number of consecutive errors before sending notification (0 = every error)', 'wc-gocardless-payments' ),
			'default'  => '1',
			'desc_tip' => true,
		);
	}
}
