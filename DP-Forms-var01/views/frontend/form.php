<?php
/**
 * View: Frontend form rendering.
 *
 * Variables available:
 *   $form     object   Form row.
 *   $fields   array    Decoded field definitions.
 *   $form_id  int      Form ID.
 *
 * The per-form nonce is set as data-nonce on the <form> element so that a page
 * containing multiple [wpfb_form] shortcodes gets independent nonces.
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$nonce        = wp_create_nonce( 'wpfb_submit_' . (int) $form_id );
$partials_dir = WPFB_DIR . 'views/frontend/partials/';

// Form-level wrapper data — new in v1.1.0, null-safe for v1.0.0 rows.
$form_css_class  = esc_attr( trim( 'wpfb-form ' . ( $form->form_css_class ?? '' ) ) );
$form_html_before = $form->form_html_before ?? '';
$form_html_after  = $form->form_html_after ?? '';
?>
<div class="wpfb-form-wrap" id="wpfb-form-wrap-<?php echo esc_attr( $form_id ); ?>">
	<form
		class="<?php echo $form_css_class; ?>"
		id="wpfb-form-<?php echo esc_attr( $form_id ); ?>"
		data-form-id="<?php echo esc_attr( $form_id ); ?>"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		novalidate
		enctype="multipart/form-data"
	>
		<?php echo wp_kses_post( $form_html_before ); ?>

		<?php foreach ( $fields as $field ) : ?>
			<?php
			$type    = $field['type'] ?? 'text';
			$partial = $partials_dir . 'field-' . sanitize_key( $type ) . '.php';
			if ( file_exists( $partial ) ) {
				// Emit per-field wrapper HTML (v1.1.0 — empty strings for v1.0.0 fields).
				echo wp_kses_post( $field['html_before'] ?? '' );
				include $partial;
				echo wp_kses_post( $field['html_after'] ?? '' );
			}
			?>
		<?php endforeach; ?>

		<div class="wpfb-form-footer">
			<button type="submit" class="wpfb-submit-btn">
				<?php esc_html_e( 'Submit', 'wpfb' ); ?>
			</button>
		</div>

		<?php echo wp_kses_post( $form_html_after ); ?>

		<div class="wpfb-form-messages" aria-live="polite"></div>
	</form>
</div>
