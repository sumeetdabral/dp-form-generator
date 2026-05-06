<?php
/**
 * AJAX submission handler for the frontend form.
 *
 * Orchestration order: nonce → load form → validate → upload → persist → mail → respond.
 *
 * Security enforced here:
 *  - check_ajax_referer() with form-scoped nonce action.
 *  - Iterates declared fields only — drops extra POST keys.
 *  - Aborts entire submission if any file upload fails.
 *
 * @package WPFB
 */

namespace WPFB\Frontend;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;
use WPFB\Repository\Submissions_Repository;
use WPFB\Mail\Brevo_Mailer;
use WPFB\Support\Field_Types;
use WPFB\Support\Logger;

/**
 * Handles the wpfb_submit AJAX action.
 */
class Submission_Handler {

	/**
	 * Registers the AJAX hooks (both priv and nopriv — forms are public-facing).
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_wpfb_submit', [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_wpfb_submit', [ $this, 'handle' ] );
	}

	/**
	 * Processes the AJAX form submission.
	 */
	public function handle(): void {
		// 1. Validate form_id is present and numeric before using it in the nonce check.
		$form_id = isset( $_POST['form_id'] ) ? (int) wp_unslash( $_POST['form_id'] ) : 0;

		if ( $form_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid form.', 'wpfb' ) ], 400 );
		}

		// 2. Form-scoped nonce check — action includes form ID to prevent replay across forms.
		check_ajax_referer( 'wpfb_submit_' . $form_id, '_wpnonce' );

		// 3. Load the form definition.
		$forms_repo = new Forms_Repository();
		$form       = $forms_repo->find( $form_id );

		if ( ! $form ) {
			wp_send_json_error( [ 'message' => __( 'Form not found.', 'wpfb' ) ], 404 );
		}

		$fields = json_decode( $form->fields_json, true );
		if ( ! is_array( $fields ) ) {
			wp_send_json_error( [ 'message' => __( 'Form configuration is invalid.', 'wpfb' ) ], 500 );
		}

		// 4. Process file uploads first (before main validation so IDs are available).
		$uploader       = new Uploader();
		$attachment_ids = [];
		$post_data      = [];

		foreach ( $fields as $field ) {
			$field_id = $field['id'] ?? '';
			$type     = $field['type'] ?? 'text';

			if ( '' === $field_id ) {
				continue;
			}

			if ( 'file' === $type ) {
				if ( isset( $_FILES[ $field_id ] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ $field_id ]['error'] ) {
					$att_id = $uploader->handle( $field_id );

					if ( is_wp_error( $att_id ) ) {
						wp_send_json_error( [
							'message' => $att_id->get_error_message(),
							'field'   => $field_id,
						], 422 );
					}

					$attachment_ids[]        = $att_id;
					$post_data[ $field_id ] = $att_id; // Pass ID to validator.
				} else {
					$post_data[ $field_id ] = 0; // No file submitted.
				}
				continue;
			}

			// Registry-driven sanitization for all non-file types.
			// checkboxes: $_POST[$field_id] is an array; scalars are strings.
			$def       = Field_Types::get( $type );
			$sanitizer = ( $def && isset( $def['sanitize_input'] ) && is_callable( $def['sanitize_input'] ) )
				? $def['sanitize_input']
				: null;

			$raw = $_POST[ $field_id ] ?? ( 'checkboxes' === $type ? [] : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( $sanitizer ) {
				$post_data[ $field_id ] = call_user_func( $sanitizer, $raw );
			} else {
				// Fallback for any type without a sanitize_input callback.
				$post_data[ $field_id ] = sanitize_text_field( wp_unslash( (string) $raw ) );
			}
		}

		// 5. Validate all fields (including file required-checks via attachment IDs).
		$validator = new Validator();
		$result    = $validator->validate( $fields, $post_data );

		if ( ! $result['valid'] ) {
			wp_send_json_error( [
				'message' => __( 'Please fix the errors below.', 'wpfb' ),
				'errors'  => $result['errors'],
			], 422 );
		}

		// 6. Build the payload for storage.
		$payload = [];
		foreach ( $fields as $field ) {
			$field_id = $field['id'] ?? '';
			if ( '' === $field_id ) {
				continue;
			}

			$entry = [
				'label' => sanitize_text_field( $field['label'] ?? $field_id ),
				'type'  => $field['type'] ?? 'text',
				'value' => $post_data[ $field_id ] ?? '',
			];

			if ( 'file' === $field['type'] && ! empty( $post_data[ $field_id ] ) ) {
				$entry['attachment_id'] = (int) $post_data[ $field_id ];
			}

			$payload[ $field_id ] = $entry;
		}

		// 7. Persist the submission row.
		$sub_repo      = new Submissions_Repository();
		$submission_id = $sub_repo->insert( [
			'form_id'        => $form_id,
			'payload'        => $payload,
			'attachment_ids' => $attachment_ids,
			'submitter_ip'   => $this->get_client_ip(),
			'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
		] );

		if ( false === $submission_id ) {
			Logger::error( 'Could not persist submission.', [ 'form_id' => $form_id ] );
			wp_send_json_error( [ 'message' => __( 'An error occurred saving your submission.', 'wpfb' ) ], 500 );
		}

		// 8. Hand off to the mailer; always return success to the visitor.
		//    Mail failures are admin-internal — the submission is already saved.
		$mailer      = new Brevo_Mailer();
		$mail_result = $mailer->send( $form_id, $payload, $attachment_ids );

		$mail_status = $mail_result['ok'] ? 'sent' : 'failed';
		$sub_repo->update_mail_status( $submission_id, $mail_status, $mail_result['error'] );

		// Always return success to the visitor — mail status is an admin concern.
		wp_send_json_success( [
			'message' => __( 'Thank you! Your submission has been received.', 'wpfb' ),
		] );
	}

	/**
	 * Returns the client IP address, preferring REMOTE_ADDR.
	 * Avoids trusting X-Forwarded-For without server configuration.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
