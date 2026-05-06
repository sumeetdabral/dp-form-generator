<?php
/**
 * Partial: Date field.
 *
 * Variables available:
 *   $field  array  { id, type, label, required, min?, max?, css_class?, wrapper_class? }
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_attr      = $required ? ' required aria-required="true"' : '';
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-date ' . ( $field['wrapper_class'] ?? '' ) ) );
$input_class   = esc_attr( trim( 'wpfb-input ' . ( $field['css_class'] ?? '' ) ) );

// Build optional min/max attributes (must be Y-m-d format).
$extra_attrs = '';
if ( isset( $field['min'] ) && '' !== $field['min'] ) {
	$extra_attrs .= ' min="' . esc_attr( $field['min'] ) . '"';
}
if ( isset( $field['max'] ) && '' !== $field['max'] ) {
	$extra_attrs .= ' max="' . esc_attr( $field['max'] ) . '"';
}
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<label for="wpfb-<?php echo $field_id; ?>">
		<?php echo $label; ?><?php echo $req_mark; ?>
	</label>
	<input
		type="date"
		id="wpfb-<?php echo $field_id; ?>"
		name="<?php echo $field_id; ?>"
		class="<?php echo $input_class; ?>"
		<?php echo $req_attr . $extra_attrs; ?>
	>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
