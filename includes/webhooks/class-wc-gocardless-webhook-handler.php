<?php
/**
 * Webhook Handler — WC_GoCardless_Webhook_Handler
 *
 * Registers the WooCommerce API webhook endpoint, verifies the
 * GoCardless webhook signature, and dispatches events to the processor.
 *
 * Endpoint: {site_url}/wc-api/wc_gocardless_webhook/
 *
 * @package WC_GoCardless_Payments\Webhooks
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Webhook_Handler
 *
 * @since 1.0.0
 */
class WC_GoCardless_Webhook_Handler {

	/**
	 * WC API endpoint key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ENDPOINT = 'wc_gocardless_webhook';

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger
	 */
	private WC_GoCardless_Logger $logger;

	/**
	 * Constructor — register the WC API endpoint hook.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = wc_gocardless_payments()->logger;

		// Register the WC API listener.
		add_action( 'woocommerce_api_' . self::ENDPOINT, array( $this, 'handle_request' ) );
	}

	/**
	 * Handle an incoming webhook request.
	 *
	 * Execution flow:
	 *   1. Read and validate the raw request body.
	 *   2. Verify the HMAC-SHA256 signature header.
	 *   3. Decode the JSON payload.
	 *   4. Dispatch each event to the processor.
	 *   5. Respond with 200 OK or an appropriate error status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_request(): void {
		// Must be a POST request.
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$this->respond( 405, 'Method Not Allowed' );
			return;
		}

		$raw_body  = file_get_contents( 'php://input' );
		$signature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';

		if ( empty( $raw_body ) ) {
			$this->logger->warning( '[Webhook] Empty request body received.' );
			$this->respond( 400, 'Empty body' );
			return;
		}

		// Verify the webhook signature.
		if ( ! $this->verify_signature( $raw_body, $signature ) ) {
			$this->logger->error( '[Webhook] Signature verification failed.' );
			$this->respond( 498, 'Invalid signature' );
			return;
		}

		$payload = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() || empty( $payload['events'] ) ) {
			$this->logger->warning( '[Webhook] Invalid or empty payload.' );
			$this->respond( 400, 'Invalid payload' );
			return;
		}

		$this->logger->info(
			sprintf( '[Webhook] Received %d event(s).', count( $payload['events'] ) )
		);

		// Process each event in the payload.
		$processor = new WC_GoCardless_Webhook_Processor();

		foreach ( $payload['events'] as $event ) {
			try {
				$processor->process( $event );
			} catch ( Throwable $e ) {
				// Log but do not abort; GoCardless expects a 200 to stop retries.
				$this->logger->error(
					sprintf(
						'[Webhook] Error processing event %s (%s): %s',
						$event['id'] ?? 'unknown',
						$event['action'] ?? 'unknown',
						$e->getMessage()
					)
				);
			}
		}

		$this->respond( 200, 'OK' );
	}

	/**
	 * Verify the GoCardless webhook signature.
	 *
	 * GoCardless signs webhook payloads with an HMAC-SHA256 of the raw
	 * request body using the webhook secret as the key. The signature is
	 * transmitted in the `Webhook-Signature` HTTP header.
	 *
	 * Uses hash_equals() to prevent timing attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw_body  Raw HTTP request body.
	 * @param string $signature Value of the Webhook-Signature header.
	 * @return bool True when the signature is valid.
	 */
	private function verify_signature( string $raw_body, string $signature ): bool {
		$secret = $this->get_webhook_secret();

		if ( empty( $secret ) ) {
			$this->logger->warning( '[Webhook] No webhook secret configured — skipping signature verification.' );
			// Allow through in sandbox mode only; reject in live mode.
			return $this->is_sandbox_mode();
		}

		if ( empty( $signature ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Retrieve the webhook secret from gateway settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string Webhook secret or empty string.
	 */
	private function get_webhook_secret(): string {
		$settings = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );
		return (string) ( $settings['webhook_secret'] ?? '' );
	}

	/**
	 * Determine whether the plugin is currently in sandbox mode.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_sandbox_mode(): bool {
		$settings = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );
		return isset( $settings['sandbox_mode'] ) && 'yes' === $settings['sandbox_mode'];
	}

	/**
	 * Send an HTTP response and terminate execution.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $message     Response body message.
	 * @return never
	 */
	private function respond( int $status_code, string $message ) {
		http_response_code( $status_code );
		header( 'Content-Type: text/plain' );
		echo esc_html( $message );
		exit;
	}
}
