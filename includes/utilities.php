<?php
/**
 * Utility functions for dealing with URLs.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Utilities;

use const HM\Redirects\Post_Type\SLUG as REDIRECTS_POST_TYPE;
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
		return new WP_Error( 'invalid-redirect-url', esc_html__( 'The URL does not validate', 'hm-redirects' ) );
	}

	if ( false === filter_var( prefix_path( $url ), FILTER_VALIDATE_URL ) ) {
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
 * @param string $redirect $from Leading-slashed relative URL to redirect away from.
 * @param string $to Absolute URL to redirect to.
 * @param int $status_code HTTP status code for the redirect.
 * @param int $post_id Optional. If set, update that existing redirect.
 *
 * @return int|\WP_Error The post ID if redirect added, otherwise WP_Error on failure.
 */
function insert_redirect( $from, $to, $status_code, $post_id = 0 ) {
	// Stop loops.
	remove_action( 'save_post', 'HM\Redirects\\Admin_UI\\handle_redirect_saving', 13 );

	$result = wp_insert_post(
		[
			'ID'                    => $redirect['post_id'],
			'post_content_filtered' => $redirect['status_code'],
			'post_excerpt'          => strtolower( $redirect['to'] ),
			'post_name'             => get_url_hash( $redirect['from'] ),
			'post_status'           => 'publish',
			'post_title'            => strtolower( $redirect['from'] ),
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

	$wpdb->queries = array();

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops      = array();
	$wp_object_cache->stats          = array();
	$wp_object_cache->memcache_debug = array();
	$wp_object_cache->cache          = array();

	if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset(); // important
	}
}
