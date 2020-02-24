<?php

require_once plugin_dir_path( __FILE__ ) . 'interface-connection-state.php';

class Authorizing_State implements Connection_State {

	private $connection_admin;

	public function __construct( $connection_admin ) {
		$this->connection_admin = $connection_admin;
	}

	public function get_post_action() {
		if( apply_filters( 'jetpack_use_iframe_authorization_flow', false ) ) {
			return null;
		}

		return Connection_Admin::AUTH_POST_ACTION;
	}

	public function get_iframe_url() {
		if( apply_filters( 'jetpack_use_iframe_authorization_flow', false ) ) {
			$auth_url = $this->connection_admin->manager->get_authorization_url( null );
			return $auth_url;
		}

		return null;
	}

	public function get_disconnect_user_action() {
		return null;
	}

	public function get_disconnect_site_action() {
		return Connection_Admin::DISCONNECT_SITE_POST_ACTION;
	}

	public function get_default_text() {
		return "Authorize User";
	}
}
