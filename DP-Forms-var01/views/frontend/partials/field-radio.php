<?php
/**
 * Partial: Radio button group.
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
$req_mark      = $required ? ' <span class="wpfb-required" aria-hidden="true">*</span>' : '';
$options       = isset( $field['options'] ) ? (array) $field['options'] : [];
$wrapper_class = esc_attr( trim( 'wpfb-field wpfb-field-radio ' . ( $field['wrapper_class'] ?? '' ) ) );
// css_class applied to the fieldset/group wrapper, not individual inputs.
$group_class   = esc_attr( trim( 'wpfb-radio-group ' . ( $field['css_class'] ?? '' ) ) );
?>
<div class="<?php echo $wrapper_class; ?>" id="wpfb-field-wrap-<?php echo $field_id; ?>">
	<fieldset class="wpfb-fieldset">
		<legend class="wpfb-field-legend">
			<?php echo $label; ?><?php echo $req_mark; ?>
		</legend>
		<div class="<?php echo $group_class; ?>">
			<?php foreach ( $options as $i => $option ) : ?>
				<?php
				$opt_id   = esc_attr( $field['id'] . '-' . $i );
				$opt_val  = esc_attr( $option );
				$opt_label = esc_html( $option );
				?>
				<label class="wpfb-radio-label" for="<?php echo $opt_id; ?>">
					<input
						type="radio"
						id="<?php echo $opt_id; ?>"
						name="<?php echo $field_id; ?>"
						value="<?php echo $opt_val; ?>"
					>
					<?php echo $opt_label; ?>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<span class="wpfb-field-error" role="alert" aria-live="polite"></span>
</div>
