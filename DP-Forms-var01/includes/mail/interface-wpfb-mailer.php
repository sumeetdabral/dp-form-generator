<?php
/**
 * Contract for mail transport implementations.
 *
 * Implementing this interface allows swapping Brevo for SMTP or another
 * service without touching the submission handler.
 *
 * @package WPFB
 */

namespace WPFB\Mail;

defined( 'ABSPATH' ) || exit;

/**
 * Mailer interface — all transports must implement this.
 */
interface Mailer_Interface {

	/**
	 * Sends a notification email for a submission.
	 *
	 * @param int   $form_id        The form that was submitted.
	 * @param array $payload        Associative array of field_id → { label, type, value, attachment_id? }.
	 * @param array $attachment_ids WP attachment IDs for uploaded files.
	 * @return array { ok: bool, error: string }
	 */
	public function send( int $form_id, array $payload, array $attachment_ids ): array;
}
