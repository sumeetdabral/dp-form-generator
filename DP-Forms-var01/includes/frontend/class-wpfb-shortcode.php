<?php
/**
 * Frontend shortcode renderer: [wpfb_form id="X"].
 *
 * Enqueues frontend assets only when the shortcode is actually rendered
 * (fallback inside render(), in addition to the has_shortcode() check in
 * Plugin::boot() which fires on wp_enqueue_scripts).
 *
 * @package WPFB
 */

namespace WPFB\Frontend;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;
use WPFB\Support\Options;

/**
 * Registers and renders the [wpfb_form] shortcode.
 */
class Shortcode {

	/**
	 * Registers the shortcode hook.
	 */
	public function register_hooks(): void {
		add_shortcode( 'wpfb_form', [ $this, 'render' ] );
	}

	/**
	 * Renders the form HTML for the given shortcode attributes.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'wpfb_form' );
		$id   = (int) $atts['id'];

		if ( $id <= 0 ) {
			return '<p class="wpfb-error">' . esc_html__( 'No form ID specified.', 'wpfb' ) . '</p>';
		}

		$forms_repo = new Forms_Repository();
		$form       = $forms_repo->find( $id );

		if ( ! $form ) {
			return '<p class="wpfb-error">' . esc_html__( 'Form not found.', 'wpfb' ) . '</p>';
		}

		$fields = json_decode( $form->fields_json, true );
		if ( ! is_array( $fields ) ) {
			return '<p class="wpfb-error">' . esc_html__( 'Form configuration error.', 'wpfb' ) . '</p>';
		}

		// Enqueue assets here as a fallback for dynamic/widget/block contexts
		// where has_shortcode() on the main query may not have fired.
		$this->enqueue_assets( $id );

		ob_start();
		$view = WPFB_DIR . 'views/frontend/form.php';
		if ( file_exists( $view ) ) {
			// Variables available to the template: $form, $fields, $form_id, $settings.
			$form_id  = $id; // Explicitly named so the template's nonce and data-attrs work.
			$settings = Options::get();
			include $view;
		}

		return ob_get_clean();
	}

	/**
	 * Enqueues frontend CSS and JS, localizing per-form data to the JS.
	 * Safe to call multiple times — WP's enqueue system deduplicates.
	 *
	 * @param int $form_id The form being rendered.
	 */
	public function enqueue_assets( int $form_id ): void {
		$settings = Options::get();

		wp_enqueue_style(
			'wpfb-frontend',
			WPFB_URL . 'assets/css/frontend.css',
			[],
			WPFB_VERSION
		);

		wp_enqueue_script(
			'wpfb-frontend',
			WPFB_URL . 'assets/js/frontend.js',
			[],
			WPFB_VERSION,
			true // Load in footer.
		);

		// Per-form nonce goes on the <form> element (data-nonce), not here.
		// We localize only shared data: ajax URL, mime list, max size, i18n.
		wp_localize_script(
			'wpfb-frontend',
			'wpfbData',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'allowedMimes' => (array) $settings['allowed_mime_types'],
				'maxSizeMb'    => (int) $settings['max_upload_mb'],
				'i18n'         => [
					'required'    => __( 'This field is required.', 'wpfb' ),
					'invalidEmail' => __( 'Please enter a valid email address.', 'wpfb' ),
					'fileTooLarge' => sprintf(
						/* translators: %d: max size in MB */
						__( 'File size must not exceed %d MB.', 'wpfb' ),
						(int) $settings['max_upload_mb']
					),
					'fileType'    => __( 'File type is not allowed.', 'wpfb' ),
					'submitting'  => __( 'Submitting…', 'wpfb' ),
					'error'       => __( 'An unexpected error occurred. Please try again.', 'wpfb' ),
				],
			]
		);
	}
}
