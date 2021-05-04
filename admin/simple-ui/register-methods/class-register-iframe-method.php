<?php

use Automattic\Jetpack\Connection\Tokens;

class Register_Iframe_Method {

	const POST_ACTION = 'register_site_iframe';

	private $connection_admin;

	public function __construct( $connection_admin ) {
		$this->connection_admin = $connection_admin;
	}

	public function register_site() {
		check_admin_referer( self::POST_ACTION );

		$this->connection_admin->manager->enable_plugin();

		if ( ! ( new Tokens() )->get_access_token() ) {
			$result = $this->connection_admin->manager->register();
		}

		$this->connection_admin->check_for_error_and_redirect( $result );
	}
}
