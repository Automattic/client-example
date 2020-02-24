<?php

require_once plugin_dir_path( __FILE__ ) . 'interface-connection-state.php';

class Registering_State implements Connection_State {

	public function get_post_action() {
		if( apply_filters( 'jetpack_use_iframe_authorization_flow', false ) ) {
			return Register_Iframe_Method::POST_ACTION;
		}

		return Register_Calypso_Method::POST_ACTION;
	}

	public function get_iframe_url() {
		return null;
	}

	public function get_disconnect_user_action() {
		return null;
	}

	public function get_disconnect_site_action() {
		return null;
	}

	public function get_default_text() {
		return "Set Up Connection";
	}
}
