<?php
/**
 * Settings admin page using the WordPress Settings API.
 *
 * Registers a single option group 'wpfb_settings_group' that maps to the
 * serialized `wpfb_settings` option. The sanitize callback is the single
 * authoritative place where inputs are cleaned.
 *
 * API key behaviour:
 *  - The field is type="password".
 *  - If the field is submitted empty, the existing stored key is kept.
 *  - If a new value is typed, it is stored as plaintext (sanitized).
 *  - The stored key is NEVER written back to the field value.
 *  - If WPFB_BREVO_API_KEY is defined in wp-config.php it takes precedence and
 *    the field is disabled.
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Options;

/**
 * Registers and renders the Settings admin page.
 */
class Settings_Page {

	/**
	 * Registers hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Registers settings sections, fields, and the option via Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'wpfb_settings_group',
			'wpfb_settings',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => Options::get_defaults(),
			]
		);

		// Section: Brevo credentials.
		add_settings_section(
			'wpfb_section_brevo',
			__( 'Brevo API Settings', 'wpfb' ),
			[ $this, 'section_brevo_description' ],
			'wpfb_settings'
		);

		add_settings_field(
			'wpfb_brevo_api_key',
			__( 'API Key', 'wpfb' ),
			[ $this, 'field_api_key' ],
			'wpfb_settings',
			'wpfb_section_brevo'
		);

		add_settings_field(
			'wpfb_from_email',
			__( 'From Email', 'wpfb' ),
			[ $this, 'field_from_email' ],
			'wpfb_settings',
			'wpfb_section_brevo'
		);

		add_settings_field(
			'wpfb_from_name',
			__( 'From Name', 'wpfb' ),
			[ $this, 'field_from_name' ],
			'wpfb_settings',
			'wpfb_section_brevo'
		);

		// Section: Global defaults.
		add_settings_section(
			'wpfb_section_general',
			__( 'General Settings', 'wpfb' ),
			null,
			'wpfb_settings'
		);

		add_settings_field(
			'wpfb_global_admin_emails',
			__( 'Default Admin Recipients', 'wpfb' ),
			[ $this, 'field_global_admin_emails' ],
			'wpfb_settings',
			'wpfb_section_general'
		);

		// Section: File upload rules.
		add_settings_section(
			'wpfb_section_files',
			__( 'File Upload Settings', 'wpfb' ),
			null,
			'wpfb_settings'
		);

		add_settings_field(
			'wpfb_allowed_mime_types',
			__( 'Allowed Extensions', 'wpfb' ),
			[ $this, 'field_allowed_mime_types' ],
			'wpfb_settings',
			'wpfb_section_files'
		);

		add_settings_field(
			'wpfb_max_upload_mb',
			__( 'Max Upload Size (MB)', 'wpfb' ),
			[ $this, 'field_max_upload_mb' ],
			'wpfb_settings',
			'wpfb_section_files'
		);

		// Section: Danger zone.
		add_settings_section(
			'wpfb_section_danger',
			__( 'Data Management', 'wpfb' ),
			null,
			'wpfb_settings'
		);

		add_settings_field(
			'wpfb_delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'wpfb' ),
			[ $this, 'field_delete_on_uninstall' ],
			'wpfb_settings',
			'wpfb_section_danger'
		);
	}

	/**
	 * Sanitizes and merges the submitted settings values.
	 *
	 * This is the single authoritative input sanitization point for settings.
	 *
	 * @param array $input Raw submitted values.
	 * @return array Sanitized settings to store.
	 */
	public function sanitize_settings( $input ): array {
		$current = Options::get();

		// API key: only update if a new non-empty value was submitted.
		if ( ! empty( $input['brevo_api_key'] ) ) {
			$current['brevo_api_key'] = sanitize_text_field( $input['brevo_api_key'] );
		}
		// If blank, $current['brevo_api_key'] keeps the existing stored value.

		$current['from_email'] = sanitize_email( $input['from_email'] ?? '' );
		$current['from_name']  = sanitize_text_field( $input['from_name'] ?? '' );

		// Global admin emails: comma-separated, validate each address.
		$raw_emails = sanitize_textarea_field( $input['global_admin_emails'] ?? '' );
		$emails     = array_filter( array_map( 'trim', explode( ',', $raw_emails ) ) );
		$current['global_admin_emails'] = implode( ',', array_filter( $emails, 'is_email' ) );

		// Allowed mime types: stored as array of lowercase extensions.
		$raw_mimes    = sanitize_textarea_field( $input['allowed_mime_types'] ?? '' );
		$mime_parts   = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $raw_mimes ) ) );
		$current['allowed_mime_types'] = array_values( array_map( 'strtolower', $mime_parts ) );

		$current['max_upload_mb'] = max( 1, min( 50, (int) ( $input['max_upload_mb'] ?? 5 ) ) );

		$current['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		return $current;
	}

	/**
	 * Renders the page (called by the menu callback).
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ) );
		}

		$view = WPFB_DIR . 'views/admin/settings.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	// -------------------------------------------------------------------------
	// Section descriptions
	// -------------------------------------------------------------------------

	/**
	 * Prints the Brevo section description.
	 */
	public function section_brevo_description(): void {
		echo '<p>' . esc_html__( 'Connect to Brevo\'s transactional email API. For stronger security you can define WPFB_BREVO_API_KEY in wp-config.php instead of saving the key here.', 'wpfb' ) . '</p>';
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	/**
	 * Renders the API key field.
	 */
	public function field_api_key(): void {
		$constant_set = defined( 'WPFB_BREVO_API_KEY' ) && '' !== (string) WPFB_BREVO_API_KEY;
		$settings     = Options::get();
		$has_key      = '' !== $settings['brevo_api_key'];

		echo '<input type="password" id="wpfb_brevo_api_key" name="wpfb_settings[brevo_api_key]" value="" autocomplete="new-password" class="regular-text"'
			. ( $constant_set ? ' disabled' : '' ) . '>';

		if ( $constant_set ) {
			echo '<p class="description">' . esc_html__( 'The API key is defined in wp-config.php (WPFB_BREVO_API_KEY) and takes precedence, so this field is disabled.', 'wpfb' ) . '</p>';
		} elseif ( $has_key ) {
			echo '<p class="description">' . esc_html__( 'A key is saved. Leave blank to keep it, or type a new one to replace it.', 'wpfb' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Enter your Brevo API key (v3).', 'wpfb' ) . '</p>';
		}
	}

	/**
	 * Renders the From Email field.
	 */
	public function field_from_email(): void {
		$settings = Options::get();
		echo '<input type="email" id="wpfb_from_email" name="wpfb_settings[from_email]" value="'
			. esc_attr( $settings['from_email'] ) . '" class="regular-text">';
	}

	/**
	 * Renders the From Name field.
	 */
	public function field_from_name(): void {
		$settings = Options::get();
		echo '<input type="text" id="wpfb_from_name" name="wpfb_settings[from_name]" value="'
			. esc_attr( $settings['from_name'] ) . '" class="regular-text">';
	}

	/**
	 * Renders the global admin emails field.
	 */
	public function field_global_admin_emails(): void {
		$settings = Options::get();
		echo '<input type="text" id="wpfb_global_admin_emails" name="wpfb_settings[global_admin_emails]" value="'
			. esc_attr( $settings['global_admin_emails'] ) . '" class="large-text">';
		echo '<p class="description">' . esc_html__( 'Comma-separated email addresses. Used when a form has no per-form recipients.', 'wpfb' ) . '</p>';
	}

	/**
	 * Renders the allowed mime types field.
	 */
	public function field_allowed_mime_types(): void {
		$settings = Options::get();
		$value    = implode( ', ', (array) $settings['allowed_mime_types'] );
		echo '<input type="text" id="wpfb_allowed_mime_types" name="wpfb_settings[allowed_mime_types]" value="'
			. esc_attr( $value ) . '" class="large-text">';
		echo '<p class="description">' . esc_html__( 'Comma-separated file extensions, e.g. pdf, jpg, png, docx', 'wpfb' ) . '</p>';
	}

	/**
	 * Renders the max upload MB field.
	 */
	public function field_max_upload_mb(): void {
		$settings = Options::get();
		echo '<input type="number" id="wpfb_max_upload_mb" name="wpfb_settings[max_upload_mb]" value="'
			. esc_attr( (string) $settings['max_upload_mb'] ) . '" min="1" max="50" class="small-text"> '
			. esc_html__( 'MB', 'wpfb' );
	}

	/**
	 * Renders the delete data on uninstall checkbox.
	 */
	public function field_delete_on_uninstall(): void {
		$settings = Options::get();
		echo '<label><input type="checkbox" id="wpfb_delete_data_on_uninstall" name="wpfb_settings[delete_data_on_uninstall]" value="1"'
			. checked( $settings['delete_data_on_uninstall'], true, false ) . '> '
			. esc_html__( 'Remove all plugin data (tables + settings) when the plugin is deleted.', 'wpfb' )
			. '</label>';
		echo '<p class="description" style="color:#c00">' . esc_html__( 'Warning: this cannot be undone.', 'wpfb' ) . '</p>';
	}
}
