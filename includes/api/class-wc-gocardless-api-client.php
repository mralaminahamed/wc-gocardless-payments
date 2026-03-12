<?php
/**
 * GoCardless API Client
 *
 * Handles all low-level HTTP communication with the GoCardless Pro API (v2).
 * Provides authentication, request signing, response normalisation, error
 * handling, and idempotency key management.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Client
 *
 * Low-level HTTP client for the GoCardless REST API.
 * All endpoint-specific classes (Payments, Mandates, etc.) compose
 * this client via constructor injection.
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Client {

	/**
	 * GoCardless API base URL — live environment.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LIVE_API_URL = 'https://api.gocardless.com';

	/**
	 * GoCardless API base URL — sandbox environment.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SANDBOX_API_URL = 'https://api-sandbox.gocardless.com';

	/**
	 * GoCardless API version header value.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const API_VERSION = '2015-07-06';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const REQUEST_TIMEOUT = 30;

	/**
	 * Whether the client is operating in sandbox (test) mode.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $sandbox_mode;

	/**
	 * GoCardless API access token.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $access_token;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Logger
	 */
	private WC_GoCardless_Logger $logger;

	/**
	 * Idempotency key manager.
	 *
	 * @since 1.0.0
	 * @var WC_GoCardless_Idempotency
	 */
	private WC_GoCardless_Idempotency $idempotency;

	/**
	 * Constructor.
	 *
	 * Credentials are resolved lazily from the gateway settings to avoid
	 * reading options before WooCommerce has fully initialised.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger      = new WC_GoCardless_Logger();
		$this->idempotency = new WC_GoCardless_Idempotency();
		$this->load_credentials();
	}

	/**
	 * Load API credentials from the Direct Debit gateway settings.
	 *
	 * We read from the Direct Debit gateway as it holds the shared API
	 * credentials used across all gateway variants.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_credentials(): void {
		$settings = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );

		$this->sandbox_mode = isset( $settings['sandbox_mode'] )
			&& 'yes' === $settings['sandbox_mode'];

		$token_key          = $this->sandbox_mode ? 'sandbox_access_token' : 'live_access_token';
		$this->access_token = isset( $settings[ $token_key ] )
			? $this->decrypt_token( $settings[ $token_key ] )
			: '';
	}

	/**
	 * Execute an HTTP GET request to the GoCardless API.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $endpoint API endpoint path (e.g. '/payments/PM123').
	 * @param array<string, mixed> $params   Optional query string parameters.
	 * @return array<string, mixed> Parsed JSON response body.
	 * @throws WC_GoCardless_API_Exception On HTTP or API-level errors.
	 */
	public function get( string $endpoint, array $params = array() ): array {
		if ( ! empty( $params ) ) {
			$endpoint .= '?' . http_build_query( $params );
		}

		return $this->request( 'GET', $endpoint );
	}

	/**
	 * Execute an HTTP POST request to the GoCardless API.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $endpoint         API endpoint path.
	 * @param array<string, mixed> $body             Request body (will be JSON-encoded).
	 * @param string|null          $idempotency_key  Optional idempotency key.
	 * @return array<string, mixed> Parsed JSON response body.
	 * @throws WC_GoCardless_API_Exception On HTTP or API-level errors.
	 */
	public function post( string $endpoint, array $body = array(), ?string $idempotency_key = null ): array {
		return $this->request( 'POST', $endpoint, $body, $idempotency_key );
	}

	/**
	 * Execute an HTTP PUT request to the GoCardless API.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $body     Request body.
	 * @return array<string, mixed> Parsed JSON response body.
	 * @throws WC_GoCardless_API_Exception On HTTP or API-level errors.
	 */
	public function put( string $endpoint, array $body = array() ): array {
		return $this->request( 'PUT', $endpoint, $body );
	}

	/**
	 * Core HTTP request dispatcher.
	 *
	 * Builds request arguments, dispatches via wp_remote_request(), handles
	 * WP_Error responses, validates the HTTP status code, and parses the
	 * JSON response body.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $method          HTTP method (GET|POST|PUT).
	 * @param string               $endpoint        API endpoint path.
	 * @param array<string, mixed> $body            Request body for POST/PUT.
	 * @param string|null          $idempotency_key Optional idempotency key.
	 * @return array<string, mixed> Parsed response body.
	 * @throws WC_GoCardless_API_Exception On transport or API errors.
	 */
	private function request(
		string $method,
		string $endpoint,
		array $body = array(),
		?string $idempotency_key = null
	): array {
		$url     = $this->get_api_url() . $endpoint;
		$headers = $this->build_headers( $method, $idempotency_key );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => self::REQUEST_TIMEOUT,
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$this->logger->debug(
			sprintf( '[API] %s %s', $method, $endpoint ),
			array( 'body' => $body )
		);

		$response = wp_remote_request( $url, $args );

		return $this->handle_response( $response, $method, $endpoint );
	}

	/**
	 * Build the HTTP headers required by the GoCardless API.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $method          HTTP method.
	 * @param string|null $idempotency_key Optional idempotency key.
	 * @return array<string, string> Associative headers array.
	 * @throws WC_GoCardless_API_Exception When no access token is configured.
	 */
	private function build_headers( string $method, ?string $idempotency_key = null ): array {
		if ( empty( $this->access_token ) ) {
			throw new WC_GoCardless_API_Exception(
				__( 'GoCardless API access token is not configured.', 'wc-gocardless-payments' ),
				'missing_credentials'
			);
		}

		$headers = array(
			'Authorization'      => 'Bearer ' . $this->access_token,
			'GoCardless-Version' => self::API_VERSION,
			'Content-Type'       => 'application/json',
			'Accept'             => 'application/json',
			'User-Agent'         => $this->get_user_agent(),
		);

		// Attach idempotency key for mutating requests.
		if ( in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$headers['Idempotency-Key'] = $idempotency_key
				?? $this->idempotency->generate();
		}

		return $headers;
	}

	/**
	 * Process the HTTP response from wp_remote_request().
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|WP_Error $response WP HTTP response or WP_Error.
	 * @param string                        $method   HTTP method (for logging).
	 * @param string                        $endpoint Endpoint path (for logging).
	 * @return array<string, mixed> Parsed response data.
	 * @throws WC_GoCardless_API_Exception On transport, HTTP, or API errors.
	 */
	private function handle_response( $response, string $method, string $endpoint ): array {
		// Handle WordPress transport-level errors.
		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				sprintf( '[API] Transport error: %s %s — %s', $method, $endpoint, $response->get_error_message() )
			);

			throw new WC_GoCardless_API_Exception(
				sprintf(
					/* translators: %s: WordPress error message */
					__( 'HTTP transport error: %s', 'wc-gocardless-payments' ),
					$response->get_error_message()
				),
				'transport_error'
			);
		}

		$status_code  = (int) wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $raw_body, true );

		$this->logger->debug(
			sprintf( '[API] Response %d: %s %s', $status_code, $method, $endpoint ),
			array( 'body' => $decoded_body )
		);

		// Successful 2xx response.
		if ( $status_code >= 200 && $status_code < 300 ) {
			return is_array( $decoded_body ) ? $decoded_body : array();
		}

		// API returned a structured error — parse and throw.
		$this->throw_api_exception( $status_code, $decoded_body, $endpoint );
	}

	/**
	 * Parse a GoCardless API error response and throw a normalised exception.
	 *
	 * GoCardless error responses follow the shape:
	 * { "error": { "message": "...", "type": "...", "code": 422, "errors": [...] } }
	 *
	 * @since 1.0.0
	 *
	 * @param int                       $status_code  HTTP status code.
	 * @param array<string, mixed>|null $body         Decoded JSON response body.
	 * @param string                    $endpoint     Endpoint path (for context).
	 * @return never
	 * @throws WC_GoCardless_API_Exception Always throws.
	 */
	private function throw_api_exception( int $status_code, ?array $body, string $endpoint ) {
		$error   = $body['error'] ?? array();
		$message = $error['message'] ?? sprintf( 'Unexpected HTTP %d from %s', $status_code, $endpoint );
		$type    = $error['type'] ?? 'unknown_error';
		$errors  = $error['errors'] ?? array();

		$this->logger->error(
			sprintf( '[API] Error %d (%s): %s', $status_code, $type, $message ),
			array(
				'errors'   => $errors,
				'endpoint' => $endpoint,
			)
		);

		throw new WC_GoCardless_API_Exception( $message, $type, $status_code, $errors );
	}

	/**
	 * Retrieve the appropriate API base URL based on current mode.
	 *
	 * @since 1.0.0
	 *
	 * @return string API base URL without trailing slash.
	 */
	public function get_api_url(): string {
		return $this->sandbox_mode ? self::SANDBOX_API_URL : self::LIVE_API_URL;
	}

	/**
	 * Determine whether the client is in sandbox mode.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when operating against the sandbox environment.
	 */
	public function is_sandbox(): bool {
		return $this->sandbox_mode;
	}

	/**
	 * Reload credentials from saved settings.
	 *
	 * Called after the gateway settings are saved to ensure the client
	 * immediately reflects any token changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function refresh_credentials(): void {
		$this->load_credentials();
	}

	/**
	 * Build the User-Agent string for API requests.
	 *
	 * Includes plugin version and WordPress/WooCommerce context to assist
	 * GoCardless support in identifying integration issues.
	 *
	 * @since 1.0.0
	 *
	 * @return string User-Agent header value.
	 */
	private function get_user_agent(): string {
		return sprintf(
			'WC-GoCardless-Payments/%s WordPress/%s WooCommerce/%s PHP/%s',
			WC_GOCARDLESS_VERSION,
			get_bloginfo( 'version' ),
			defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			PHP_VERSION
		);
	}

	/**
	 * Decrypt a stored access token.
	 *
	 * Tokens are stored encrypted via wc_encrypt(). This method handles
	 * both encrypted and plain-text tokens gracefully for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Raw stored token value.
	 * @return string Decrypted token.
	 */
	private function decrypt_token( string $token ): string {
		// wc_decrypt() is available since WooCommerce 3.x.
		if ( function_exists( 'wc_decrypt' ) && ! empty( $token ) ) {
			$decrypted = wc_decrypt( $token );
			return $decrypted ?: $token;
		}

		return $token;
	}
}
