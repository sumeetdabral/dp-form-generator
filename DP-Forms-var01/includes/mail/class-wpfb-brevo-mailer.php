<?php
/**
 * Brevo HTTP API mail transport.
 *
 * Sends transactional email via POST https://api.brevo.com/v3/smtp/email
 * using wp_remote_post(). No SMTP, no PHPMailer config.
 *
 * @package WPFB
 */

namespace WPFB\Mail;

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Options;
use WPFB\Support\Logger;
use WPFB\Support\Field_Types;
use WPFB\Repository\Forms_Repository;

/**
 * Brevo transactional email mailer.
 */
class Brevo_Mailer implements Mailer_Interface {

	/**
	 * Brevo SMTP API endpoint.
	 */
	const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

	/**
	 * Maximum total base64 attachment size before we skip attachments.
	 * base64 is ~1.33x raw size; 9.5 MB base64 ≈ 7.1 MB raw.
	 */
	const MAX_ATTACHMENT_BASE64_BYTES = 9961472; // 9.5 * 1024 * 1024

	/**
	 * Sends a notification email for a form submission via Brevo.
	 *
	 * Returns { ok: bool, error: string }.
	 *
	 * @param int   $form_id        Form ID.
	 * @param array $payload        field_id → { label, type, value, attachment_id? }.
	 * @param array $attachment_ids WP attachment IDs.
	 * @return array
	 */
	public function send( int $form_id, array $payload, array $attachment_ids ): array {
		$settings = Options::get();
		$api_key  = Options::deobfuscate( $settings['brevo_api_key'] );

		if ( '' === $api_key ) {
			Logger::error( 'Brevo mailer: API key is not configured.', [ 'form_id' => $form_id ] );
			return [ 'ok' => false, 'error' => __( 'Brevo API key is not configured.', 'wpfb' ) ];
		}

		$forms_repo = new Forms_Repository();
		$form       = $forms_repo->find( $form_id );

		if ( ! $form ) {
			return [ 'ok' => false, 'error' => __( 'Form not found.', 'wpfb' ) ];
		}

		$to_list = $this->resolve_recipients( $form, $settings );

		if ( empty( $to_list ) ) {
			Logger::error( 'Brevo mailer: No recipients configured.', [ 'form_id' => $form_id ] );
			return [ 'ok' => false, 'error' => __( 'No recipients configured.', 'wpfb' ) ];
		}

		// Strip CRLF from header-bound values before building the body.
		$from_name  = $this->strip_crlf( $settings['from_name'] );
		$from_email = $this->strip_crlf( $settings['from_email'] );
		$subject    = $this->strip_crlf( $this->build_subject( $form, $payload, $settings ) );

		$body = [
			'sender'      => [
				'name'  => $from_name,
				'email' => $from_email,
			],
			'to'          => array_map(
				fn( $e ) => [ 'email' => $this->strip_crlf( $e ) ],
				$to_list
			),
			'subject'     => $subject,
			'htmlContent' => $this->render_html( $form, $payload ),
		];

		// Add reply-to from the first email field in the submission.
		$reply_to = $this->extract_reply_to( $payload );
		if ( $reply_to ) {
			$body['replyTo'] = $reply_to;
		}

		// Attach files if the form allows it.
		if ( $form->attach_files && ! empty( $attachment_ids ) ) {
			$attachments = $this->build_attachments( $attachment_ids );
			if ( ! empty( $attachments ) ) {
				$body['attachment'] = $attachments;
			}
		}

		$response = wp_remote_post( self::ENDPOINT, [
			'timeout' => 15,
			'headers' => [
				'accept'       => 'application/json',
				'content-type' => 'application/json',
				'api-key'      => $api_key,
			],
			'body'    => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			Logger::error( 'Brevo mailer: wp_remote_post error.', [ 'error' => $error_msg, 'form_id' => $form_id ] );
			return [ 'ok' => false, 'error' => $error_msg ];
		}

		$status_code  = wp_remote_retrieve_response_code( $response );
		$body_str     = wp_remote_retrieve_body( $response );

		// Brevo returns HTTP 201 on success.
		if ( 201 !== (int) $status_code ) {
			$error_msg = sprintf(
				/* translators: 1: HTTP status code, 2: API response body */
				__( 'Brevo API returned HTTP %1$d: %2$s', 'wpfb' ),
				$status_code,
				$body_str
			);
			Logger::error( 'Brevo mailer: non-201 response.', [
				'status'  => $status_code,
				'body'    => substr( $body_str, 0, 500 ),
				'form_id' => $form_id,
			] );
			return [ 'ok' => false, 'error' => $error_msg ];
		}

		Logger::info( 'Brevo mailer: email sent.', [ 'form_id' => $form_id, 'status' => $status_code ] );
		return [ 'ok' => true, 'error' => '' ];
	}

	/**
	 * Resolves the final recipient list for a submission.
	 * Per-form admin_emails take precedence; falls back to global setting.
	 *
	 * @param object $form     Form row.
	 * @param array  $settings Plugin settings.
	 * @return string[]
	 */
	private function resolve_recipients( object $form, array $settings ): array {
		$source = ! empty( trim( $form->admin_emails ) )
			? $form->admin_emails
			: $settings['global_admin_emails'];

		$emails = array_filter( array_map( 'trim', explode( ',', $source ) ) );

		// Validate each address.
		return array_values( array_filter( $emails, 'is_email' ) );
	}

	/**
	 * Builds the email subject.
	 *
	 * @param object $form     Form row.
	 * @param array  $payload  Submission payload.
	 * @param array  $settings Plugin settings.
	 * @return string
	 */
	private function build_subject( object $form, array $payload, array $settings ): string {
		if ( ! empty( trim( $form->mail_subject ) ) ) {
			return $form->mail_subject;
		}

		return sprintf(
			/* translators: %s: form title */
			__( 'New submission: %s', 'wpfb' ),
			$form->title
		);
	}

	/**
	 * Renders the HTML email body from the payload.
	 *
	 * Uses wp_kses_post for the overall wrapper HTML since we control the
	 * structure, and esc_html per field value.
	 *
	 * @param object $form    Form row.
	 * @param array  $payload Submission payload.
	 * @return string
	 */
	private function render_html( object $form, array $payload ): string {
		$fields_def = json_decode( $form->fields_json, true ) ?: [];
		$field_map  = [];
		foreach ( $fields_def as $field ) {
			$field_map[ $field['id'] ] = $field;
		}

		$rows = '';
		foreach ( $payload as $field_id => $entry ) {
			$type    = $entry['type'] ?? 'text';
			$label   = esc_html( $entry['label'] ?? $field_id );
			$value   = $entry['value'] ?? '';

			// format_for_display dispatches via the registry and returns pre-escaped HTML.
			$formatted_value = Field_Types::format_for_display( $type, $value );

			$rows .= '<tr>'
				. '<td style="font-weight:bold;padding:8px 12px;background:#f5f5f5;border:1px solid #ddd;vertical-align:top;width:30%">'
				. $label
				. '</td>'
				. '<td style="padding:8px 12px;border:1px solid #ddd;vertical-align:top">'
				. $formatted_value
				. '</td>'
				. '</tr>';
		}

		$html = '<!DOCTYPE html><html><body>'
			. '<h2 style="color:#333">' . esc_html( sprintf(
				/* translators: %s: form title */
				__( 'New submission: %s', 'wpfb' ),
				$form->title
			) ) . '</h2>'
			. '<table style="border-collapse:collapse;width:100%;max-width:600px">'
			. $rows
			. '</table>'
			. '</body></html>';

		// wp_kses_post allows safe HTML tags/attributes for email content.
		return wp_kses_post( $html );
	}

	/**
	 * Extracts reply-to from the first email-type field in the payload.
	 *
	 * @param array $payload Submission payload.
	 * @return array|null { email: string } or null.
	 */
	private function extract_reply_to( array $payload ): ?array {
		foreach ( $payload as $entry ) {
			if ( isset( $entry['type'] ) && 'email' === $entry['type'] && ! empty( $entry['value'] ) ) {
				$email = sanitize_email( $entry['value'] );
				if ( is_email( $email ) ) {
					return [ 'email' => $this->strip_crlf( $email ) ];
				}
			}
		}

		return null;
	}

	/**
	 * Builds the Brevo attachment array from WP attachment IDs.
	 *
	 * Aborts and logs a warning if total base64 size would exceed 9.5 MB.
	 *
	 * @param array $attachment_ids WP attachment IDs.
	 * @return array Brevo attachment objects.
	 */
	private function build_attachments( array $attachment_ids ): array {
		$attachments     = [];
		$total_b64_bytes = 0;

		foreach ( $attachment_ids as $att_id ) {
			$att_id   = (int) $att_id;
			$filepath = get_attached_file( $att_id );

			if ( ! $filepath || ! file_exists( $filepath ) ) {
				Logger::warning( 'Brevo mailer: attachment file not found.', [ 'attachment_id' => $att_id ] );
				continue;
			}

			$raw_size = filesize( $filepath );
			// base64 encodes ~4/3 the raw size.
			$b64_size = (int) ceil( $raw_size * 4 / 3 );

			if ( ( $total_b64_bytes + $b64_size ) > self::MAX_ATTACHMENT_BASE64_BYTES ) {
				Logger::warning(
					'Brevo mailer: attachments exceed 9.5 MB base64 limit; sending without attachments.',
					[ 'attachment_ids' => $attachment_ids ]
				);
				return [];
			}

			$total_b64_bytes += $b64_size;

			$content = file_get_contents( $filepath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $content ) {
				Logger::warning( 'Brevo mailer: could not read attachment file.', [ 'path' => $filepath ] );
				continue;
			}

			$attachments[] = [
				'name'    => basename( $filepath ),
				'content' => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			];
		}

		return $attachments;
	}

	/**
	 * Strips carriage return and newline characters to prevent header injection.
	 *
	 * JSON transport mostly neutralizes this, but we strip anyway as defence-
	 * in-depth for from_name, subject, and email addresses.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private function strip_crlf( string $value ): string {
		return trim( str_replace( [ "\r", "\n" ], '', $value ) );
	}
}
