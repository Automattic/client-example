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
 * @package           Client_Example
 *
 * @wordpress-plugin
 * Plugin Name:       Jetpack Client Example
 * Plugin URI:        https://jetpack.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Automattic
 * Author URI:        https://automattic.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       client-example
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
define( 'CLIENT_EXAMPLE_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-client-example-activator.php
 */
function activate_client_example() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-client-example-activator.php';
	Client_Example_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-client-example-deactivator.php
 */
function deactivate_client_example() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-client-example-deactivator.php';
	Client_Example_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_client_example' );
register_deactivation_hook( __FILE__, 'deactivate_client_example' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-client-example.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */

function wp_startswith( $haystack, $needle ) {
	return 0 === strpos( $haystack, $needle );
}

function jetpack_json_wrap( &$any, $seen_nodes = array() ) {
	if ( is_object( $any ) ) {
		$input        = get_object_vars( $any );
		$input['__o'] = 1;
	} else {
		$input = &$any;
	}

	if ( is_array( $input ) ) {
		$seen_nodes[] = &$any;

		$return = array();

		foreach ( $input as $k => &$v ) {
			if ( ( is_array( $v ) || is_object( $v ) ) ) {
				if ( in_array( $v, $seen_nodes, true ) ) {
					continue;
				}
				$return[ $k ] = jetpack_json_wrap( $v, $seen_nodes );
			} else {
				$return[ $k ] = $v;
			}
		}

		return $return;
	}

	return $any;
}



/**
 * @param string $sandbox Sandbox domain
 * @param string $url URL of request about to be made
 * @param array  $headers Headers of request about to be made
 * @return array [ 'url' => new URL, 'host' => new Host ]
 */
function jetpack_server_sandbox_request_parameters( $sandbox, $url, $headers ) {
	$host = '';

	$url_host = wp_parse_url( $url, PHP_URL_HOST );

	switch ( $url_host ) {
		case 'public-api.wordpress.com' :
		case 'jetpack.wordpress.com' :
		case 'jetpack.com' :
		case 'dashboard.wordpress.com' :
			$host = isset( $headers['Host'] ) ? $headers['Host'] : $url_host;
			$url = preg_replace(
				'@^(https?://)' . preg_quote( $url_host, '@' ) . '(?=[/?#].*|$)@',
				'${1}' . $sandbox,
				$url,
				1
			);
	}

	return compact( 'url', 'host' );
}

/**
 * Modifies parameters of request in order to send the request to the
 * server specified by `JETPACK__SANDBOX_DOMAIN`.
 *
 * Attached to the `requests-requests.before_request` filter.
 * @param string &$url URL of request about to be made
 * @param array  &$headers Headers of request about to be made
 * @return void
 */
function jetpack_server_sandbox( &$url, &$headers ) {
	if ( ! JETPACK__SANDBOX_DOMAIN ) {
		return;
	}

	$original_url = $url;

	$request_parameters = jetpack_server_sandbox_request_parameters( JETPACK__SANDBOX_DOMAIN, $url, $headers );
	$url = $request_parameters['url'];
	if ( $request_parameters['host'] ) {
		$headers['Host'] = $request_parameters['host'];
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( "SANDBOXING via '%s': '%s'", JETPACK__SANDBOX_DOMAIN, $original_url ) );
		}
	}
}

add_action( 'requests-requests.before_request', 'jetpack_server_sandbox', 10, 2 );



function run_client_example() {

	// Here we enable the Jetpack packages.
	$config = new Config();
	$config->ensure( 'connection' );

	$jetpack_connection_manager = new Manager();
	$plugin = new Client_Example( $jetpack_connection_manager );

	$plugin->run();

	$config->ensure('sync');
	\Automattic\Jetpack\Sync\Actions::init();


	$queue = new Automattic\Jetpack\Sync\Queue('sync');

	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);
	$post = wp_insert_post(['post_title' => 'asd'.microtime(true), 'post_content' => 'blarghhgh'.microtime(true)]);


//	print_r($post);
//
	var_dump(count($queue->get_all()));
	//$queue->flush_all();
}

add_action( 'plugins_loaded', 'run_client_example', 1 );
