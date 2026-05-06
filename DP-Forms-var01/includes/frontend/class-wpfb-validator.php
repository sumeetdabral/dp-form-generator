<?php
/**
 * Server-side per-field validator for form submissions.
 *
 * Iterates the form's declared fields only — ignores any extra POST keys.
 * Delegates to Field_Types validate callbacks so adding a type is friction-free.
 *
 * @package WPFB
 */

namespace WPFB\Frontend;

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Field_Types;

/**
 * Validates submitted form data against a form's field definitions.
 */
class Validator {

	/**
	 * Validates the POST payload against the form's field definitions.
	 *
	 * File fields are pre-processed before this call; their $post_data entry
	 * should already hold the attachment ID (or 0 if no file was uploaded).
	 *
	 * @param array $fields    Decoded field definitions from fields_json.
	 * @param array $post_data Sanitized input data keyed by field id.
	 * @return array { valid: bool, errors: array<field_id, string> }
	 */
	public function validate( array $fields, array $post_data ): array {
		$errors = [];

		foreach ( $fields as $field ) {
			$field_id = $field['id'] ?? '';
			$type     = $field['type'] ?? 'text';

			if ( '' === $field_id ) {
				continue;
			}

			$type_def = Field_Types::get( $type );
			if ( ! $type_def ) {
				// Unknown field type — skip silently (schema already validated on save).
				continue;
			}

			$value  = $post_data[ $field_id ] ?? '';
			$result = call_user_func( $type_def['validate'], $value, $field );

			if ( is_wp_error( $result ) ) {
				$errors[ $field_id ] = $result->get_error_message();
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}
}
