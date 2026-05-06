<?php
/**
 * Option-backed ring buffer logger.
 *
 * Stores the last 50 log events under the `wpfb_log` option. Because this is a
 * high-frequency option (written on every mail attempt), it is NOT autoloaded.
 *
 * @package WPFB
 */

namespace WPFB\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Simple ring-buffer logger persisted in wp_options.
 */
class Logger {

	/**
	 * Maximum number of log entries to retain.
	 */
	const MAX_ENTRIES = 50;

	/**
	 * WP option key for the log buffer.
	 */
	const OPTION_KEY = 'wpfb_log';

	/**
	 * Log a message at the given severity level.
	 *
	 * @param string $level   Severity: 'info', 'warning', 'error'.
	 * @param string $message Human-readable log message.
	 * @param array  $context Optional structured context data.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		$entries = self::read();

		$entries[] = [
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'level'   => $level,
			'message' => $message,
			'context' => $context,
		];

		// Trim to ring-buffer size.
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		// autoload = false — this option is not needed on every page load.
		update_option( self::OPTION_KEY, $entries, false );
	}

	/**
	 * Convenience wrapper for info-level events.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 */
	public static function info( string $message, array $context = [] ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Convenience wrapper for warning-level events.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 */
	public static function warning( string $message, array $context = [] ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Convenience wrapper for error-level events.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 */
	public static function error( string $message, array $context = [] ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Returns all stored log entries, newest first.
	 *
	 * @return array
	 */
	public static function get_entries(): array {
		return array_reverse( self::read() );
	}

	/**
	 * Clears the log buffer.
	 */
	public static function clear(): void {
		update_option( self::OPTION_KEY, [], false );
	}

	/**
	 * Reads the raw log array from the option.
	 *
	 * @return array
	 */
	private static function read(): array {
		$data = get_option( self::OPTION_KEY, [] );

		return is_array( $data ) ? $data : [];
	}
}
