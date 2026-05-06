<?php
/**
 * PSR-4-style autoloader for the WPFB namespace.
 *
 * Mapping rule:
 *   WPFB\Admin\Forms_List_Page  → includes/admin/class-wpfb-forms-list-page.php
 *   WPFB\Mail\Mailer_Interface  → includes/mail/interface-wpfb-mailer.php   (suffix "_Interface" → prefix "interface-wpfb-", stripped)
 *   WPFB\Support\Options        → includes/support/class-wpfb-options.php
 *
 * Verified against sample classes:
 *   WPFB\Plugin                          → includes/class-wpfb-plugin.php
 *   WPFB\Admin\Menu                      → includes/admin/class-wpfb-menu.php
 *   WPFB\Admin\Ajax\Form_Save            → includes/admin/ajax/class-wpfb-ajax-form-save.php
 *   WPFB\Repository\Forms_Repository    → includes/repository/class-wpfb-forms-repository.php
 *   WPFB\Frontend\Submission_Handler    → includes/frontend/class-wpfb-submission-handler.php
 *   WPFB\Mail\Mailer_Interface          → includes/mail/interface-wpfb-mailer.php
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight namespace autoloader. No Composer required.
 */
class WPFB_Autoloader {

	/**
	 * Base namespace prefix for this plugin.
	 *
	 * @var string
	 */
	private $prefix = 'WPFB\\';

	/**
	 * Absolute path to the includes directory.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor — stores the base directory for class files.
	 *
	 * @param string $base_dir Absolute path to the /includes directory.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Registers the autoloader with SPL.
	 */
	public function register(): void {
		spl_autoload_register( [ $this, 'load_class' ] );
	}

	/**
	 * Attempts to load a class file by mapping the class name to a file path.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public function load_class( string $class ): void {
		// Only handle classes under our namespace prefix.
		if ( 0 !== strpos( $class, $this->prefix ) ) {
			return;
		}

		// Strip the root prefix to get the relative class path.
		$relative = substr( $class, strlen( $this->prefix ) );

		// Separate namespace segments from the class name.
		$parts      = explode( '\\', $relative );
		$class_name = array_pop( $parts );

		// Namespace segments become lowercase directory names.
		$sub_dir = '';
		foreach ( $parts as $segment ) {
			$sub_dir .= strtolower( $segment ) . DIRECTORY_SEPARATOR;
		}

		// Class name: underscores → hyphens, lowercased.
		$file_base = strtolower( str_replace( '_', '-', $class_name ) );

		// Interfaces use the "interface-wpfb-" prefix; classes use "class-wpfb-".
		// Detection: class name ends with "_Interface" (e.g. Mailer_Interface).
		if ( '-interface' === substr( $file_base, -10 ) ) {
			$prefix    = 'interface-wpfb-';
			// Strip the trailing "-interface" so Mailer_Interface → "mailer" → interface-wpfb-mailer.php
			$file_base = substr( $file_base, 0, -10 );
		} else {
			$prefix = 'class-wpfb-';
		}

		// Strip a leading "wpfb-" from the file base if the convention would double it.
		// e.g. class name "Options" → "class-wpfb-options.php" (no doubling needed).
		// class name "Wpfb_Foo" would become "class-wpfb-wpfb-foo.php" — strip inner "wpfb-".
		if ( 0 === strpos( $file_base, 'wpfb-' ) ) {
			$file_base = substr( $file_base, 5 );
		}

		$file = $this->base_dir . $sub_dir . $prefix . $file_base . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
