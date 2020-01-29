<?php

/**
 * Slow but somewhat effective HTML compression.
 * TODO: only use if caching is detected - this probably doesn't make sense without it
 * WARNING: must be initialized before template_redirect, which means this may be loaded (but does nothing) on admin pages too
 */
class Jetpack_Boost_Minify_HTML {
	private static $__instance = null;

	/**
	 * Singleton implementation
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! is_a( self::$__instance, 'Jetpack_Boost_Minify_HTML' ) ) {
			self::$__instance = new Jetpack_Boost_Minify_HTML();
		}

		return self::$__instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'begin_buffer_html' ) );
	}

	function begin_buffer_html() {
		ob_start( array( $this, 'end_buffer_html' ) );
	}

	function end_buffer_html( $content ) {
		return Minify_HTML::minify( $content );
	}
}