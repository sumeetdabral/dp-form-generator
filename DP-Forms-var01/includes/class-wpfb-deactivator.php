<?php
/**
 * Plugin deactivation handler.
 *
 * Deactivation intentionally does NOT delete data. That is handled by
 * uninstall.php when the plugin is fully removed, if the admin has opted in.
 *
 * @package WPFB
 */

namespace WPFB;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
class Deactivator {

	/**
	 * Runs on plugin deactivation.
	 * Called via register_deactivation_hook() in the main plugin file.
	 */
	public static function deactivate(): void {
		// Clear rewrite rules so our shortcode doesn't leave stale rules.
		flush_rewrite_rules();
	}
}
