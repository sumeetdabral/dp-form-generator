<?php
/**
 * Partial: Select field.
 *
 * Variables available:
 *   $field  array  { id, type, label, required, options[], css_class?, wrapper_class? }
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_attr      = $required ? ' required aria-required="true"' : '';
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$options       = isset( $field['options'] ) ? (array) $field['options'] : [];
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-select ' . ( $field['wrapper_class'] ?? '' ) ) );
$input_class   = esc_attr( trim( 'wpfb-input ' . ( $field['css_class'] ?? '' ) ) );
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<label for="wpfb-<?php echo $field_id; ?>">
		<?php echo $label; ?><?php echo $req_mark; ?>
	</label>
	<select
		id="wpfb-<?php echo $field_id; ?>"
		name="<?php echo $field_id; ?>"
		class="<?php echo $input_class; ?>"
		<?php echo $req_attr; ?>
	>
		<option value=""><?php esc_html_e( '— Select —', 'wpfb' ); ?></option>
		<?php foreach ( $options as $option ) : ?>
			<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
		<?php endforeach; ?>
	</select>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
