<?php
/**
 * View: Submissions list admin page.
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

use WPFB\Admin\Submissions_List_Table;
use WPFB\Repository\Forms_Repository;

$list_table = new Submissions_List_Table();
$list_table->prepare_items();

// Current form filter for the export URL.
$form_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$export_url = wp_nonce_url(
	add_query_arg( [
		'action'  => 'wpfb_export_submissions',
		'form_id' => $form_id,
	], admin_url( 'admin-post.php' ) ),
	'wpfb_export_submissions_' . $form_id
);

$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Submissions', 'wpfb' ); ?></h1>

	<?php if ( 'resent' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Email resent successfully.', 'wpfb' ); ?></p></div>
	<?php elseif ( 'resend_failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Failed to resend email. Check the log for details.', 'wpfb' ); ?></p></div>
	<?php endif; ?>

	<a href="<?php echo esc_url( $export_url ); ?>" class="button">
		<?php esc_html_e( 'Export CSV', 'wpfb' ); ?>
		<?php if ( $form_id > 0 ) : ?>
			<?php
				$forms_repo  = new Forms_Repository();
				$export_form = $forms_repo->find( $form_id );
			?>
			<?php if ( $export_form ) : ?>
				(<?php echo esc_html( $export_form->title ); ?>)
			<?php endif; ?>
		<?php endif; ?>
	</a>

	<form method="get">
		<input type="hidden" name="page" value="wpfb-submissions">
		<?php $list_table->display(); ?>
	</form>
</div>
