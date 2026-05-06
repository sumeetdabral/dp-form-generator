<?php
/**
 * View: Submission detail admin page.
 *
 * Variables available:
 *   $submission  object  Submission row.
 *   $form        object|null  Form row, or null if deleted.
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Field_Types;

$payload        = json_decode( $submission->payload_json, true ) ?: [];
$attachment_ids = array_filter( array_map( 'intval', explode( ',', $submission->attachment_ids ) ) );
$back_url       = admin_url( 'admin.php?page=wpfb-submissions' );

$resend_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=wpfb_resend_mail&submission_id=' . (int) $submission->id ),
	'wpfb_resend_mail_' . (int) $submission->id
);

$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap">
	<h1>
		<?php
		printf(
			/* translators: %d: submission ID */
			esc_html__( 'Submission #%d', 'wpfb' ),
			(int) $submission->id
		);
		?>
	</h1>

	<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( '&larr; Back to list', 'wpfb' ); ?></a>

	<?php if ( 'resent' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Email resent successfully.', 'wpfb' ); ?></p></div>
	<?php elseif ( 'resend_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Failed to resend email. Check the log for details.', 'wpfb' ); ?></p></div>
	<?php endif; ?>

	<table class="widefat wpfb-detail-meta" style="max-width:700px;margin:20px 0">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Form', 'wpfb' ); ?></th>
				<td>
					<?php if ( $form ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfb-form-builder&form_id=' . (int) $form->id ) ); ?>">
							<?php echo esc_html( $form->title ); ?>
						</a>
					<?php else : ?>
						<?php echo esc_html( sprintf(
							/* translators: %d: form ID */
							__( 'Form #%d (deleted)', 'wpfb' ),
							(int) $submission->form_id
						) ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Submitted At', 'wpfb' ); ?></th>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->created_at ) ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'IP Address', 'wpfb' ); ?></th>
				<td><?php echo esc_html( $submission->submitter_ip ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Mail Status', 'wpfb' ); ?></th>
				<td>
					<span class="wpfb-mail-status wpfb-status-<?php echo esc_attr( sanitize_html_class( $submission->mail_status ) ); ?>">
						<?php echo esc_html( $submission->mail_status ); ?>
					</span>
					<?php if ( 'failed' === $submission->mail_status || 'pending' === $submission->mail_status ) : ?>
						&nbsp;<a href="<?php echo esc_url( $resend_url ); ?>" class="button button-small"><?php esc_html_e( 'Resend', 'wpfb' ); ?></a>
					<?php endif; ?>
					<?php if ( $submission->mail_error ) : ?>
						<p class="description" style="color:#c00"><?php echo esc_html( $submission->mail_error ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Submitted Fields', 'wpfb' ); ?></h2>

	<?php if ( empty( $payload ) ) : ?>
		<p><?php esc_html_e( 'No field data found.', 'wpfb' ); ?></p>
	<?php else : ?>
		<table class="widefat wpfb-payload-table" style="max-width:700px">
			<thead>
				<tr>
					<th style="width:30%"><?php esc_html_e( 'Field', 'wpfb' ); ?></th>
					<th><?php esc_html_e( 'Value', 'wpfb' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $payload as $field_id => $entry ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $entry['label'] ?? $field_id ); ?></strong></td>
						<td>
							<?php if ( 'file' === ( $entry['type'] ?? '' ) && ! empty( $entry['attachment_id'] ) ) : ?>
								<?php
								// Only show attachments listed in the submission row (IDOR guard).
								$att_id = (int) $entry['attachment_id'];
								if ( in_array( $att_id, array_map( 'intval', $attachment_ids ), true ) ) :
									$att_url = wp_get_attachment_url( $att_id );
									?>
									<a href="<?php echo esc_url( $att_url ); ?>" target="_blank">
										<?php echo esc_html( basename( get_attached_file( $att_id ) ) ); ?>
									</a>
								<?php endif; ?>
							<?php else : ?>
								<?php echo Field_Types::format_for_display( $entry['type'] ?? 'text', $entry['value'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format_for_display returns pre-escaped HTML. ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
