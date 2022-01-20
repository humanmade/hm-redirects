<?php
/**
 * Utility functions for dealing with URLs.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Utilities;

use const HM\Redirects\Post_Type\SLUG as REDIRECTS_POST_TYPE;
use WP_Error;

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
 * This function sanitises, and normalises URLs:
 * - Decodes non ASCII characters, so that URLs are always decoded.
 * - Ensures that paths always have a leading slash.
 * - Passes the URL through `esc_url_raw()`.
 *
 * @param string $unsafe_url URL to process and sanitise.
 *
 * @return string Processed and sanitised URL.
 */
function sanitise_and_normalise_url( $unsafe_url ) {
	// Verify whether the URL is encoded. If so, encode it, to be consistent.
	// This regular expression was extracted from `wp_sanitize_text_field()`.
	if ( preg_match( '/%[a-fA-F0-9]{2}/i', $unsafe_url ) ) {
		$unsafe_url = urldecode( $unsafe_url );
	}

	// Test whether the URL has a scheme (like `https://`) and a domain. This is to determine whether this is an
	// absolute URL or a relative URL.
	$url_parts = wp_parse_url( $unsafe_url );

	// If we're dealing with a relative URL, make sure that there's a leading slash before it.
	if ( empty( $url_parts['scheme'] ) || empty( $url_parts['host'] ) ) {
		$unsafe_url = add_leading_slash( $unsafe_url );
	}

	// Remove any parameters and trailing slash from the url.
	$clean_url = rtrim( strtok( $unsafe_url, '?' ), '/' );

	// We now can safely escape the URL.
	$url = esc_url_raw( $clean_url );

	return $url;
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
		return new WP_Error( 'invalid-redirect-url', esc_html__( 'The URL does not validate', 'hm-redirects' ) );
	}

	// Break up the URL into it's constituent parts.
	$components = wp_parse_url( $url );

	// Avoid playing with unexpected data.
	if ( ! is_array( $components ) ) {
		return new WP_Error( 'url-parse-failed', esc_html__( 'The URL could not be parsed', 'hm-redirects' ) );
	}

	// We should have at least a path or query.
	if ( ! isset( $components['path'] ) && ! isset( $components['query'] ) ) {
		return new WP_Error( 'url-parse-failed', esc_html__( 'The URL contains neither a path nor query string', 'hm-redirects' ) );
	}

	// Make sure $components['query'] is set, to avoid errors.
	$components['query'] = ( isset( $components['query'] ) ) ? $components['query'] : '';

	// All we want is path and query strings.
	// Note this strips hashes (#) too.
	$normalised_url = $components['path'];

	// Remove any trailing slashes.
	$normalised_url = untrailingslashit( $normalised_url );

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

/**
 * Add a redirect.
 *
 * @param string $from        Leading-slashed relative URL to redirect away from.
 * @param string $to          Absolute URL to redirect to.
 * @param int    $status_code HTTP status code for the redirect.
 * @param int    $post_id     Optional. If set, update that existing redirect.
 *
 * @return int|\WP_Error The post ID if redirect added, otherwise WP_Error on failure.
 */
function insert_redirect( $from, $to, $status_code, $post_id = 0 ) {
	// Stop loops.
	remove_action( 'save_post', 'HM\Redirects\\Admin_UI\\handle_redirect_saving', 13 );

	/**
	 * Filter the from URL value before saving to the database.
	 *
	 * @param string $from The URL to redirect from.
	 * @param string $to The URL to redirect to.
	 * @param int $status_code The status code for the redirect.
	 * @param int $post_id The post ID for the redirect.
	 */
	$from = apply_filters( 'hm_redirects_pre_save_from_url', $from, $to, $status_code, $post_id );

	/**
	 * Filter the to URL value before saving to the database.
	 *
	 * @param string $to The URL to redirect to.
	 * @param string $from The URL to redirect to.
	 * @param int $status_code The status code for the redirect.
	 * @param int $post_id The post ID for the redirect.
	 */
	$to = apply_filters( 'hm_redirects_pre_save_to_url', $to, $from, $status_code, $post_id );

	$result = wp_insert_post(
		[
			'ID'                    => $post_id,
			'post_content_filtered' => $status_code,
			'post_excerpt'          => $to,
			'post_name'             => get_url_hash( $from ),
			'post_status'           => 'publish',
			'post_title'            => strtolower( $from ),
			'post_type'             => REDIRECTS_POST_TYPE,
		],
		true
	);

	add_action( 'save_post', 'HM\\Redirects\\Admin_UI\\handle_redirect_saving', 13 );

	return $result;
}

/**
 * Clear all caches for memory management.
 */
function clear_object_cache() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = [];

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops      = [];
	$wp_object_cache->stats          = [];
	$wp_object_cache->memcache_debug = [];
	$wp_object_cache->cache          = [];

	if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset();
	}
}

/**
 * Add a leading slash to a string.
 *
 * This function ensures that only a single leading slash will be present.
 *
 * @param string $string String to add leading slash to.
 *
 * @return string String with a single leading slash.
 */
function add_leading_slash( $string ) {
	$string = ltrim( $string, '\/' );

	return '/' . $string;
}
