<?php

require_once plugin_dir_path( __FILE__ ) . 'interface-connection-state.php';

class Connected_State implements Connection_State {

	public function get_post_action() {
		return null;
	}

	public function get_iframe_url() {
		return null;
	}

	public function get_disconnect_user_action() {
		return Connection_Admin::DISCONNECT_USER_POST_ACTION;
	}

	public function get_disconnect_site_action() {
		return Connection_Admin::DISCONNECT_SITE_POST_ACTION;
	}

	public function get_default_text() {
		return "The site is registered, and the user is authorized";
	}
}
