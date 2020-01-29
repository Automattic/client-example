<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://automattic.com
 * @since      1.0.0
 *
 * @package    Jetpack_Boost
 * @subpackage Jetpack_Boost/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Jetpack_Boost
 * @subpackage Jetpack_Boost/public
 * @author     Automattic <support@jetpack.com>
 */
class Jetpack_Boost_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
	}

	public function plugins_loaded() {
		if ( JETPACK_BOOST_ENABLED ) {
			// minifier needs to be hooked before template_redirect, since it depends on that hook
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jetpack-boost-minify-html.php';
			Jetpack_Boost_Minify_HTML::instance();

			// TODO: check which of these are enabled
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-jetpack-boost-cdn.php';
			Jetpack_Boost_CDN::instance();
		}
	}

	/**
	 * Initialise necessary hooks as early as possible
	 *
	 * @since    1.0.0
	 */
	public function template_redirect() {

	}

}
