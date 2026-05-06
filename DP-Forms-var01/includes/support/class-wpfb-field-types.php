<?php
/**
 * Declarative registry for all supported form field types.
 *
 * Adding a new field type is a single entry here; the builder, validator,
 * shortcode renderer, and email builder all read from this registry.
 *
 * @package WPFB
 */

namespace WPFB\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of allowed field types with per-type schema and callbacks.
 */
class Field_Types {

	/**
	 * Returns the full registry of supported field types.
	 *
	 * Each entry has:
	 *  - label              : display name in the builder UI
	 *  - sanitize_input     : callable( $raw_post_value ) → mixed (string for scalars, array for checkboxes)
	 *  - validate           : callable( $value, $field_def ) → true or WP_Error
	 *  - render_admin_row   : callable( $field_def, $index ) → HTML string for builder (legacy PHP rows)
	 *  - render_frontend    : partial filename key (no path, no .php)
	 *  - format_for_display : callable( $value ) → HTML-safe string. Used by email body and submission-detail.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		return [
			'text'       => [
				'label'              => __( 'Text', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_text' ],
				'render_admin_row'   => [ self::class, 'admin_row_text' ],
				'render_frontend'    => 'field-text',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'email'      => [
				'label'              => __( 'Email', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_email' ],
				'render_admin_row'   => [ self::class, 'admin_row_email' ],
				'render_frontend'    => 'field-email',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'select'     => [
				'label'              => __( 'Select', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_select' ],
				'render_admin_row'   => [ self::class, 'admin_row_select' ],
				'render_frontend'    => 'field-select',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'file'       => [
				'label'              => __( 'File Upload', 'wpfb' ),
				'sanitize_input'     => null, // handled by Uploader in submission handler.
				'validate'           => [ self::class, 'validate_file' ],
				'render_admin_row'   => [ self::class, 'admin_row_file' ],
				'render_frontend'    => 'field-file',
				'format_for_display' => [ self::class, 'format_file' ],
			],
			'url'        => [
				'label'              => __( 'URL', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_url' ],
				'validate'           => [ self::class, 'validate_url' ],
				'render_admin_row'   => null, // Builder is JS-driven for new types.
				'render_frontend'    => 'field-url',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'tel'        => [
				'label'              => __( 'Phone', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_tel' ],
				'render_admin_row'   => null,
				'render_frontend'    => 'field-tel',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'number'     => [
				'label'              => __( 'Number', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_number' ],
				'render_admin_row'   => null,
				'render_frontend'    => 'field-number',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'date'       => [
				'label'              => __( 'Date', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_date' ],
				'render_admin_row'   => null,
				'render_frontend'    => 'field-date',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'textarea'   => [
				'label'              => __( 'Textarea', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_textarea' ],
				'validate'           => [ self::class, 'validate_text' ], // required check only.
				'render_admin_row'   => null,
				'render_frontend'    => 'field-textarea',
				'format_for_display' => [ self::class, 'format_text' ], // format_text already does nl2br.
			],
			'radio'      => [
				'label'              => __( 'Radio', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_scalar' ],
				'validate'           => [ self::class, 'validate_radio' ],
				'render_admin_row'   => null,
				'render_frontend'    => 'field-radio',
				'format_for_display' => [ self::class, 'format_text' ],
			],
			'checkboxes' => [
				'label'              => __( 'Checkboxes', 'wpfb' ),
				'sanitize_input'     => [ self::class, 'sanitize_checkboxes' ],
				'validate'           => [ self::class, 'validate_checkboxes' ],
				'render_admin_row'   => null,
				'render_frontend'    => 'field-checkboxes',
				'format_for_display' => [ self::class, 'format_checkboxes' ],
			],
		];
	}

	/**
	 * Returns the definition for a single type, or null if unknown.
	 *
	 * @param string $type Field type key.
	 * @return array|null
	 */
	public static function get( string $type ): ?array {
		$all = self::get_all();

		return $all[ $type ] ?? null;
	}

	/**
	 * Returns an array of valid type keys.
	 *
	 * @return string[]
	 */
	public static function valid_types(): array {
		return array_keys( self::get_all() );
	}

	/**
	 * Validates an array of field definitions coming from the builder AJAX save.
	 *
	 * Each definition must have: id (string), type (known), label (non-empty).
	 * Select/Radio/Checkboxes fields must have at least one option.
	 *
	 * @param array $fields Raw decoded field definitions.
	 * @return true|\WP_Error
	 */
	public static function validate_definition( array $fields ) {
		$valid_types        = self::valid_types();
		$option_types       = [ 'select', 'radio', 'checkboxes' ];

		foreach ( $fields as $index => $field ) {
			if ( empty( $field['id'] ) || ! is_string( $field['id'] ) ) {
				return new \WP_Error( 'invalid_field', sprintf(
					/* translators: %d: field index */
					__( 'Field %d is missing a valid id.', 'wpfb' ),
					$index + 1
				) );
			}

			if ( empty( $field['type'] ) || ! in_array( $field['type'], $valid_types, true ) ) {
				return new \WP_Error( 'invalid_type', sprintf(
					/* translators: %d: field index */
					__( 'Field %d has an unrecognized type.', 'wpfb' ),
					$index + 1
				) );
			}

			if ( empty( $field['label'] ) ) {
				return new \WP_Error( 'empty_label', sprintf(
					/* translators: %d: field index */
					__( 'Field %d must have a label.', 'wpfb' ),
					$index + 1
				) );
			}

			if ( in_array( $field['type'], $option_types, true ) && empty( $field['options'] ) ) {
				return new \WP_Error( 'no_options', sprintf(
					/* translators: %d: field index */
					__( 'Field %d must have at least one option.', 'wpfb' ),
					$index + 1
				) );
			}
		}

		return true;
	}

	/**
	 * Sanitizes per-field definition wrapper keys and type-specific extras.
	 *
	 * Called by the save handler after validate_definition() succeeds.
	 * Modifies and returns the sanitized field array.
	 *
	 * @param array $field Raw field definition (already decoded from JSON).
	 * @return array Sanitized field array.
	 */
	public static function sanitize_field_definition( array $field ): array {
		// Wrapper HTML — kses allows layout tags, strips scripts/iframes.
		if ( isset( $field['html_before'] ) ) {
			$field['html_before'] = wp_kses_post( wp_unslash( $field['html_before'] ) );
		}
		if ( isset( $field['html_after'] ) ) {
			$field['html_after'] = wp_kses_post( wp_unslash( $field['html_after'] ) );
		}

		// CSS class tokens — sanitize each token individually.
		if ( isset( $field['css_class'] ) ) {
			$field['css_class'] = self::sanitize_css_class_list( $field['css_class'] );
		}
		if ( isset( $field['wrapper_class'] ) ) {
			$field['wrapper_class'] = self::sanitize_css_class_list( $field['wrapper_class'] );
		}

		// Type-specific numeric/date casts.
		if ( 'number' === ( $field['type'] ?? '' ) ) {
			foreach ( [ 'min', 'max', 'step' ] as $key ) {
				if ( isset( $field[ $key ] ) && '' !== $field[ $key ] ) {
					$field[ $key ] = is_numeric( $field[ $key ] ) ? (float) $field[ $key ] : '';
				}
			}
		}

		if ( 'date' === ( $field['type'] ?? '' ) ) {
			foreach ( [ 'min', 'max' ] as $key ) {
				if ( isset( $field[ $key ] ) && '' !== $field[ $key ] ) {
					$dt             = \DateTime::createFromFormat( 'Y-m-d', $field[ $key ] );
					$field[ $key ] = ( $dt && $dt->format( 'Y-m-d' ) === $field[ $key ] ) ? $field[ $key ] : '';
				}
			}
		}

		if ( 'textarea' === ( $field['type'] ?? '' ) ) {
			if ( isset( $field['rows'] ) ) {
				$rows          = (int) $field['rows'];
				$field['rows'] = $rows > 0 ? $rows : 4;
			}
		}

		return $field;
	}

	/**
	 * Dispatches to the correct format_for_display callback for a field type.
	 *
	 * Returns an HTML-safe string. Output should be echoed raw (callbacks
	 * produce their own escaping).
	 *
	 * @param string $type  Field type key.
	 * @param mixed  $value The stored field value.
	 * @return string
	 */
	public static function format_for_display( string $type, $value ): string {
		$def = self::get( $type );

		if ( $def && isset( $def['format_for_display'] ) && is_callable( $def['format_for_display'] ) ) {
			return call_user_func( $def['format_for_display'], $value );
		}

		// Fallback — safe scalar display.
		return nl2br( esc_html( (string) $value ) );
	}

	/**
	 * Sanitizes a space-separated CSS class list: splits, sanitizes each token, rejoins.
	 *
	 * @param string $class_list Raw class string.
	 * @return string
	 */
	public static function sanitize_css_class_list( string $class_list ): string {
		$tokens = preg_split( '/\s+/', wp_unslash( trim( $class_list ) ) );

		return implode( ' ', array_filter( array_map( 'sanitize_html_class', $tokens ) ) );
	}

	// -------------------------------------------------------------------------
	// Sanitize input callbacks
	// -------------------------------------------------------------------------

	/**
	 * Sanitizes a standard scalar text input.
	 *
	 * @param mixed $value Raw POST value.
	 * @return string
	 */
	public static function sanitize_scalar( $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitizes a URL input.
	 *
	 * @param mixed $value Raw POST value.
	 * @return string
	 */
	public static function sanitize_url( $value ): string {
		return esc_url_raw( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitizes a textarea input (preserves newlines).
	 *
	 * @param mixed $value Raw POST value.
	 * @return string
	 */
	public static function sanitize_textarea( $value ): string {
		return sanitize_textarea_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Sanitizes a checkboxes array input.
	 *
	 * @param mixed $value Raw POST value (expected array).
	 * @return string[]
	 */
	public static function sanitize_checkboxes( $value ): array {
		return array_map( 'sanitize_text_field', wp_unslash( (array) $value ) );
	}

	// -------------------------------------------------------------------------
	// Validation callbacks
	// -------------------------------------------------------------------------

	/**
	 * Validates a text field value.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_text( $value, array $field_def ) {
		$value = is_array( $value ) ? '' : sanitize_text_field( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates an email field value.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_email( $value, array $field_def ) {
		$value = sanitize_email( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value && ! is_email( $value ) ) {
			return new \WP_Error( 'invalid_email', sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid email address.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates a select field value against the declared options list.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_select( $value, array $field_def ) {
		$value   = sanitize_text_field( wp_unslash( (string) $value ) );
		$options = isset( $field_def['options'] ) ? (array) $field_def['options'] : [];

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value && ! in_array( $value, $options, true ) ) {
			return new \WP_Error( 'invalid_option', sprintf(
				/* translators: %s: field label */
				__( '%s contains an invalid selection.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates a file field. Actual file handling is done by Uploader.
	 * This just enforces the required check when no file is present.
	 *
	 * @param mixed $value     The submitted value (attachment ID, or 0 if no upload).
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_file( $value, array $field_def ) {
		if ( ! empty( $field_def['required'] ) && empty( $value ) ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates a URL field value.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_url( $value, array $field_def ) {
		$value = esc_url_raw( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value && ! wp_http_validate_url( $value ) ) {
			return new \WP_Error( 'invalid_url', sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid URL.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates a telephone field value.
	 * Allows digits, +, -, (, ), and spaces. Max 30 characters.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_tel( $value, array $field_def ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value ) {
			if ( strlen( $value ) > 30 ) {
				return new \WP_Error( 'invalid_tel', sprintf(
					/* translators: %s: field label */
					__( '%s must be 30 characters or fewer.', 'wpfb' ),
					esc_html( $field_def['label'] )
				) );
			}

			if ( ! preg_match( '/^[\d\+\-\(\)\s]+$/', $value ) ) {
				return new \WP_Error( 'invalid_tel', sprintf(
					/* translators: %s: field label */
					__( '%s must be a valid phone number.', 'wpfb' ),
					esc_html( $field_def['label'] )
				) );
			}
		}

		return true;
	}

	/**
	 * Validates a number field value.
	 * Respects optional min/max from field_def.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_number( $value, array $field_def ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value ) {
			if ( ! is_numeric( $value ) ) {
				return new \WP_Error( 'invalid_number', sprintf(
					/* translators: %s: field label */
					__( '%s must be a valid number.', 'wpfb' ),
					esc_html( $field_def['label'] )
				) );
			}

			$num = (float) $value;

			if ( isset( $field_def['min'] ) && '' !== $field_def['min'] && $num < (float) $field_def['min'] ) {
				return new \WP_Error( 'number_min', sprintf(
					/* translators: 1: field label, 2: minimum value */
					__( '%1$s must be at least %2$s.', 'wpfb' ),
					esc_html( $field_def['label'] ),
					esc_html( $field_def['min'] )
				) );
			}

			if ( isset( $field_def['max'] ) && '' !== $field_def['max'] && $num > (float) $field_def['max'] ) {
				return new \WP_Error( 'number_max', sprintf(
					/* translators: 1: field label, 2: maximum value */
					__( '%1$s must be at most %2$s.', 'wpfb' ),
					esc_html( $field_def['label'] ),
					esc_html( $field_def['max'] )
				) );
			}
		}

		return true;
	}

	/**
	 * Validates a date field value.
	 * Expects Y-m-d format. Respects optional min/max from field_def.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_date( $value, array $field_def ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value ) {
			$dt = \DateTime::createFromFormat( 'Y-m-d', $value );
			if ( ! $dt || $dt->format( 'Y-m-d' ) !== $value ) {
				return new \WP_Error( 'invalid_date', sprintf(
					/* translators: %s: field label */
					__( '%s must be a valid date.', 'wpfb' ),
					esc_html( $field_def['label'] )
				) );
			}

			if ( isset( $field_def['min'] ) && '' !== $field_def['min'] && $value < $field_def['min'] ) {
				return new \WP_Error( 'date_min', sprintf(
					/* translators: 1: field label, 2: minimum date */
					__( '%1$s must be on or after %2$s.', 'wpfb' ),
					esc_html( $field_def['label'] ),
					esc_html( $field_def['min'] )
				) );
			}

			if ( isset( $field_def['max'] ) && '' !== $field_def['max'] && $value > $field_def['max'] ) {
				return new \WP_Error( 'date_max', sprintf(
					/* translators: 1: field label, 2: maximum date */
					__( '%1$s must be on or before %2$s.', 'wpfb' ),
					esc_html( $field_def['label'] ),
					esc_html( $field_def['max'] )
				) );
			}
		}

		return true;
	}

	/**
	 * Validates a radio field value against the declared options list.
	 *
	 * @param mixed $value     The submitted value.
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_radio( $value, array $field_def ) {
		$value   = sanitize_text_field( wp_unslash( (string) $value ) );
		$options = isset( $field_def['options'] ) ? (array) $field_def['options'] : [];

		if ( ! empty( $field_def['required'] ) && '' === $value ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		if ( '' !== $value && ! in_array( $value, $options, true ) ) {
			return new \WP_Error( 'invalid_option', sprintf(
				/* translators: %s: field label */
				__( '%s contains an invalid selection.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		return true;
	}

	/**
	 * Validates a checkboxes field value.
	 * Expects an array; each element must be in the declared options list.
	 *
	 * @param mixed $value     The submitted value (array of strings).
	 * @param array $field_def The field definition.
	 * @return true|\WP_Error
	 */
	public static function validate_checkboxes( $value, array $field_def ) {
		$values  = is_array( $value ) ? $value : [];
		$options = isset( $field_def['options'] ) ? (array) $field_def['options'] : [];

		if ( ! empty( $field_def['required'] ) && 0 === count( $values ) ) {
			return new \WP_Error( 'required', sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'wpfb' ),
				esc_html( $field_def['label'] )
			) );
		}

		foreach ( $values as $v ) {
			if ( ! in_array( $v, $options, true ) ) {
				return new \WP_Error( 'invalid_option', sprintf(
					/* translators: %s: field label */
					__( '%s contains an invalid selection.', 'wpfb' ),
					esc_html( $field_def['label'] )
				) );
			}
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Display format callbacks
	// -------------------------------------------------------------------------

	/**
	 * Formats a scalar field value for HTML display (email body or submission detail).
	 *
	 * @param mixed $value The field value.
	 * @return string
	 */
	public static function format_text( $value ): string {
		return nl2br( esc_html( (string) $value ) );
	}

	/**
	 * Formats a file field for display.
	 * Shows the attachment filename; actual file is sent as attachment if enabled.
	 *
	 * @param mixed $value Attachment ID or filename string.
	 * @return string
	 */
	public static function format_file( $value ): string {
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$filename = basename( get_attached_file( (int) $value ) );
			if ( $filename ) {
				return esc_html( $filename );
			}
		}

		return esc_html( (string) $value );
	}

	/**
	 * Formats a checkboxes array value for display.
	 * Returns a comma-separated, HTML-escaped string.
	 *
	 * @param mixed $value Array of selected option strings.
	 * @return string
	 */
	public static function format_checkboxes( $value ): string {
		$values = is_array( $value ) ? $value : (array) $value;

		return implode( ', ', array_map( 'esc_html', $values ) );
	}

	// -------------------------------------------------------------------------
	// Admin builder row HTML helpers (legacy PHP-rendered rows)
	// -------------------------------------------------------------------------

	/**
	 * Returns the builder-row HTML for a text field.
	 *
	 * @param array $field_def Field definition.
	 * @param int   $index     Current row index (0-based).
	 * @return string HTML.
	 */
	public static function admin_row_text( array $field_def, int $index ): string {
		return self::base_row_html( 'text', $field_def, $index );
	}

	/**
	 * Returns the builder-row HTML for an email field.
	 *
	 * @param array $field_def Field definition.
	 * @param int   $index     Current row index (0-based).
	 * @return string HTML.
	 */
	public static function admin_row_email( array $field_def, int $index ): string {
		return self::base_row_html( 'email', $field_def, $index );
	}

	/**
	 * Returns the builder-row HTML for a select field (includes options textarea).
	 *
	 * @param array $field_def Field definition.
	 * @param int   $index     Current row index (0-based).
	 * @return string HTML.
	 */
	public static function admin_row_select( array $field_def, int $index ): string {
		$options_val = isset( $field_def['options'] ) ? implode( "\n", (array) $field_def['options'] ) : '';
		$base        = self::base_row_html( 'select', $field_def, $index );
		$extra       = '<div class="wpfb-field-options">'
			. '<label>' . esc_html__( 'Options (one per line)', 'wpfb' ) . '</label>'
			. '<textarea class="wpfb-select-options">' . esc_textarea( $options_val ) . '</textarea>'
			. '</div>';

		// Insert extra markup just before the closing </li>.
		return substr( $base, 0, -5 ) . $extra . '</li>';
	}

	/**
	 * Returns the builder-row HTML for a file field.
	 *
	 * @param array $field_def Field definition.
	 * @param int   $index     Current row index (0-based).
	 * @return string HTML.
	 */
	public static function admin_row_file( array $field_def, int $index ): string {
		return self::base_row_html( 'file', $field_def, $index );
	}

	/**
	 * Shared builder row scaffold.
	 *
	 * @param string $type      Field type.
	 * @param array  $field_def Field definition.
	 * @param int    $index     Row index (0-based).
	 * @return string HTML.
	 */
	private static function base_row_html( string $type, array $field_def, int $index ): string {
		$label    = esc_attr( $field_def['label'] ?? '' );
		$required = ! empty( $field_def['required'] ) ? ' checked' : '';
		$id       = esc_attr( $field_def['id'] ?? 'field_' . $index );

		return '<li class="wpfb-field-row" data-type="' . esc_attr( $type ) . '" data-id="' . $id . '" draggable="true">'
			. '<span class="wpfb-drag-handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder', 'wpfb' ) . '"></span>'
			. '<span class="wpfb-field-type-badge">' . esc_html( $type ) . '</span>'
			. '<input type="text" class="wpfb-field-label" placeholder="' . esc_attr__( 'Field label', 'wpfb' ) . '" value="' . $label . '">'
			. '<label class="wpfb-required-wrap">'
			. '<input type="checkbox" class="wpfb-required"' . $required . '>'
			. esc_html__( 'Required', 'wpfb' )
			. '</label>'
			. '<button type="button" class="button wpfb-remove-field">' . esc_html__( 'Remove', 'wpfb' ) . '</button>'
			. '</li>';
	}
}
