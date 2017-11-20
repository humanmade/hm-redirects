<?php
/**
 * Utility functions for dealing with URLs.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Utilities;

use HM\Redirects\Post_Type as Redirects_Post_Type;
use WP_Error;
use WP_Query;

/**
 * Creates an md5 hash from a URL.
 *
 * @param string $url The URL to hash.
 *
 * @return string
 */
function get_url_hash( $url ) {
	return md5( strtolower( $url ) );
}

/**
 * Takes a request URL and "normalises" it, stripping common elements.
 *
 * Removes scheme and host from the URL, as redirects should be independent of these.
 *
 * @param string $url URL to transform.
 *
 * @return string $url Transformed URL
 */
function normalise_url( $url ) {
	// Sanitise the URL first rather than trying to normalise a non-URL.
	if ( empty( esc_url_raw( $url ) ) ) {
		return new WP_Error( 'invalid-redirect-url', 'The URL does not validate' );
	}

	if ( false === filter_var( prefix_path( $url ), FILTER_VALIDATE_URL ) ) {
		return new WP_Error( 'invalid-redirect-url', 'The URL does not validate' );
	}

	// Break up the URL into it's constituent parts.
	$components = wp_parse_url( $url );

	// Avoid playing with unexpected data.
	if ( ! is_array( $components ) ) {
		return new WP_Error( 'url-parse-failed', 'The URL could not be parsed' );
	}

	// We should have at least a path or query.
	if ( ! isset( $components['path'] ) && ! isset( $components['query'] ) ) {
		return new WP_Error( 'url-parse-failed', 'The URL contains neither a path nor query string' );
	}

	// Make sure $components['query'] is set, to avoid errors.
	$components['query'] = ( isset( $components['query'] ) ) ? $components['query'] : '';

	// All we want is path and query strings.
	// Note this strips hashes (#) too.
	$normalised_url = $components['path'];

	// Only append '?' and the query if there is one.
	if ( ! empty( $components['query'] ) ) {
		$normalised_url = $components['path'] . '?' . $components['query'];
	}

	return $normalised_url;
}

/**
 * Build a full URL from a path.
 *
 * @param string $url URL to use.
 * @return string Full URL.
 */
function prefix_path( $url ) {
	$scheme = wp_parse_url( $url, PHP_URL_SCHEME );

	// If it's just a path, prepend the base URL to validate.
	if ( is_null( $scheme ) ) {
		return home_url( $url );
	}

	return $url;
}
