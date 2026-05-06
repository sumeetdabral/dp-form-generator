<?php
/**
 * Partial: Checkboxes group.
 *
 * Variables available:
 *   $field  array  { id, type, label, required, options[], css_class?, wrapper_class? }
 *
 * NOTE: Input name is "{field_id}[]" so PHP auto-creates an array on POST.
 * Required validation is server-only (no individual checkbox carries required attr).
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

$field_id      = esc_attr( $field['id'] );
$label         = esc_html( $field['label'] );
$required      = ! empty( $field['required'] );
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$options       = isset( $field['options'] ) ? (array) $field['options'] : [];
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-checkboxes ' . ( $field['wrapper_class'] ?? '' ) ) );
// css_class applied to the group container.
$group_class   = esc_attr( trim( 'wpfb-checkbox-group ' . ( $field['css_class'] ?? '' ) ) );

// The array name used for error targeting in JS: field_id + "[]".
$input_name    = esc_attr( $field['id'] ) . '[]';
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<fieldset class="wpfb-fieldset">
		<legend class="wpfb-field-legend">
			<?php echo $label; ?><?php echo $req_mark; ?>
		</legend>
		<div class="<?php echo $group_class; ?>">
			<?php foreach ( $options as $i => $option ) : ?>
				<?php
				$opt_id    = esc_attr( $field['id'] . '-' . $i );
				$opt_val   = esc_attr( $option );
				$opt_label = esc_html( $option );
				?>
				<label class="wpfb-checkbox-label" for="<?php echo $opt_id; ?>">
					<input
						type="checkbox"
						id="<?php echo $opt_id; ?>"
						name="<?php echo $input_name; ?>"
						value="<?php echo $opt_val; ?>"
					>
					<?php echo $opt_label; ?>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
