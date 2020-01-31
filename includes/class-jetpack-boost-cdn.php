<?php

/**
 * TODO
 * - asset inlining for smaller styles?
 * - critical CSS support?
 * - non-enqueued assets?
 * - how to authorize to the CDN
 */

require_once 'class-jetpack-boost-filecache.php';

define( 'JETPACK_BOOST_LOCAL_ENDPOINT_BASE', '/wp-json/jpb/v1' );

class Jetpack_Boost_CDN_Url_Builder {
	static function get_cdn_url() {
		return apply_filters( 'jetpack_boost_cdn_url', 'http://localhost:8090' );
	}

	static function get_js_url( $assets, $versions = null, $base_url = null ) {
		return self::get_assets_url( 'js', $assets, $versions, $base_url );
	}

	static function get_css_url( $assets, $versions = null, $base_url = null ) {
		return self::get_assets_url( 'css', $assets, $versions, $base_url );
	}

	/**
	 * Generate a URL for a set of assets with optional versions and base_url
	 *
	 * - Try generating URL to CDN (e.g. wpvm.io/js?b=http://mysite.com&f[]=/js/foo.js)
	 * - If serving from CDN directly, return that URL
	 * - If serving locally, checked for cached asset
	 * - If no cached asset, link to local URL that fetches the asset server-to-server and then caches locally, and return that
	 */
	private static function get_assets_url( $file_extension, $assets, $versions, $base_url ) {
		$cdn_url = self::get_cdn_url();

		// generate the canonical URL - this is used as a cache key
		$assets_url   = $cdn_url . '/' . $file_extension;

		$assets_url   = add_query_arg( 'f', $assets, $assets_url );

		if ( ! empty( $versions ) ) {
			$assets_url   = add_query_arg( 'v', $versions, $assets_url );
		}

		if ( ! is_null( $base_url ) ) {
			$assets_url   = add_query_arg( 'b', $base_url, $assets_url );
		}

		// check if we want to simply link to the external asset
		if ( ! apply_filters( 'jetpack_boost_serve_from_origin', false ) ) {
			return $assets_url;
		}

		// if we have a cached asset in wp-content, link to it directly
		$cached_assets_url = Jetpack_Boost_Filecache::get_cached_resource_url( $assets_url, $file_extension );

		if ( $cached_assets_url ) {
			error_log("returning cached assets URL $cached_assets_url");
			return $cached_assets_url;
		}

		// serve from the local endpoint, which will cache the response on the server while fetching
		// then future requests will link directly to the cached file for performance reasons
		$local_assets_url = str_replace( $cdn_url, JETPACK_BOOST_LOCAL_ENDPOINT_BASE, $assets_url );

		error_log("returning local assets URL $local_assets_url");

		return $local_assets_url;
	}

	static function check_for_cached_cdn_file( $url, $file_extension ) {
		error_log("checking for cached URL for $url");
		$cached_asset_url = Jetpack_Boost_Filecache::get_cached_resource_url( $url, $file_extension );
		error_log("got asset url $cached_asset_url");

		if ( $cached_asset_url ) {
			return $cached_asset_url;
		}

		return $url;
	}
}

class Jetpack_Boost_CDN {
	private static $__instance = null;

	public $cdn_server;
	private $base_url;
	private $serve_from_origin; // proxy files via the origin and cache locally - helps a lot with http2
	private $concat_style_groups = array();
	private $concat_script_groups = array();
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

	// prevent javascript and CSS strings from being JSON-encoded
	function pre_serve_request( $served, $result, $request, $server ) {
		if ( ! $served && preg_match( '|/jpb/v1/.*|', $request->get_route() ) ) {
			$data = $result->get_data();
			if ( is_string( $data ) ) {
				echo $data;
			} else {
				echo '';
			}
			return true;
		}
		return $served;
	}

	function rest_serve_js( $request ) {
		return $this->generate_cdn_response( 'js', $request->get_params(), $request->get_headers() );
	}

	function rest_serve_css( $request ) {
		return $this->generate_cdn_response( 'css', $request->get_params(), $request->get_headers() );
	}

	/**
	 * Fetch and cache a response from our CDN
	 */
	private function generate_cdn_response( $file_type, $cdn_params, $request_headers ) {
		$cdn_host = Jetpack_Boost_CDN_Url_Builder::get_cdn_url();
		$cdn_url = add_query_arg( $_GET, $cdn_host . '/' . $file_type );
		$cdn_response = Jetpack_Boost_Filecache::fetch_and_cache( $cdn_url, 'GET', $file_type, null, $request_headers );

		// if there's an error, return an empty response with appropriate info in the header
		if ( is_wp_error( $cdn_response ) ) {
			$response_headers = [
				'X-Jetpack-Boost-Error-Code' => $cdn_response->get_error_code(),
				'X-Jetpack-Boost-Error-Message' => $cdn_response->get_error_message(),
				// simulate correct content type otherwise Chrome outputs spurious console messages
				'Content-Type' => ( 'css' === $file_type ) ? 'text/css' : 'application/javascript'
			];
			return new WP_REST_Response(
				'',
				500,
				$response_headers
			);
		}

		list( $response_headers, $response_body ) = $cdn_response;

		return new WP_REST_Response(
			$response_body,
			200,
			$response_headers
		);
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
			return Jetpack_Boost_CDN_Url_Builder::get_js_url( [ $src ] );
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
			return Jetpack_Boost_CDN_Url_Builder::get_css_url( [ $src ] );
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

			$cdn_url = Jetpack_Boost_CDN_Url_Builder::get_css_url( $urls, $vers, $site_url );

			// if we are injecting critical CSS, load the full CSS async
			// TODO: what happens here if javascript is disabled? Should we have a noscript version?
			if ( apply_filters( 'jetpack_boost_inject_critical_css', false ) ) {
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

		$cdn_url = apply_filters( 'jetpack_boost_cdn_asset_url', $cdn_url, 'js' );

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