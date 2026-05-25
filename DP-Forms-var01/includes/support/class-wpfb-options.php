<?php
/**
 * Typed wrapper around get_option/update_option for wpfb_settings.
 *
 * The Brevo API key is stored as plaintext in the wpfb_settings option, which
 * is standard practice for WordPress plugins that integrate third-party APIs.
 * For stronger protection, define WPFB_BREVO_API_KEY in wp-config.php — when
 * present it takes precedence over the stored value and the key never touches
 * the database. See get_api_key().
 *
 * @package WPFB
 */

namespace WPFB\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Manages all plugin settings stored under the `wpfb_settings` option key.
 */
class Options {

	/**
	 * The WP option name that holds all plugin settings.
	 */
	const OPTION_KEY = 'wpfb_settings';

	/**
	 * Defaults used when the option does not yet exist or keys are missing.
	 *
	 * @var array
	 */
	private static $defaults = [
		'brevo_api_key'           => '',
		'from_email'              => '',
		'from_name'               => '',
		'global_admin_emails'     => '',
		'allowed_mime_types'      => [ 'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx' ],
		'max_upload_mb'           => 5,
		'delete_data_on_uninstall' => false,
	];

	/**
	 * Returns the full settings array, merged with defaults.
	 *
	 * @return array
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		return array_merge( self::$defaults, $saved );
	}

	/**
	 * Persists the full settings array.
	 *
	 * @param array $settings The settings to save. Should already be sanitized.
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Returns the defaults array (used by Activator to seed the option).
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return self::$defaults;
	}

	/**
	 * Returns the effective Brevo API key.
	 *
	 * Precedence:
	 *   1. The WPFB_BREVO_API_KEY constant. Define it in wp-config.php to keep
	 *      the key out of the database entirely — the more secure option.
	 *   2. The plaintext value stored in the wpfb_settings option.
	 *
	 * @return string The API key, or empty string if none is configured.
	 */
	public static function get_api_key(): string {
		if ( defined( 'WPFB_BREVO_API_KEY' ) && '' !== (string) WPFB_BREVO_API_KEY ) {
			return (string) WPFB_BREVO_API_KEY;
		}

		$settings = self::get();

		return isset( $settings['brevo_api_key'] ) ? (string) $settings['brevo_api_key'] : '';
	}
}
