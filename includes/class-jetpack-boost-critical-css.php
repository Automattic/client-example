<?php

require_once 'class-jetpack-boost-filecache.php';

class Jetpack_Boost_Critical_CSS {
	private static $__instance = null;

	/**
	 * Singleton implementation
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_Boost_Critical_CSS' ) ) {
			self::$__instance = new Jetpack_Boost_Critical_CSS();
		}

		return self::$__instance;
	}

	private function __construct() {
		// add_action( 'template_redirect', array( $this, 'begin_buffer_html' ) );
		// detect enabled with:
		// apply_filters( 'jetpack_boost_inject_critical_css', false )
		add_action('wp_head', array( $this, 'inject_critical_css' ), 0 );
		add_filter( 'jetpack_boost_inject_critical_css', '__return_true' );
	}

	function inject_critical_css() {
		// critical CSS blows up our CSS size
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return;
		}

		// fetch critical CSS - this must be done in a background job or we might
		// end up in a loop where every request to a URL triggers a request from our server to
		// the URL which tries again to get the URL...

		list( $cdn_url, $cdn_params ) = $this->get_cdn_params_for_current_page();
		list( $response_headers, $response_body ) = Jetpack_Boost_Filecache::get_cached_response( $cdn_url, 'POST', 'css', $cdn_params );

		if ( $response_body ) {
			error_log("got cached crticial CSS!");
			echo '<style id="jetpack-boost-critical-css" type="text/css">' . "\n";
			echo $response_body; // TODO: sanitize response!!! Someone might write a malicious file to disk...
			echo "\n</style>";
		} else {
			// fetch and cache response in background job?
		}
	}

	function get_cdn_params_for_current_page() {
		global $wp;
		$current_url = home_url( add_query_arg( $_GET, $wp->request ) );
		$cdn_server = apply_filters( 'jetpack_boost_cdn_url', 'http://localhost:8090' );
		$cdn_params = [ 'url' => $current_url ];
		$cdn_url = $cdn_server . '/critical';
		return [ $cdn_url, $cdn_params ];
	}
}
