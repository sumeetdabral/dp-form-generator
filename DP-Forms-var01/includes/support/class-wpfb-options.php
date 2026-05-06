<?php
/**
 * Typed wrapper around get_option/update_option for wpfb_settings.
 *
 * The Brevo API key is stored XOR-obfuscated with wp_salt(). This is NOT
 * encryption — it is obfuscation that prevents casual disclosure via a DB
 * dump or an options-viewer plugin. Anyone with access to wp-config.php salts
 * and the database can recover the key.
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

	// -------------------------------------------------------------------------
	// API key obfuscation helpers
	// These wrap the key in XOR+base64 so it is not stored as plaintext in the
	// options table. This is OBFUSCATION, not encryption — document clearly.
	// -------------------------------------------------------------------------

	/**
	 * Obfuscates the API key using XOR against wp_salt('secure_auth').
	 *
	 * NOT encryption — this is obfuscation only. Do not treat as secure storage.
	 *
	 * @param string $plain_key Raw API key string.
	 * @return string Base64-encoded obfuscated string.
	 */
	public static function obfuscate( string $plain_key ): string {
		if ( '' === $plain_key ) {
			return '';
		}

		$salt   = wp_salt( 'secure_auth' );
		$result = '';
		$salt_len = strlen( $salt );

		for ( $i = 0; $i < strlen( $plain_key ); $i++ ) {
			// XOR each character of the key with the salt, cycling.
			$result .= chr( ord( $plain_key[ $i ] ) ^ ord( $salt[ $i % $salt_len ] ) );
		}

		return base64_encode( $result ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Reverses obfuscate() to return the raw API key.
	 *
	 * NOT encryption — this is obfuscation only.
	 *
	 * @param string $obfuscated Base64-encoded obfuscated string.
	 * @return string Raw API key, or empty string on failure.
	 */
	public static function deobfuscate( string $obfuscated ): string {
		if ( '' === $obfuscated ) {
			return '';
		}

		$decoded = base64_decode( $obfuscated, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return '';
		}

		$salt     = wp_salt( 'secure_auth' );
		$result   = '';
		$salt_len = strlen( $salt );

		for ( $i = 0; $i < strlen( $decoded ); $i++ ) {
			$result .= chr( ord( $decoded[ $i ] ) ^ ord( $salt[ $i % $salt_len ] ) );
		}

		return $result;
	}
}
