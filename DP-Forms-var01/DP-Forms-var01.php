<?php
/**
 * Plugin Name:       DP-Forms-var01
 * Plugin URI:        https://1apollo.co/
 * Description:       Drag-and-drop form builder with Brevo transactional email, file uploads via the Media Library, and a full submissions viewer with CSV export.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sumeet Dabral
 * Author URI:        https://1apollo.co/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpfb
 * Domain Path:       /languages
 *
 * @package WPFB
 */

defined( 'ABSPATH' ) || exit;

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------

define( 'WPFB_VERSION', '1.1.0' );
define( 'WPFB_FILE', __FILE__ );
define( 'WPFB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFB_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Autoloader
// -------------------------------------------------------------------------

require_once WPFB_DIR . 'includes/class-wpfb-autoloader.php';

$wpfb_autoloader = new WPFB_Autoloader( WPFB_DIR . 'includes' );
$wpfb_autoloader->register();

// -------------------------------------------------------------------------
// Lifecycle hooks
// -------------------------------------------------------------------------

register_activation_hook( __FILE__, [ 'WPFB\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WPFB\\Deactivator', 'deactivate' ] );

// -------------------------------------------------------------------------
// Boot
// -------------------------------------------------------------------------

WPFB\Plugin::instance()->boot();
