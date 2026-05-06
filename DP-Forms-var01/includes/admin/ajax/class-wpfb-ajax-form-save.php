<?php
/**
 * AJAX handler for saving (create/update) a form definition.
 *
 * Security: capability check + nonce check before any database write.
 *
 * @package WPFB
 */

namespace WPFB\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;
use WPFB\Support\Field_Types;

/**
 * Handles the wp_ajax_wpfb_save_form action.
 */
class Ajax_Form_Save {

	/**
	 * Registers the admin AJAX hook.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpfb_save_form', [ $this, 'handle' ] );
	}

	/**
	 * Processes the save-form AJAX request.
	 */
	public function handle(): void {
		// Capability gate — admin-only action.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'wpfb' ) ], 403 );
		}

		// Nonce verification.
		check_ajax_referer( 'wpfb_save_form', '_wpnonce' );

		// Sanitize scalar inputs.
		$form_id      = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;
		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$admin_emails = isset( $_POST['admin_emails'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_emails'] ) ) : '';
		$mail_subject = isset( $_POST['mail_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_subject'] ) ) : '';
		$attach_files = isset( $_POST['attach_files'] ) ? (int) wp_unslash( $_POST['attach_files'] ) : 1;
		$fields_raw   = isset( $_POST['fields_json'] ) ? wp_unslash( $_POST['fields_json'] ) : '[]';

		// Form-level wrapper HTML and CSS class sanitization.
		$form_html_before = wp_kses_post( wp_unslash( $_POST['form_html_before'] ?? '' ) );
		$form_html_after  = wp_kses_post( wp_unslash( $_POST['form_html_after'] ?? '' ) );
		$form_css_class   = Field_Types::sanitize_css_class_list( $_POST['form_css_class'] ?? '' );

		if ( '' === $title ) {
			wp_send_json_error( [ 'message' => __( 'Form title is required.', 'wpfb' ) ], 422 );
		}

		// Decode and validate the fields JSON.
		$fields = json_decode( $fields_raw, true );
		if ( ! is_array( $fields ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid fields data.', 'wpfb' ) ], 422 );
		}

		$validation = Field_Types::validate_definition( $fields );
		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( [ 'message' => $validation->get_error_message() ], 422 );
		}

		// Sanitize each field definition (wrapper HTML, CSS classes, type-specific keys).
		$fields = array_map( [ Field_Types::class, 'sanitize_field_definition' ], $fields );

		$repo = new Forms_Repository();
		$data = [
			'title'            => $title,
			'fields'           => $fields,
			'admin_emails'     => $admin_emails,
			'mail_subject'     => $mail_subject,
			'attach_files'     => $attach_files,
			'form_html_before' => $form_html_before,
			'form_html_after'  => $form_html_after,
			'form_css_class'   => $form_css_class,
		];

		if ( $form_id > 0 ) {
			// Update existing form — verify it actually exists.
			$existing = $repo->find( $form_id );
			if ( ! $existing ) {
				wp_send_json_error( [ 'message' => __( 'Form not found.', 'wpfb' ) ], 404 );
			}

			$ok = $repo->update( $form_id, $data );
			if ( ! $ok ) {
				wp_send_json_error( [ 'message' => __( 'Failed to update form.', 'wpfb' ) ], 500 );
			}

			wp_send_json_success( [
				'message'   => __( 'Form updated.', 'wpfb' ),
				'form_id'   => $form_id,
				'shortcode' => '[wpfb_form id="' . $form_id . '"]',
			] );
		} else {
			// Insert new form.
			$new_id = $repo->insert( $data );
			if ( false === $new_id ) {
				wp_send_json_error( [ 'message' => __( 'Failed to create form.', 'wpfb' ) ], 500 );
			}

			wp_send_json_success( [
				'message'   => __( 'Form created.', 'wpfb' ),
				'form_id'   => $new_id,
				'shortcode' => '[wpfb_form id="' . $new_id . '"]',
			] );
		}
	}
}
