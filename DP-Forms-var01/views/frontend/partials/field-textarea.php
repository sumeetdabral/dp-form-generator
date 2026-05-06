<?php
/**
 * Partial: Textarea field.
 *
 * Variables available:
 *   $field  array  { id, type, label, required, rows?, css_class?, wrapper_class? }
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_attr      = $required ? ' required aria-required="true"' : '';
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$rows          = isset( $field['rows'] ) ? max( 1, (int) $field['rows'] ) : 4;
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-textarea ' . ( $field['wrapper_class'] ?? '' ) ) );
$input_class   = esc_attr( trim( 'wpfb-input ' . ( $field['css_class'] ?? '' ) ) );
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<label for="wpfb-<?php echo $field_id; ?>">
		<?php echo $label; ?><?php echo $req_mark; ?>
	</label>
	<textarea
		id="wpfb-<?php echo $field_id; ?>"
		name="<?php echo $field_id; ?>"
		class="<?php echo $input_class; ?>"
		rows="<?php echo $rows; ?>"
		<?php echo $req_attr; ?>
	></textarea>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
