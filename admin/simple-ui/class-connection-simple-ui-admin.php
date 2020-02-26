<?php

require_once plugin_dir_path( __FILE__ ) . 'connection-states/class-registering-state.php';
require_once plugin_dir_path( __FILE__ ) . 'connection-states/class-authorizing-state.php';
require_once plugin_dir_path( __FILE__ ) . 'connection-states/class-connected-state.php';
require_once plugin_dir_path( __FILE__ ) . 'register-methods/class-register-iframe-method.php';
require_once plugin_dir_path( __FILE__ ) . 'register-methods/class-register-calypso-method.php';

class Connection_Admin {

	public const AUTH_POST_ACTION            = 'authorize_user_simple_ui';
	public const DISCONNECT_USER_POST_ACTION = 'disconnect_user_simple_ui';
	public const DISCONNECT_SITE_POST_ACTION = 'disconnect_site_simple_ui';

	private $registering_state;
	private $authorizing_state;
	private $connected_state;

	private $register_iframe_method;
	private $register_calypso_method;

	public $manager;
	private $connection_state;

	private $error;

	public function __construct( $manager ) {
		$this->manager = $manager;

		$this->registering_state  = new Registering_State( $this );
		$this->authorizing_state  = new Authorizing_State( $this );
		$this->connected_state   = new Connected_State();

		$this->register_iframe_method  = new Register_Iframe_Method( $this );
		$this->register_calypso_method = new Register_Calypso_Method( $this );

		$this->set_state();
		$this->set_up_post_request_handlers();
	}

	public function admin_page_load() {
		if ( isset( $_GET['error'] ) ) {
			$this->error = $_GET['error'];
			add_action( 'admin_notices', array( $this, 'display_error_notice' ) );
		}
	}

	private function set_up_post_request_handlers() {
		add_action( 'admin_post_' . $this->register_iframe_method::POST_ACTION,
			array( $this->register_iframe_method, 'register_site' ) );

		add_action( 'admin_post_' . $this->register_calypso_method::POST_ACTION,
			array( $this->register_calypso_method, 'register_site' ) );

		add_action( 'admin_post_' . self::AUTH_POST_ACTION, array( $this, 'authorize_user' ) );
		add_action( 'admin_post_' . self::DISCONNECT_USER_POST_ACTION, array( $this, 'disconnect_user' ) );
		add_action( 'admin_post_' . self::DISCONNECT_SITE_POST_ACTION, array( $this, 'disconnect_site' ) );
	}

	private function set_state() {
		$registered = $this->manager->get_access_token();
		$authorized = $this->manager->is_user_connected();

		if ( ! $registered ) {
			$this->connection_state = $this->registering_state;

		} elseif ( ! $authorized ) {
			$this->connection_state = $this->authorizing_state;

		} else {
			$this->connection_state = $this->connected_state;
		}
	}

	public function get_post_action() {
		return $this->connection_state->get_post_action();
	}

	public function get_iframe_url() {
		return $this->connection_state->get_iframe_url();
	}

	public function get_disconnect_user_action() {
		return $this->connection_state->get_disconnect_user_action();
	}

	public function get_disconnect_site_action() {
		return $this->connection_state->get_disconnect_site_action();
	}

	public function get_default_text() {
		return $this->connection_state->get_default_text();
	}

	public function authorize_user() {
		check_admin_referer( self::AUTH_POST_ACTION );
		$result = $this->manager->connect_user();
		$this->check_for_error_and_redirect( $result );
	}

	public function disconnect_user() {
		check_admin_referer( self::DISCONNECT_USER_POST_ACTION );
		$result = $this->manager->disconnect_user( get_current_user_id() );

		if ( false === $result ) {
			$result = new WP_Error(
				'disconnect_user_failed',
				'Could not disconnect the user. Either the user isn\'t connected,
				 or the user is the master user.' );
		}

		$this->check_for_error_and_redirect( $result );
	}

	public function disconnect_site() {
		check_admin_referer( self::DISCONNECT_SITE_POST_ACTION );
		$this->manager->disconnect_site_wpcom();
		$this->manager->delete_all_connection_tokens();
		$this->check_for_error_and_redirect( null );
	}

	public function check_for_error_and_redirect( $result ){
		if ( wp_get_referer() ) {
			$redirect_url = wp_get_referer();

			if (is_wp_error( $result ) ) {
				error_log($result->get_error_message());
				$redirect_url = add_query_arg( 'error', $result->get_error_message(), $redirect_url );
			}
			wp_safe_redirect( $redirect_url );

		} else {
			wp_safe_redirect( get_home_url() );
		}
	}

	public function display_error_notice() {
		?>
	    <div class="notice notice-error">
	        <p><?php echo $this->error; ?></p>
	    </div>
    <?php
	}
}
