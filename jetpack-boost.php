<?php

use Automattic\Jetpack\Config;
use Automattic\Jetpack\Connection\Manager;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://automattic.com
 * @since             1.0.0
 * @package           Jetpack_Boost
 *
 * @wordpress-plugin
 * Plugin Name:       Jetpack Boost
 * Plugin URI:        https://jetpack.com
 * Description:       A collection of performance enhancements.
 * Version:           1.0.0
 * Author:            Automattic
 * Author URI:        https://automattic.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       jetpack-boost
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'JETPACK_BOOST_VERSION', '1.0.0' );
define( 'JETPACK_BOOST_ENABLED', true );

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-jetpack-boost-activator.php
 */
function activate_jetpack_boost() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jetpack-boost-activator.php';
	Jetpack_Boost_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-jetpack-boost-deactivator.php
 */
function deactivate_jetpack_boost() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-jetpack-boost-deactivator.php';
	Jetpack_Boost_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_jetpack_boost' );
register_deactivation_hook( __FILE__, 'deactivate_jetpack_boost' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-jetpack-boost.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_jetpack_boost() {

	// Here we enable the Jetpack packages.
	$config = new Config();
	$config->ensure( 'connection' );

	$jetpack_connection_manager = new Manager();
	$plugin = new Jetpack_Boost( $jetpack_connection_manager );

	$plugin->run();
}

add_action( 'plugins_loaded', 'run_jetpack_boost', 1 );
