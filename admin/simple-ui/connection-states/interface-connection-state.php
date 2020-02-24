<?php

interface Connection_State {

	public function get_post_action();

	public function get_iframe_url();

	public function get_disconnect_user_action();

	public function get_disconnect_site_action();

	public function get_default_text();
}
