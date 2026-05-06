<?php
/**
 * Partial: File upload field.
 *
 * Variables available:
 *   $field     array  { id, type, label, required, css_class?, wrapper_class? }
 *   $settings  array  Plugin settings (for accept attribute and max size hint).
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_attr      = $required ? ' required aria-required="true"' : '';
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$allowed_ext   = isset( $settings['allowed_mime_types'] ) ? (array) $settings['allowed_mime_types'] : [];
$max_mb        = isset( $settings['max_upload_mb'] ) ? (int) $settings['max_upload_mb'] : 5;
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-file ' . ( $field['wrapper_class'] ?? '' ) ) );
$input_class   = esc_attr( trim( 'wpfb-input ' . ( $field['css_class'] ?? '' ) ) );

// Build accept attribute from allowed extensions.
$accept = implode( ',', array_map( fn( $e ) => '.' . ltrim( $e, '.' ), $allowed_ext ) );
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<label for="wpfb-<?php echo $field_id; ?>">
		<?php echo $label; ?><?php echo $req_mark; ?>
	</label>
	<input
		type="file"
		id="wpfb-<?php echo $field_id; ?>"
		name="<?php echo $field_id; ?>"
		class="<?php echo $input_class; ?>"
		<?php echo $req_attr; ?>
		<?php if ( $accept ) : ?>accept="<?php echo esc_attr( $accept ); ?>"<?php endif; ?>
		data-max-mb="<?php echo esc_attr( $max_mb ); ?>"
	>
	<p class="wpfb-file-hint description">
		<?php
		printf(
			/* translators: 1: comma-separated extensions, 2: max size in MB */
			esc_html__( 'Allowed: %1$s. Max size: %2$d MB.', 'wpfb' ),
			esc_html( implode( ', ', $allowed_ext ) ),
			$max_mb
		);
		?>
	</p>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
