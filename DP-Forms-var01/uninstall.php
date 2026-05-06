<?php
/**
 * Plugin uninstall handler.
 *
 * Runs when the admin clicks "Delete" on the Plugins screen.
 * Data is only removed if the admin opted in via the
 * `delete_data_on_uninstall` setting.
 *
 * NOTE: This file runs WITHOUT the autoloader or any plugin classes loaded —
 * WP bootstraps it in a stripped context. All DB and option operations are
 * inlined here using $wpdb directly.
 *
 * @package WPFB
 */

// WordPress sets this constant before calling uninstall.php. Bail if absent.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Read settings from the option. We do NOT use the Options class here because
// the autoloader is not available in the uninstall context.
$settings = get_option( 'wpfb_settings', [] );
$should_delete = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $should_delete ) {
	// User chose to preserve data — exit without doing anything.
	return;
}

// Drop both custom tables.
$forms_table = $wpdb->prefix . 'wpfb_forms';
$sub_table   = $wpdb->prefix . 'wpfb_submissions';

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$forms_table}`" );
$wpdb->query( "DROP TABLE IF EXISTS `{$sub_table}`" );
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete plugin options.
delete_option( 'wpfb_settings' );
delete_option( 'wpfb_log' );

// Clear any object cache entries for these options.
wp_cache_delete( 'wpfb_settings', 'options' );
wp_cache_delete( 'wpfb_log', 'options' );
