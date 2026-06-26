<?php
/**
 * Plugin Name:       KennelFlow Groom
 * Plugin URI:         https://wordpress.org/plugins/kennelflow-groom/
 * Description:        GroomPress for KennelFlow: grooming calendar, groomer role, commissions, earnings, and salon settings on shared pet data.
 * Version:            0.2.1
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Tested up to:       7.0
 * Requires Plugins:   kennelflow-core
 * Author:             LandTech Web Designs
 * License:            GPL-2.0-or-later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        kennelflow-groom
 *
 * @package KennelFlow_Groom
 */

defined( 'ABSPATH' ) || exit;

define( 'KENNELFLOW_GROOM_VERSION', '0.2.1' );
define( 'KENNELFLOW_GROOM_PLUGIN_FILE', __FILE__ );
define( 'KENNELFLOW_GROOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KENNELFLOW_GROOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KENNELFLOW_GROOM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/functions-kennelflow-groom.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-dependencies.php';

/**
 * @return void
 */
function kennelflow_groom_load_textdomain() {
	load_plugin_textdomain( 'kennelflow-groom', false, dirname( KENNELFLOW_GROOM_PLUGIN_BASENAME ) . '/languages' );
}
add_action( 'init', 'kennelflow_groom_load_textdomain', 0 );

/**
 * @return void
 */
function groompress_load_textdomain() {
	kennelflow_groom_load_textdomain();
}

require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-plugin.php';

register_activation_hook( KENNELFLOW_GROOM_PLUGIN_FILE, array( 'KennelFlow_Groom_Activator', 'activate' ) );
register_deactivation_hook( KENNELFLOW_GROOM_PLUGIN_FILE, array( 'KennelFlow_Groom_Activator', 'deactivate' ) );

/**
 * @return void
 */
function kennelflow_groom_bootstrap() {
	if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
		return;
	}

	add_action( 'init', 'kennelflow_groom_boot', 1 );
}

/**
 * @return void
 */
function groompress_bootstrap() {
	kennelflow_groom_bootstrap();
}

/**
 * @return void
 */
function kennelflow_groom_boot() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;

	KennelFlow_Groom_Plugin::instance()->init();
}

/**
 * @return void
 */
function groompress_boot() {
	kennelflow_groom_boot();
}

add_action( 'plugins_loaded', 'kennelflow_groom_bootstrap', 20 );
