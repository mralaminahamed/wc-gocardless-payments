<?php
/**
 * GoCardless API Exception
 *
 * Normalised exception for all GoCardless API errors.
 * Carries the error type, HTTP status code, and the full
 * errors array from the GoCardless error response.
 *
 * @package WC_GoCardless_Payments\API
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_API_Exception
 *
 * Normalised exception for all GoCardless API errors.
 * Carries the error type, HTTP status code, and the full
 * errors array from the GoCardless error response.
 *
 * @since 1.0.0
 */
class WC_GoCardless_API_Exception extends RuntimeException {

	/**
	 * GoCardless error type identifier (e.g. 'invalid_api_usage', 'validation_failed').
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $error_type;

	/**
	 * HTTP status code returned by the API.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $http_status;

	/**
	 * Array of granular error objects from the 'errors' key.
	 *
	 * @since 1.0.0
	 * @var array<int, array<string, mixed>>
	 */
	private array $api_errors;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                           $message     Human-readable error message.
	 * @param string                           $error_type  GoCardless error type.
	 * @param int                              $http_status HTTP status code (0 for transport errors).
	 * @param array<int, array<string, mixed>> $api_errors  Granular API error objects.
	 */
	public function __construct(
		string $message,
		string $error_type = 'unknown_error',
		int $http_status = 0,
		array $api_errors = array()
	) {
		parent::__construct( $message, $http_status );

		$this->error_type  = $error_type;
		$this->http_status = $http_status;
		$this->api_errors  = $api_errors;
	}

	/**
	 * Retrieve the GoCardless error type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_error_type(): string {
		return $this->error_type;
	}

	/**
	 * Retrieve the HTTP status code.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Retrieve the granular API error objects.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_api_errors(): array {
		return $this->api_errors;
	}

	/**
	 * Determine whether this is a retryable error.
	 *
	 * Rate-limit (429) and server-side (5xx) errors are considered retryable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_retryable(): bool {
		return in_array( $this->http_status, array( 429, 500, 502, 503, 504 ), true );
	}
}
