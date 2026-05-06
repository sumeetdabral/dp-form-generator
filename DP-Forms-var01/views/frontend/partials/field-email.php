<?php
/**
 * Partial: Email field.
 *
 * Variables available:
 *   $field  array  { id, type, label, required, css_class?, wrapper_class? }
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_attr      = $required ? ' required aria-required="true"' : '';
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-email ' . ( $field['wrapper_class'] ?? '' ) ) );
$input_class   = esc_attr( trim( 'wpfb-input ' . ( $field['css_class'] ?? '' ) ) );
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<label for="wpfb-<?php echo $field_id; ?>">
		<?php echo $label; ?><?php echo $req_mark; ?>
	</label>
	<input
		type="email"
		id="wpfb-<?php echo $field_id; ?>"
		name="<?php echo $field_id; ?>"
		class="<?php echo $input_class; ?>"
		<?php echo $req_attr; ?>
		autocomplete="email"
	>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
