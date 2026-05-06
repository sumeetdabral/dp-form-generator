<?php
/**
 * Admin page controller for the Form Builder (create/edit).
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;

/**
 * Renders the Form Builder admin page.
 */
class Form_Builder_Page {

	/**
	 * Registers hooks for this page.
	 */
	public function register_hooks(): void {
		// No additional hooks beyond menu registration needed here.
	}

	/**
	 * Renders the page (called by the menu callback).
	 * Loads an existing form if form_id is present in the query string.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form    = null;

		if ( $form_id > 0 ) {
			$repo = new Forms_Repository();
			$form = $repo->find( $form_id );
		}

		$view = WPFB_DIR . 'views/admin/form-builder.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}
}
