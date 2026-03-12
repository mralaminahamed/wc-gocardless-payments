<?php
/**
 * Logger Utility
 *
 * Wraps WooCommerce's native WC_Logger with a channel-scoped interface
 * and respects the plugin's debug/logging setting.
 *
 * @package WC_GoCardless_Payments\Utilities
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_GoCardless_Logger
 *
 * Provides PSR-3-inspired log methods (debug, info, warning, error) scoped
 * to the 'wc-gocardless-payments' WC log channel.
 *
 * Logging is only active when the gateway's `debug_logging` setting is
 * enabled, except for `error` level messages which are always written.
 *
 * @since 1.0.0
 */
class WC_GoCardless_Logger {

	/**
	 * WC log channel identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOG_CHANNEL = 'wc-gocardless-payments';

	/**
	 * WooCommerce logger instance.
	 *
	 * @since 1.0.0
	 * @var WC_Logger_Interface|null
	 */
	private ?WC_Logger_Interface $wc_logger = null;

	/**
	 * Whether debug logging is enabled.
	 *
	 * @since 1.0.0
	 * @var bool|null Null until first resolved.
	 */
	private ?bool $debug_enabled = null;

	/**
	 * Log a DEBUG level message.
	 *
	 * Only written when debug logging is enabled in settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( $this->is_debug_enabled() ) {
			$this->log( WC_Log_Levels::DEBUG, $message, $context );
		}
	}

	/**
	 * Log an INFO level message.
	 *
	 * Only written when debug logging is enabled in settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		if ( $this->is_debug_enabled() ) {
			$this->log( WC_Log_Levels::INFO, $message, $context );
		}
	}

	/**
	 * Log a NOTICE level message.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void {
		if ( $this->is_debug_enabled() ) {
			$this->log( WC_Log_Levels::NOTICE, $message, $context );
		}
	}

	/**
	 * Log a WARNING level message.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( WC_Log_Levels::WARNING, $message, $context );
	}

	/**
	 * Log an ERROR level message.
	 *
	 * Error messages are always written regardless of the debug setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		// Errors are always logged irrespective of the debug toggle.
		$this->log( WC_Log_Levels::ERROR, $message, $context );
	}

	/**
	 * Log a CRITICAL level message.
	 *
	 * Critical messages are always written regardless of the debug setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( WC_Log_Levels::CRITICAL, $message, $context );
	}

	/**
	 * Write a log entry to the WooCommerce log system.
	 *
	 * Context data is serialised and appended to the message to keep
	 * WC log entries human-readable in the WooCommerce → Status → Logs screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $level   WC_Log_Levels constant value.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		$logger = $this->get_logger();

		if ( ! empty( $context ) ) {
			// Sanitise context before logging — redact sensitive values.
			$context  = $this->sanitise_context( $context );
			$message .= ' | Context: ' . wp_json_encode( $context );
		}

		$logger->log(
			$level,
			$message,
			array( 'source' => self::LOG_CHANNEL )
		);
	}

	/**
	 * Retrieve or instantiate the WC_Logger_Interface implementation.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_Logger_Interface
	 */
	private function get_logger(): WC_Logger_Interface {
		if ( null === $this->wc_logger ) {
			$this->wc_logger = wc_get_logger();
		}

		return $this->wc_logger;
	}

	/**
	 * Determine whether debug logging is currently enabled.
	 *
	 * Reads from the Direct Debit gateway settings (shared across all variants).
	 * Result is cached per request to avoid repeated option reads.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when logging is enabled.
	 */
	private function is_debug_enabled(): bool {
		if ( null === $this->debug_enabled ) {
			$settings            = get_option( 'woocommerce_gocardless_direct_debit_settings', array() );
			$this->debug_enabled = isset( $settings['debug_logging'] )
				&& 'yes' === $settings['debug_logging'];
		}

		return $this->debug_enabled;
	}

	/**
	 * Sanitise the context array before logging.
	 *
	 * Redacts known sensitive fields (access tokens, bank account numbers,
	 * sort codes) to prevent credentials from appearing in log files.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context Raw context data.
	 * @return array<string, mixed> Sanitised context data.
	 */
	private function sanitise_context( array $context ): array {
		$sensitive_keys = array(
			'access_token',
			'live_access_token',
			'sandbox_access_token',
			'account_number',
			'sort_code',
			'iban',
			'authorization',
			'webhook_secret',
		);

		array_walk_recursive(
			$context,
			static function ( mixed &$value, string $key ) use ( $sensitive_keys ): void {
				if ( in_array( strtolower( $key ), $sensitive_keys, true ) && is_string( $value ) ) {
					$value = '***REDACTED***';
				}
			}
		);

		return $context;
	}
}
