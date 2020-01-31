<?php

/**
  * A simple cache for fetching and returning remotely-generated files.
  * It also allows for directly serving files from the cache directory (I hope).
  * It attempts to pass request headers (e.g. Accepts) through to the remote service, and pass some important
  * response headers (e.g. Expires) to the client.
  *
  * It is explicitly designed to work with "Photon Pro"
  *
  * TODO:
  * - expire files older than TTL
  * - add methods allowing to fetch URL directly from wp-content/... if file exists (let the web server handle it)
  */

define( 'JETPACK_BOOST_CACHE_DIR', 'jetpack-boost-cache' ); // in wp-content... for now

class Jetpack_Boost_Filecache {
	static function fetch_and_cache( $url, $method = 'GET', $file_extension = '', $data = null, $request_headers = [], $ttl = null ) {
		// attempt to fetch from cache
		// TODO link to this file directly from page using content_url() if we know it exists during page render? But what if the page output is cached?

		list( $response_headers, $response_body ) = self::get_cached_response( $url, $method, $file_extension, $data );
		if ( $response_body ) {
			$response_headers[ 'x_jetpack_boost_cached' ] = '1';
			return [ $response_headers, $response_body ];
		}

		list( $response_headers, $response_body, $return_code ) = self::get_server_response( $url, $method, $file_extension, $data, $request_headers );

		if ( $response_body ) {
			$response_headers[ 'x_jetpack_boost_cached' ] = '0';
			return [ $response_headers, $response_body ];
		}

		return new WP_Error( 'server_error', sprintf( 'Jetpack Boost server failure: %s', $return_code ) );
	}

	static function get_cached_response( $url, $method, $file_extension, $data = null ) {
		$cache_file = self::get_cache_file_name( $url, $method, $file_extension, $data );

		if ( file_exists( $cache_file ) && ( $cache_file_size = filesize( $cache_file ) ) > 0 ) {
			// get the file from disk
			// TODO: handle filesystem and memory errors
			$cache_file_handle = fopen( $cache_file, 'r' );
			$response = fread( $cache_file_handle, $cache_file_size );
			fclose( $cache_file_handle );

			// parse the header out
			list( $response_headers, $response_body ) = explode("\r\n\r\n", $response, 3);
			$response_headers_array = self::get_headers_from_curl_response( $response_headers );
			return [ $response_headers_array, $response_body ];
		}

		return null;
	}

	static function get_cached_resource_url( $url, $file_extension ) {
		error_log("looking for cached response for $url");
		$cache_file = self::get_cache_file_name( $url, 'GET', $file_extension, null );
		error_log("checking cache file $cache_file");

		if ( file_exists( $cache_file ) && ( filesize( $cache_file ) ) > 0 ) {
			error_log("cache file exists!");
			return content_url( JETPACK_BOOST_CACHE_DIR . '/' . basename( $cache_file ) );
		}
		error_log("cache file does not exist");

		return null;
	}

	static function write_response_to_cache( $url, $method, $file_extension, $data, $curl_response ) {
		// get the filename
		$cache_file = self::get_cache_file_name( $url, $method, $data, $file_extension );
		wp_mkdir_p( dirname( $cache_file ) ); // TODO: handle filesystem errors gracefully
		// write the cache file
		error_log("writing cache file $cache_file");
		$cache_file_handle = fopen( $cache_file, "w" );
		$write = fputs( $cache_file_handle, $curl_response );
		fclose( $cache_file_handle );
	}

	static function get_cache_file_name( $url, $method, $file_extension, $data ) {

		// normalize url
		$url_parsed = parse_url( $url );
		if ( isset( $url_parsed['query'] ) ) {
			$q = explode( '&', $url_parsed['query'] );
			sort($q);
			$normalized_query = implode( ':', $q );
		} else {
			$normalized_query = '';
		}



		error_log("getting cached file name for $url with method $method and file_extension $file_extension");
		$body = !is_null( $data ) ? json_encode( $data ) : '';
		$cache_key_input = implode( ':', [ $method, $url_parsed['host'], $url_parsed['path'], $normalized_query, $body ] );
		error_log("hashing $cache_key_input");
		$cache_key = hash( "sha256", $cache_key_input );
		$cache_dir = WP_CONTENT_DIR . '/' . JETPACK_BOOST_CACHE_DIR . '/'; // TODO: constantize
		return $cache_dir . $cache_key . '.' . $file_extension;
	}

	// TODO: support $method and $data
	static function get_server_response( $url, $method, $file_extension, $data, $request_headers ) {
		// filter outgoing headers and make them match the "normalized" versions we get from WP_Rest_Request
		$allowed_headers = [ 'accept', 'accept_encoding', 'user_agent' ];
		$valid_headers = array_filter(
			$request_headers,
			function ( $key ) use ( $allowed_headers ) {
				return in_array( $key, $allowed_headers );
			},
			ARRAY_FILTER_USE_KEY
		);

		// map array-of-arrays we get from WP Rest Request to a simple array
		$curl_headers = array_map( function( $header ) { return implode( ';', $header ); }, $valid_headers );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $curl_headers ); // forward client headers
		curl_setopt( $curl, CURLOPT_HEADER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );

		// if curl POST...
		if ( 'POST' === $method ) {
			$data_string = json_encode( $data );
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_string))
			);
		}

		$curl_response = curl_exec( $curl ); // execute the curl command
		$curl_httpcode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl ); // close the connection

		if ( $curl_httpcode !== 200 ) {
			// return a failure so we can use the original CDN URL instead
			return [ null, null, $curl_httpcode];
		}

		self::write_response_to_cache( $url, $method, $data, $file_extension, $curl_response );

		list( $curl_response_headers, $curl_response_body ) = explode("\r\n\r\n", $curl_response, 3);
		$curl_response_headers_array = self::get_headers_from_curl_response( $curl_response_headers );

		// filter headers returned from CDN server
		$allowed_cdn_headers = [ 'Content-Type', 'ETag', 'Expires', 'Cache-Control' ];
		$curl_response_headers_array = array_filter(
			$curl_response_headers_array,
			function ( $key ) use ( $allowed_cdn_headers ) {
				return in_array( $key, $allowed_cdn_headers );
			},
			ARRAY_FILTER_USE_KEY
		);

		return [ $curl_response_headers_array, $curl_response_body, $curl_httpcode ];
	}

	static function get_headers_from_curl_response( $header_text ) {
		$headers = array();

		foreach( explode("\r\n", $header_text) as $i => $line ) {
			if ($i === 0) {
				$headers['http_code'] = $line;
			} else {
				list ( $key, $value ) = explode( ': ', $line );
				// $key = strtolower( $key );
        		// $key = str_replace( '-', '_', $key );
				$headers[$key] = $value;
			}
		}

		return $headers;
	}
}
