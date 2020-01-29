<?php

/**
 * TODO
 * - asset inlining for smaller styles?
 * - critical CSS support?
 * - non-enqueued assets?
 */

class Jetpack_Boost_CDN {
	private static $__instance = null;

	public $cdn_server;
	private $base_url;
	private $serve_from_origin; // proxy files via the origin and cache locally - helps a lot with http2
	private $concat_style_groups = array();
	private $concat_script_groups = array();
	private $inject_critical_css = false;
	private $include_external_assets = false;
	private $max_assets_per_tag = 1;

	/**
	 * Singleton implementation
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_Boost_CDN' ) ) {
			self::$__instance = new Jetpack_Boost_CDN();
		}

		return self::$__instance;
	}

	public static function reset() {
		if ( null === self::$__instance ) {
			return;
		}

		// allow smaller CSS by only minifying assets on the page
		remove_filter( 'jetpack_implode_frontend_css', array( self::$__instance, 'jetpack_implode_frontend_css' ) );

		// concatenate selected CSS and JS tags
		remove_filter( 'script_loader_tag', array( self::$__instance, 'register_concat_scripts' ), -100 );
		remove_filter( 'style_loader_tag', array( self::$__instance, 'register_concat_styles' ), -100 );

		// rewrite URLs for selected CSS and JS tags
		remove_filter( 'script_loader_src', array( self::$__instance, 'rewrite_script_src' ), -100, 2 );
		remove_filter( 'style_loader_src', array( self::$__instance, 'rewrite_style_src' ), -100, 2 );

		// render buffered assets
		remove_action( 'wp_head', array( self::$__instance, 'render_concatenated_styles_head' ), PHP_INT_MAX );
		remove_action( 'wp_head', array( self::$__instance, 'render_concatenated_scripts_head' ), PHP_INT_MAX );
		remove_action( 'wp_footer', array( self::$__instance, 'render_concatenated_styles_footer' ), PHP_INT_MAX );
		remove_action( 'wp_footer', array( self::$__instance, 'render_concatenated_scripts_footer' ), PHP_INT_MAX );

		self::$__instance = null;
	}

	private function __construct() {
		// TODO: this should only be needed for development
		add_filter( 'block_local_requests', '__return_false' );

		$this->cdn_server = apply_filters( 'jetpack_boost_cdn_url', 'http://localhost:8090' );
		$this->serve_from_origin = apply_filters( 'jetpack_boost_serve_from_origin', true );
		$this->base_url = $this->serve_from_origin ? '/wp-json/jpb/v1' : $this->cdn_server;
		$this->include_external_assets = apply_filters( 'jetpack_boost_cdn_external_assets', false );

		// allow smaller CSS by only minifying assets on the page
		add_filter( 'jetpack_implode_frontend_css', array( self::$__instance, 'jetpack_implode_frontend_css' ) );

		// concatenate selected CSS and JS tags
		add_filter( 'script_loader_tag', array( $this, 'register_concat_scripts' ), -100, 3 );
		add_filter( 'style_loader_tag', array( $this, 'register_concat_styles' ), -100, 4 );

		// rewrite URLs for selected CSS and JS tags
		add_filter( 'script_loader_src', array( $this, 'rewrite_script_src' ), -100, 2 );
		add_filter( 'style_loader_src', array( $this, 'rewrite_style_src' ), -100, 2 );

		// flush remaining un-printed CDN assets
		add_action( 'wp_head', array( $this, 'render_concatenated_styles_head' ), PHP_INT_MAX );
		add_action( 'wp_head', array( $this, 'render_concatenated_scripts_head' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( $this, 'render_concatenated_styles_footer' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( $this, 'render_concatenated_scripts_footer' ), PHP_INT_MAX );

		// REST routes for serving CDN files from the origin. A read-through cache.
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'rest_pre_serve_request', array( $this, 'pre_serve_request' ), 99, 4 );
	}

	function register_endpoints() {
		register_rest_route( 'jpb/v1', '/js', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'rest_serve_js' )
		) );

		register_rest_route( 'jpb/v1', '/css', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'rest_serve_css' )
		) );
	}

	function pre_serve_request( $served, $result, $request, $server ) {
		if ( ! $served && preg_match( '|/jpb/v1/.*|', $request->get_route() ) ) {
			echo $result->get_data();
			return true;
		}
		return $served;
	}

	function rest_serve_js( $request ) {
		// TODO: caching
		// TODO: check for errors

		return $this->generate_cdn_response( '/js', $request->get_params(), $request->get_headers() );
	}

	function rest_serve_css( $request ) {
		// TODO: caching
		// TODO: check for errors

		return $this->generate_cdn_response( '/css', $request->get_params(), $request->get_headers() );
	}

	/**
	 * Fetch and cache a response from our CDN
	 */
	private function generate_cdn_response( $cdn_path, $cdn_params, $headers ) {

		$cdn_url = add_query_arg( $cdn_params, $this->cdn_server . $cdn_path );

		// disable converting local to absolute urls
		$cdn_url = add_query_arg( 'l', 1, $cdn_url );

		// attempt to fetch from cache
		// TODO link to this file directly from page using content_url() if we know it exists during page render? But what if the page output is cached?
		$cache_key = hash( "sha256", $cdn_url );
		$cache_dir = WP_CONTENT_DIR . '/jetpack-boost-cache/';
		wp_mkdir_p( $cache_dir );
		$cache_file = $cache_dir . $cache_key;

		if ( file_exists( $cache_file ) && ( $cache_file_size = filesize( $cache_file ) ) > 0 ) {
			error_log('returning cached response for '.$cdn_url);
			$cache_file_handle = fopen( $cache_file, 'r' );
			$curl_response = fread( $cache_file_handle, $cache_file_size );
			fclose( $cache_file_handle );
		} else {
			error_log('generating new response for '.$cdn_url);
			$allowed_headers = [ 'accept', 'accept_encoding', 'user_agent' ];

			$valid_headers = array_filter(
				$headers,
				function ( $key ) use ( $allowed_headers ) {
					return in_array( $key, $allowed_headers );
				},
				ARRAY_FILTER_USE_KEY
			);

			// map array-of-arrays we get from WP Rest Request
			$curl_headers = array_map( function( $header ) { return implode( ';', $header ); }, $valid_headers );

			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, $cdn_url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $curl_headers ); // forward client headers
			curl_setopt( $curl, CURLOPT_HEADER, 1 );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
			$curl_response = curl_exec( $curl ); // execute the curl command
			curl_close( $curl ); // close the connection

			// write the cache file
			$cache_file_handle = fopen( $cache_file, "w" );
			$write = fputs( $cache_file_handle, $curl_response );
			fclose( $cache_file_handle );
		}

		list( $curl_response_headers, $curl_response_body ) = explode("\r\n\r\n", $curl_response, 3);
		$curl_response_headers_array = $this->get_headers_from_curl_response( $curl_response_headers );

		return new WP_REST_Response(
			$curl_response_body, //file_get_contents( plugin_dir_path( JETPACK__PLUGIN_FILE ) . '_inc/build/universal/' . $client_slug . '.js' ),
			200,
			array(
				'Content-Type'     => $curl_response_headers_array['content_type'],
				'ETag'             => $curl_response_headers_array['etag'],
				'Expires'          => $curl_response_headers_array['expires'],
				'Cache-Control'    => $curl_response_headers_array['cache_control']
			)
		);
	}

	function get_headers_from_curl_response( $header_text ) {
		$headers = array();

		foreach( explode("\r\n", $header_text) as $i => $line ) {
			if ($i === 0) {
				$headers['http_code'] = $line;
			} else {
				list ( $key, $value ) = explode( ': ', $line );
				$key = strtolower( $key );
        		$key = str_replace( '-', '_', $key );
				$headers[$key] = $value;
			}
		}

		return $headers;
	}

	function jetpack_implode_frontend_css() {
		return false;
	}

	/**
	 * Rewrite script or style src optionally, if not being concatenated,
	 * so they're still served from the CDN
	 */

	function rewrite_script_src( $src, $handle ) {
		global $wp_scripts;

		if ( is_admin() || ! isset( $wp_scripts->registered[$handle] ) ) {
			return $src;
		}

		$script = $wp_scripts->registered[$handle];

		if ( ! $this->should_concat_script( $script ) && $this->should_cdn_script( $script ) ) {
			// serve this script from the CDN
			$url = $this->base_url . '/js';
			$url = add_query_arg( array(
				'f' => array( $src )
			), $url );
			return $url;
		}

		return $src;
	}

	function should_cdn_script( $script ) {
		return false;
		$should_cdn = ( $this->include_external_assets || $this->is_local_url( $script->src ) );
		// echo "should cdn " . print_r( $script, 1 ) . " is $should_cdn" . "\n";
		return apply_filters( 'jetpack_boost_cdn_script', $should_cdn, $script->handle, $script->src );
	}

	function rewrite_style_src( $src, $handle ) {
		global $wp_styles;

		if ( is_admin() || ! isset( $wp_styles->registered[$handle] ) ) {
			return $src;
		}

		$style = $wp_styles->registered[$handle];

		if ( ! $this->should_concat_style( $style ) && $this->should_cdn_style( $style ) ) {
			// serve this style from the CDN
			$url = $this->base_url . '/css';
			$url = add_query_arg( array(
				'f' => array( $src )
			), $url );
			return $url;
		}

		return $src;
	}

	function should_cdn_style( $style ) {
		return false;
		$should_cdn = ( $this->include_external_assets || $this->is_local_url( $style->src ) );
		return apply_filters( 'jetpack_boost_cdn_style', $should_cdn, $style->handle, $style->src );
	}

	/**
	 * Render functions
	 */

	function render_concatenated_styles_head() {
		$this->flush_concatenated_styles(0);
	}

	function render_concatenated_styles_footer() {
		$this->flush_concatenated_styles(0);
		$this->flush_concatenated_styles(1);
	}

	private function flush_concatenated_styles( $group ) {
		if ( ! isset( $this->concat_style_groups[ $group ] ) ) {
			// echo "no style group $group" . "\n";
			// echo print_r($this->concat_style_groups,1) . "\n";
			return;
		}

		$style_groups = $this->concat_style_groups[ $group ];

		if ( empty( $style_groups ) ) {
			// echo "style groups are empty" . "\n";
			return;
		}

		// Generate special URL to concatenation service
		global $wp_styles;
		$site_url = site_url();
		foreach( $style_groups as $media => $styles ) {
			$urls = array();
			$vers = array();

			foreach( $styles as $style ) {
				$urls[] = str_replace( untrailingslashit( $site_url ), '', $style->src );
				$vers[] = $style->ver ? $style->ver : $wp_styles->default_version;
			}

			$cdn_url = $this->base_url . '/css?b=' .
				urlencode( $site_url ) . '&' .
				http_build_query( array( 'f' => $urls ) ) . '&' .
				http_build_query( array( 'v' => $vers ) );

			// if we are injecting critical CSS, load the full CSS async
			if ( $this->inject_critical_css ) {
				echo '<link rel="preload" onload="this.rel=\'stylesheet\'" as="style" type="text/css" media="' . $media . '" href="' . esc_attr( $cdn_url ) . '"/>';
			} else {
				echo '<link rel="stylesheet" type="text/css" media="' . $media . '" href="' . esc_attr( $cdn_url ) . '"/>';
			}

			foreach( $styles as $style ) {
				if ( isset( $style->extra['concat-after'] ) && $style->extra['concat-after'] ) {
					printf( "<style id='%s-inline-css' type='text/css'>\n%s\n</style>\n", esc_attr( $style->handle ), implode( "\n", $style->extra['concat-after'] ) );
				}
			}
		}

		$this->concat_style_groups[ $group ] = array();
	}

	function render_concatenated_scripts_head() {
		$this->flush_concatenated_scripts( 0 );
	}

	function render_concatenated_scripts_footer() {
		$this->flush_concatenated_scripts( 0 ); // in case of late-enqueud header styles
		$this->flush_concatenated_scripts( 1 );
	}

	private function flush_concatenated_scripts( $group ) {
		if ( ! isset( $this->concat_script_groups[ $group ] ) ) {
			// echo "no script group $group" . "\n";
			// echo print_r($this->concat_script_groups,1) . "\n";
			return;
		}

		$scripts = $this->concat_script_groups[ $group ];

		if ( empty( $scripts ) ) {
			// echo "script groups are empty" . "\n";
			return;
		}

		global $wp_scripts;
		$site_url = site_url();
		$urls = array();
		$vers = array();

		foreach( $scripts as $script ) {
			$urls[] = str_replace( untrailingslashit( $site_url ), '', $script->src );
			$vers[] = $script->ver ? $script->ver : $wp_scripts->default_version;
			if ( isset( $script->extra['before'] ) && $script->extra['before'] ) {
				echo sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $script->extra['before'] );
			}
		}

		$cdn_url = $this->base_url . '/js?b=' .
			urlencode( $site_url ) . '&' .
			http_build_query( array( 'f' => $urls ) ) . '&' .
			http_build_query( array( 'v' => $vers ) );

		echo '<script type="text/javascript" src="' . esc_attr( $cdn_url ) . '"></script>';

		foreach( $scripts as $script ) {
			if ( isset( $script->extra['after'] ) && $script->extra['after'] ) {
				echo sprintf( "<script type='text/javascript'>\n%s\n</script>\n", $script->extra['after'] );
			}
		}

		$this->concat_script_groups[ $group ] = array();
	}

	/**
	 * Asset modification functions
	 */

	/**
	 * Scripts
	 */

	public function register_concat_scripts( $tag, $handle, $src ) {
		global $wp_scripts;

		// don't do admin for now
		if ( is_admin() || ! isset( $wp_scripts->registered[$handle] ) ) {
			return $tag;
		}

		$script = $wp_scripts->registered[$handle];

		if ( $this->should_concat_script( $script ) && ! $this->should_cdn_script( $script ) ) {
			// echo "buffering script ". $script->src . "\n";
			$this->buffer_script( $script );
			return '';
		}

		// echo "flushing script ". $script->src . "\n";

		// we flush buffered scripts when we encounter a tag which
		// is not eligible for concatenation, so that ordering is preserved
		$group = isset( $script->extra['group'] ) ? $script->extra['group'] : 0;
		$this->flush_concatenated_scripts( $group );

		return $tag;
	}

	private function should_concat_script( $script ) {
		$should_concat =
			( $this->include_external_assets || $this->is_local_url( $script->src ) )
			&& ! isset( $script->extra['conditional'] );
		// echo "should concatenate " . print_r( $script, 1 ) . " is $should_concat" . "\n";
		return apply_filters( 'jetpack_boost_concat_script', $should_concat, $script->handle, $script->src );
	}

	private function buffer_script( $script ) {
		$group = isset( $script->extra['group'] ) ? $script->extra['group'] : 0;
		if ( ! isset( $this->concat_script_groups[$group] ) ) {
			$this->concat_script_groups[$group] = array();
		}
		$this->concat_script_groups[$group][] = $script;
	}

	/**
	 * Styles
	 */

	public function register_concat_styles( $tag, $handle, $href, $media ) {
		global $wp_styles;

		// don't do admin for now
		if ( is_admin() || ! isset( $wp_styles->registered[$handle] ) ) {
			return $tag;
		}

		$style = $wp_styles->registered[$handle];

		if ( $this->should_concat_style( $style ) && ! $this->should_cdn_style( $style ) ) {
			$this->buffer_style( $style );
			return '';
		}

		return $tag;
	}

	private function buffer_style( $style ) {
		$group = isset( $style->extra['group'] ) ? $style->extra['group'] : 0;
		$media = $style->args;

		// rename the 'after' code so that we can output it separately
		if ( isset( $style->extra['after'] ) ) {
			$style->extra['concat-after'] = $style->extra['after'];
			unset( $style->extra['after'] );
		}

		if ( ! $media ) {
			$media = 'all';
		}

		if ( ! isset( $this->concat_style_groups[$group] ) ) {
			$this->concat_style_groups[$group] = array();
		}

		if ( ! isset( $this->concat_style_groups[$group][$media] ) ) {
			$this->concat_style_groups[$group][$media] = array();
		}

		$this->concat_style_groups[$group][$media][] = $style;
	}

	private function should_concat_style( $style ) {
		$should_concat =
		( $this->include_external_assets || $this->is_local_url( $style->src ) )
		&& ! isset( $style->extra['conditional'] );

		return apply_filters( 'jetpack_boost_concat_style', $should_concat, $style->handle, $style->src );
	}

	private function is_local_url( $url ) {
		$site_url = site_url();
		return ( strncmp( $url, '/', 1 ) === 0 && strncmp( $url, '//', 2 ) !== 0 )
			|| strpos( $url, $site_url ) === 0;
	}
}