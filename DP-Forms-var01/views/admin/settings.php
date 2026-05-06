<?php
/**
 * View: Settings admin page.
 *
 * Uses the WordPress Settings API — options_update nonce is handled
 * automatically by settings_fields().
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'DP-Forms-var01 Settings', 'wpfb' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		// Outputs hidden fields + nonce for the settings group.
		settings_fields( 'wpfb_settings_group' );
		// Renders all registered sections and fields.
		do_settings_sections( 'wpfb_settings' );
		submit_button();
		?>
	</form>
</div>
