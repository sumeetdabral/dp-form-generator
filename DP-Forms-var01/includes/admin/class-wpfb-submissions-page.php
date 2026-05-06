<?php
/**
 * Admin page controller for the Submissions viewer.
 *
 * Handles three sub-actions:
 *  - (default) : list view
 *  - ?action=view&submission_id=X : detail view
 *
 * CSV export is handled via admin_post_wpfb_export_submissions (separate
 * admin-post handler) so it can stream headers cleanly.
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Submissions_Repository;
use WPFB\Repository\Forms_Repository;
use WPFB\Mail\Brevo_Mailer;

/**
 * Renders the Submissions admin page (list + detail).
 * Also registers the CSV export and Resend Mail admin-post handlers.
 */
class Submissions_Page {

	/**
	 * Registers hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_wpfb_export_submissions', [ $this, 'handle_csv_export' ] );
		add_action( 'admin_post_wpfb_resend_mail', [ $this, 'handle_resend_mail' ] );
	}

	/**
	 * Renders the submissions list or detail view.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'view' === $action ) {
			$this->render_detail();
			return;
		}

		$view = WPFB_DIR . 'views/admin/submissions-list.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Renders the submission detail view.
	 */
	private function render_detail(): void {
		$submission_id = isset( $_GET['submission_id'] ) ? (int) wp_unslash( $_GET['submission_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $submission_id <= 0 ) {
			wp_die( esc_html__( 'Invalid submission ID.', 'wpfb' ) );
		}

		$repo       = new Submissions_Repository();
		$submission = $repo->find( $submission_id );

		if ( ! $submission ) {
			wp_die( esc_html__( 'Submission not found.', 'wpfb' ) );
		}

		$forms_repo = new Forms_Repository();
		$form       = $forms_repo->find( (int) $submission->form_id );

		$view = WPFB_DIR . 'views/admin/submission-detail.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	/**
	 * Streams a CSV export of submissions for a given form.
	 *
	 * Triggered by admin-post action wpfb_export_submissions.
	 */
	public function handle_csv_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ), 403 );
		}

		$form_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0;
		check_admin_referer( 'wpfb_export_submissions_' . $form_id );

		$sub_repo  = new Submissions_Repository();
		$rows      = $form_id > 0 ? $sub_repo->all_for_form( $form_id ) : $sub_repo->all();

		$filename = 'wpfb-submissions';
		if ( $form_id > 0 ) {
			$filename .= '-form-' . $form_id;
		}
		$filename .= '-' . gmdate( 'Y-m-d' ) . '.csv';

		// Stream CSV headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// Write BOM for Excel UTF-8 compatibility.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Header row.
		fputcsv( $out, [ 'ID', 'Form ID', 'Submitted At', 'IP', 'Mail Status', 'Fields (JSON)', 'Attachment IDs' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				$row->id,
				$row->form_id,
				$row->created_at,
				$row->submitter_ip,
				$row->mail_status,
				$row->payload_json,
				$row->attachment_ids,
			] );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Resends the notification email for a failed submission.
	 *
	 * Triggered by admin-post action wpfb_resend_mail.
	 */
	public function handle_resend_mail(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ), 403 );
		}

		$submission_id = isset( $_GET['submission_id'] ) ? (int) wp_unslash( $_GET['submission_id'] ) : 0;
		check_admin_referer( 'wpfb_resend_mail_' . $submission_id );

		$sub_repo   = new Submissions_Repository();
		$submission = $sub_repo->find( $submission_id );

		if ( ! $submission ) {
			wp_safe_redirect( add_query_arg( [
				'page'  => 'wpfb-submissions',
				'error' => 'not_found',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$payload        = json_decode( $submission->payload_json, true ) ?: [];
		$attachment_ids = array_filter( array_map( 'intval', explode( ',', $submission->attachment_ids ) ) );

		$mailer      = new Brevo_Mailer();
		$mail_result = $mailer->send( (int) $submission->form_id, $payload, array_values( $attachment_ids ) );

		$sub_repo->update_mail_status(
			$submission_id,
			$mail_result['ok'] ? 'sent' : 'failed',
			$mail_result['error']
		);

		$notice = $mail_result['ok'] ? 'resent' : 'resend_failed';

		wp_safe_redirect( add_query_arg( [
			'page'          => 'wpfb-submissions',
			'action'        => 'view',
			'submission_id' => $submission_id,
			'notice'        => $notice,
		], admin_url( 'admin.php' ) ) );
		exit;
	}
}
