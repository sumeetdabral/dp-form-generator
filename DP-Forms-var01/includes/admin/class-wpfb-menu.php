<?php
/**
 * Registers the admin menu hierarchy for DP-Forms-var01.
 *
 * Top-level: "DP-Forms-var01"
 * Subpages : Forms (list), Add New, Submissions, Settings
 *
 * Also registers admin-page asset enqueuing (only on our own pages).
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all WP admin menu entries for the plugin.
 */
class Menu {

	/**
	 * Page controller instances.
	 *
	 * @var Forms_List_Page
	 */
	private Forms_List_Page $forms_list_page;

	/**
	 * @var Form_Builder_Page
	 */
	private Form_Builder_Page $form_builder_page;

	/**
	 * @var Submissions_Page
	 */
	private Submissions_Page $submissions_page;

	/**
	 * @var Settings_Page
	 */
	private Settings_Page $settings_page;

	/**
	 * Constructor — receives page controller instances.
	 *
	 * @param Forms_List_Page   $forms_list_page   Forms list page controller.
	 * @param Form_Builder_Page $form_builder_page  Form builder page controller.
	 * @param Submissions_Page  $submissions_page   Submissions page controller.
	 * @param Settings_Page     $settings_page      Settings page controller.
	 */
	public function __construct(
		Forms_List_Page $forms_list_page,
		Form_Builder_Page $form_builder_page,
		Submissions_Page $submissions_page,
		Settings_Page $settings_page
	) {
		$this->forms_list_page   = $forms_list_page;
		$this->form_builder_page = $form_builder_page;
		$this->submissions_page  = $submissions_page;
		$this->settings_page     = $settings_page;
	}

	/**
	 * Registers the admin_menu and admin_enqueue_scripts hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Adds the top-level menu and subpages to the WP admin sidebar.
	 */
	public function register_menu(): void {
		// Top-level menu entry — renders the forms list.
		add_menu_page(
			__( 'DP-Forms-var01', 'wpfb' ),
			__( 'DP-Forms-var01', 'wpfb' ),
			'manage_options',
			'wpfb-forms',
			[ $this->forms_list_page, 'render' ],
			'dashicons-feedback',
			58
		);

		// "Forms" submenu (duplicate of top-level to rename it).
		add_submenu_page(
			'wpfb-forms',
			__( 'All Forms', 'wpfb' ),
			__( 'All Forms', 'wpfb' ),
			'manage_options',
			'wpfb-forms',
			[ $this->forms_list_page, 'render' ]
		);

		// Add New form.
		add_submenu_page(
			'wpfb-forms',
			__( 'Add New Form', 'wpfb' ),
			__( 'Add New', 'wpfb' ),
			'manage_options',
			'wpfb-form-builder',
			[ $this->form_builder_page, 'render' ]
		);

		// Submissions.
		add_submenu_page(
			'wpfb-forms',
			__( 'Submissions', 'wpfb' ),
			__( 'Submissions', 'wpfb' ),
			'manage_options',
			'wpfb-submissions',
			[ $this->submissions_page, 'render' ]
		);

		// Settings.
		add_submenu_page(
			'wpfb-forms',
			__( 'Settings', 'wpfb' ),
			__( 'Settings', 'wpfb' ),
			'manage_options',
			'wpfb-settings',
			[ $this->settings_page, 'render' ]
		);
	}

	/**
	 * Enqueues admin CSS and JS only on the plugin's own admin pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only enqueue on our pages.
		$our_pages = [
			'toplevel_page_wpfb-forms',
			'dp-forms-var01_page_wpfb-form-builder',
			'dp-forms-var01_page_wpfb-submissions',
			'dp-forms-var01_page_wpfb-settings',
		];

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpfb-admin',
			WPFB_URL . 'assets/css/admin.css',
			[],
			WPFB_VERSION
		);

		// Builder JS only on the form-builder page.
		if ( 'dp-forms-var01_page_wpfb-form-builder' === $hook ) {
			wp_enqueue_script(
				'wpfb-admin-builder',
				WPFB_URL . 'assets/js/admin-builder.js',
				[],
				WPFB_VERSION,
				true
			);

			wp_localize_script(
				'wpfb-admin-builder',
				'wpfbBuilder',
				[
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'wpfb_save_form' ),
					'fieldTypes' => \WPFB\Support\Field_Types::valid_types(),
					'i18n'       => [
						'saved'       => __( 'Form saved.', 'wpfb' ),
						'saving'      => __( 'Saving…', 'wpfb' ),
						'error'       => __( 'Could not save form. Please try again.', 'wpfb' ),
						'confirmDel'  => __( 'Remove this field?', 'wpfb' ),
						'noFields'    => __( 'Please add at least one field.', 'wpfb' ),
						'noTitle'     => __( 'Please enter a form title.', 'wpfb' ),
						'copied'      => __( 'Copied!', 'wpfb' ),
					],
				]
			);
		}

		// Submissions list: enqueue only admin CSS (already done above).
	}
}
