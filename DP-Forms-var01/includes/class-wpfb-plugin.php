<?php
/**
 * Plugin singleton boot controller.
 *
 * This is the only file that calls add_action()/add_filter() directly outside
 * of a class constructor. It wires every subsystem hook in one place.
 *
 * @package WPFB
 */

namespace WPFB;

defined( 'ABSPATH' ) || exit;

use WPFB\Admin\Menu;
use WPFB\Admin\Forms_List_Page;
use WPFB\Admin\Form_Builder_Page;
use WPFB\Admin\Submissions_Page;
use WPFB\Admin\Settings_Page;
use WPFB\Admin\Ajax\Ajax_Form_Save;
use WPFB\Admin\Ajax\Ajax_Form_Delete;
use WPFB\Frontend\Shortcode;
use WPFB\Frontend\Submission_Handler;
use WPFB\Repository\Forms_Repository;
use WPFB\Support\Options;

/**
 * Central boot class — singleton.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor — use instance() instead.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers all plugin hooks.
	 *
	 * Called once from the main plugin file after the autoloader is registered.
	 */
	public function boot(): void {
		// DB upgrade check — fires before everything else on plugins_loaded.
		// Handles dashboard updates where register_activation_hook does not fire.
		add_action( 'plugins_loaded', [ Activator::class, 'maybe_upgrade' ], 5 );

		// i18n — load text domain on plugins_loaded.
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Admin subsystem.
		if ( is_admin() ) {
			$this->boot_admin();
		}

		// Frontend subsystem (shortcode registered on all requests so it works
		// in REST / block editor contexts too).
		$this->boot_frontend();
	}

	/**
	 * Loads the plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpfb',
			false,
			dirname( plugin_basename( WPFB_FILE ) ) . '/languages'
		);
	}

	/**
	 * Boots the admin-side subsystems.
	 */
	private function boot_admin(): void {
		// Page controllers.
		$forms_list_page   = new Forms_List_Page();
		$form_builder_page = new Form_Builder_Page();
		$submissions_page  = new Submissions_Page();
		$settings_page     = new Settings_Page();

		// Menu wires all pages and enqueues admin assets.
		$menu = new Menu( $forms_list_page, $form_builder_page, $submissions_page, $settings_page );
		$menu->register_hooks();

		// Settings page needs admin_init for register_setting().
		$settings_page->register_hooks();

		// Admin-post handlers (CSV export, resend mail).
		$submissions_page->register_hooks();

		// AJAX form save/delete.
		( new Ajax_Form_Save() )->register_hooks();
		( new Ajax_Form_Delete() )->register_hooks();
	}

	/**
	 * Boots the frontend subsystems.
	 */
	private function boot_frontend(): void {
		$shortcode = new Shortcode();
		$shortcode->register_hooks();

		// Enqueue frontend assets early when shortcode is detected on the main
		// query (the shortcode itself also enqueues as a fallback for dynamic
		// contexts, e.g. block editor, widget areas).
		add_action( 'wp_enqueue_scripts', function() use ( $shortcode ) {
			global $post;
			if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wpfb_form' ) ) {
				// Determine which form IDs appear so we can pass the first one.
				// The per-form nonce lives on data-nonce attr, so we just pre-enqueue styles/scripts.
				$shortcode->enqueue_assets( 0 );
			}
		} );

		// Form submission handler (priv + nopriv).
		( new Submission_Handler() )->register_hooks();
	}
}
