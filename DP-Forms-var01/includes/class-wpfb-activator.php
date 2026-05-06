<?php
/**
 * Plugin activation handler.
 *
 * Creates both custom tables with dbDelta and seeds default wpfb_settings
 * if the option does not yet exist. Re-activation is safe — dbDelta is
 * idempotent and the option seed only runs when the key is absent.
 *
 * @package WPFB
 */

namespace WPFB;

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Options;

/**
 * Handles plugin activation: table creation and option seeding.
 */
class Activator {

	/**
	 * Runs on plugin activation.
	 * Called via register_activation_hook() in the main plugin file.
	 */
	public static function activate(): void {
		self::create_tables();
		self::seed_options();
		// Record the current DB version so maybe_upgrade() skips on next plugins_loaded.
		update_option( 'wpfb_db_version', WPFB_VERSION, false );
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugins_loaded (priority 5) to handle dashboard updates.
	 *
	 * register_activation_hook() does NOT fire when updating via the WP dashboard;
	 * this routine ensures the DB schema is current after any update.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( 'wpfb_db_version', '0.0.0' );

		if ( version_compare( $stored, WPFB_VERSION, '<' ) ) {
			self::create_tables(); // dbDelta is idempotent — adds new columns safely.
			update_option( 'wpfb_db_version', WPFB_VERSION, false );
		}
	}

	/**
	 * Creates the wpfb_forms and wpfb_submissions tables using dbDelta.
	 *
	 * dbDelta is idempotent — safe to run on re-activation.
	 * Column order and formatting must follow dbDelta's strict rules
	 * (two spaces after PRIMARY KEY, no trailing commas on last key line).
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$forms_table     = $wpdb->prefix . 'wpfb_forms';
		$sub_table       = $wpdb->prefix . 'wpfb_submissions';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$forms_sql = "CREATE TABLE {$forms_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  fields_json LONGTEXT NOT NULL,
  admin_emails TEXT NOT NULL DEFAULT '',
  mail_subject VARCHAR(191) NOT NULL DEFAULT '',
  attach_files TINYINT(1) NOT NULL DEFAULT 1,
  form_html_before LONGTEXT NULL,
  form_html_after LONGTEXT NULL,
  form_css_class VARCHAR(191) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id)
) {$charset_collate};";

		$submissions_sql = "CREATE TABLE {$sub_table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id BIGINT UNSIGNED NOT NULL,
  payload_json LONGTEXT NOT NULL,
  attachment_ids VARCHAR(255) NOT NULL DEFAULT '',
  submitter_ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  mail_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  mail_error TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY form_id (form_id),
  KEY created_at (created_at)
) {$charset_collate};";
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		dbDelta( $forms_sql );
		dbDelta( $submissions_sql );
	}

	/**
	 * Seeds the wpfb_settings option with defaults if it does not already exist.
	 * Uses false === get_option() to distinguish "absent" from "empty array".
	 */
	private static function seed_options(): void {
		if ( false === get_option( Options::OPTION_KEY ) ) {
			update_option( Options::OPTION_KEY, Options::get_defaults(), false );
		}
	}
}
