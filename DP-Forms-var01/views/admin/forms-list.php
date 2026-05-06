<?php
/**
 * View: Admin forms list page.
 *
 * Variables available from Forms_List_Page::render():
 *   (none — list table is self-contained)
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

use WPFB\Admin\Forms_List_Table;

$list_table = new Forms_List_Table();
$list_table->prepare_items();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'DP-Forms-var01', 'wpfb' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfb-form-builder' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'wpfb' ); ?>
	</a>

	<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Form deleted.', 'wpfb' ); ?></p>
		</div>
	<?php endif; ?>

	<hr class="wp-header-end">

	<form method="get">
		<input type="hidden" name="page" value="wpfb-forms">
		<?php $list_table->display(); ?>
	</form>
</div>
