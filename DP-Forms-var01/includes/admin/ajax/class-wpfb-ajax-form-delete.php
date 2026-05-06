<?php
/**
 * Admin handler for deleting a form (via admin-post action, not AJAX).
 *
 * Registered via admin_post_wpfb_delete_form — triggered by a linked GET
 * action with a nonce in the URL. Redirects back to the forms list on
 * completion.
 *
 * Security: capability check + check_admin_referer before any DB write.
 *
 * @package WPFB
 */

namespace WPFB\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;

/**
 * Handles form deletion via the admin-post hook.
 */
class Ajax_Form_Delete {

	/**
	 * Registers the admin-post hook.
	 */
	public function register_hooks(): void {
		// Handle delete from the list table row action (GET link with nonce).
		add_action( 'admin_post_wpfb_delete_form', [ $this, 'handle' ] );
		// Also handle inline delete triggered from the forms list page itself.
		add_action( 'admin_action_delete', [ $this, 'handle_list_action' ] );
	}

	/**
	 * Processes deletion via admin-post URL.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ), 403 );
		}

		$form_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0;
		if ( $form_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpfb-forms&error=invalid_id' ) );
			exit;
		}

		check_admin_referer( 'wpfb_delete_form_' . $form_id );

		$repo = new Forms_Repository();
		$repo->delete( $form_id );

		wp_safe_redirect( add_query_arg( [
			'page'    => 'wpfb-forms',
			'deleted' => 1,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Processes deletion triggered from the forms list page via ?action=delete&page=wpfb-forms.
	 */
	public function handle_list_action(): void {
		// Only handle when we're on our own page.
		if ( ! isset( $_GET['page'] ) || 'wpfb-forms' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ), 403 );
		}

		$form_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0;
		if ( $form_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpfb-forms&error=invalid_id' ) );
			exit;
		}

		check_admin_referer( 'wpfb_delete_form_' . $form_id );

		$repo = new Forms_Repository();
		$repo->delete( $form_id );

		wp_safe_redirect( add_query_arg( [
			'page'    => 'wpfb-forms',
			'deleted' => 1,
		], admin_url( 'admin.php' ) ) );
		exit;
	}
}
