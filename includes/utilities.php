<?php

/**
 * Utility functions for common functionality.
 */

namespace HM\Redirects\Utilities;

use HM\Redirects\Post_Type as Redirects_Post_Type;
use WP_Error;
use WP_Query;

/**
 * Retrieves the redirect URL.
 *
 * @param string $url The URL to redirect to.
 *
 * @return bool|false|string
 */
function get_redirect_uri( $url ) {
	$url = normalise_url( $url );
	if ( is_wp_error( $url ) ) {
		return false;
	}

	$redirect_post = get_redirect_post( $url );

	if ( is_null( $redirect_post ) ) {
		return false;
	}

	$to_url = $redirect_post->post_excerpt;
	if ( empty( $to_url ) ) {
		return false;
	}

	if ( false === filter_var( $to_url, FILTER_VALIDATE_URL ) ) {
		$to_url = home_url() . $redirect_post->post_excerpt;
	}

	return wp_sanitize_redirect( $to_url );
}


/**
 * Retrieves the redirect status code.
 *
 * @param string $url The URL being redirected.
 *
 * @return int|string|WP_Error
 */
function get_redirect_status_code( $url = '' ) {
	if ( ! is_string( $url ) || strlen( $url ) === 0 ) {
		return new WP_Error( '', 'URL needs to be a non empty string' );
	}

	$url = normalise_url( $url );
	if ( is_wp_error( $url ) ) {
		return 302;
	}

	$redirect_post = get_redirect_post( $url );

	if ( ! is_null( $redirect_post ) ) {
		$status_code = $redirect_post->post_content_filtered;
		if ( ! empty( $status_code ) ) {
			return $status_code;
		}
	}

	return 302;
}

/**
 * Retrieve the target URL's post ID.
 *
 * @param string $url The URL to retrieve the post for.
 *
 * @return int|null|string
 */
function get_redirect_post( $url ) {
	$url_hash = get_url_hash( $url );

	$query = new WP_Query( [
		'posts_per_page' => 1,
		'post_type'      => Redirects_Post_Type\SLUG,
		'name'           => $url_hash,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
	] );

	$redirect_post = $query->have_posts() ? current( $query->get_posts() ) : null;

	return $redirect_post;
}

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
	$scheme = parse_url( $url, PHP_URL_SCHEME );

	// If it's just a path, prepend the base URL to validate.
	if( is_null( $scheme ) ) {
		return home_url( $url );
	}

	return $url;
}
