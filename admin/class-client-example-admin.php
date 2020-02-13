<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://automattic.com
 * @since      1.0.0
 *
 * @package    Client_Example
 * @subpackage Client_Example/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Client_Example
 * @subpackage Client_Example/admin
 * @author     Automattic <support@jetpack.com>
 */
class Client_Example_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 * @param      Automattic\Jetpack\Connection\Manager $manager The connection manager object.
	 */
	public function __construct( $plugin_name, $version, $manager ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->manager = $manager;

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_register_site', array( $this, 'register_site' ) );
		add_action( 'admin_post_connect_user', array( $this, 'connect_user' ) );
		add_action( 'admin_post_disconnect_user', array( $this, 'disconnect_user' ) );
		add_action( 'admin_post_disconnect_site', array( $this, 'disconnect_site' ) );

		add_filter( 'jetpack_connection_secret_generator', function( $callable ) {
			return function() {
				return wp_generate_password( 32, false );
			};
		} );
	}

	/**
	 * Runs the function that generates the admin menu for the plugin.
	 *
	 */
	public function admin_menu() {
		add_menu_page(
			'Client Example',
			'Client Example',
			'manage_options',
			'client-example',
			array( $this, 'generate_menu' ),
			'',
			4
		);
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Client_Example_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Client_Example_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/client-example-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Client_Example_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Client_Example_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/client-example-admin.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Generate the admin menu page.
	 */
	public function generate_menu() {
		require plugin_dir_path( __FILE__ ) . '/partials/client-example-admin-display.php';
	}

	/**
	 * Registers the site using the connection package.
	 */
	public function register_site() {
		check_admin_referer( 'register-site' );
		$this->manager->register();

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}

	/**
	 * Connects the currently logged in user.
	 */
	public function connect_user() {
		check_admin_referer( 'connect-user' );
		$this->manager->connect_user();

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}

	/**
	 * Disconnects the currently logged in user.
	 */
	public function disconnect_user() {
		check_admin_referer( 'disconnect-user' );
		$this->manager->disconnect_user( get_current_user_id() );

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}

	/**
	 * Disconnects the site.
	 */
	public function disconnect_site() {
		check_admin_referer( 'disconnect-site' );
		$this->manager->disconnect_site_wpcom();
		$this->manager->delete_all_connection_tokens();

		if ( wp_get_referer() ) {
			wp_safe_redirect( wp_get_referer() );
		} else {
			wp_safe_redirect( get_home_url() );
		}
	}
}
