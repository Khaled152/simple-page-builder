<?php
/**
 * Plugin Name:       Simple Page Builder
 * Description:       Bulk create WordPress pages via a secure REST API with API key auth, rate limiting, logging, webhooks, and an admin UI.
 * Version:           1.0.0
 * Author:            Simple Page Builder
 * Text Domain:       simple-page-builder
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package Simple_Page_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants.
define( 'SPB_PLUGIN_VERSION', '1.0.0' );
define( 'SPB_PLUGIN_FILE', __FILE__ );
define( 'SPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPB_DB_VERSION', '1.0.0' );

// Includes.
require_once SPB_PLUGIN_DIR . 'includes/helpers.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-database.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-settings.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-logger.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-rate-limiter.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-auth.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-webhook.php';
require_once SPB_PLUGIN_DIR . 'includes/class-spb-rest-controller.php';
require_once SPB_PLUGIN_DIR . 'admin/class-spb-admin.php';

/**
 * Activation hook.
 */
function spb_activate() {
	\SPB\Database::activate();
	\SPB\Settings::bootstrap_defaults();
}
register_activation_hook( __FILE__, 'spb_activate' );


/**
 * Initialize plugin (after plugins loaded).
 */
function spb_init() {
	// Initialize settings.
	\SPB\Settings::init();

	// Initialize REST API routes.
	add_action(
		'rest_api_init',
		static function () {
			$controller = new \SPB\REST_Controller();
			$controller->register_routes();
		}
	);

	// Initialize Admin UI.
	if ( is_admin() ) {
		new \SPB\Admin();
	}
}
add_action( 'plugins_loaded', 'spb_init' );

// /**
//  * Plugin loaded text domain (if translations are provided in future).
//  */
// function spb_load_textdomain() {
// 	load_plugin_textdomain( 'simple-page-builder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
// }
// add_action( 'init', 'spb_load_textdomain' );


