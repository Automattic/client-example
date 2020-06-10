<?php

class Register_Calypso_Method {

	const POST_ACTION = 'register_site_calypso';

	private $connection_admin;

	public function __construct( $connection_admin ) {
		$this->connection_admin = $connection_admin;
	}

	public function register_site() {
		check_admin_referer( self::POST_ACTION );

		$result = null;

		$this->connection_admin->manager->enable_plugin();

		if ( ! $this->connection_admin->manager->get_access_token() ) {
			$result = $this->connection_admin->manager->register();
		}

		if ( is_wp_error( $result ) ) {
			$this->connection_admin->check_for_error_and_redirect( $result );
		}

		if ( ! $this->connection_admin->manager->is_user_connected() ) {
			$result = $this->connection_admin->manager->connect_user();
		}

		$this->connection_admin->check_for_error_and_redirect( $result );
	}
}
