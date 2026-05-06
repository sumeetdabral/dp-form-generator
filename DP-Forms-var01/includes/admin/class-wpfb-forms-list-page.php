<?php
/**
 * Admin page controller for the Forms list.
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Forms list admin page.
 */
class Forms_List_Page {

	/**
	 * Registers hooks for this page.
	 */
	public function register_hooks(): void {
		// No additional hooks beyond menu registration needed here.
	}

	/**
	 * Renders the page (called by the menu callback).
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wpfb' ) );
		}

		$view = WPFB_DIR . 'views/admin/forms-list.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}
}
