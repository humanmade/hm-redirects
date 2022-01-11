<?php
/**
 * Handle redirecting URLs if a redirect has been set.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Handle_Redirects;

use HM\Redirects\Post_Type as Redirects_Post_Type;
use HM\Redirects\Utilities;
use WP_Error;

/**
 * Initialise the plugin.
 */
function setup() {
	add_action( 'parse_request', __NAMESPACE__ . '\\maybe_do_redirect', 0 );
}

/**
 * Determine if a redirect needs to be performed for this URL, and do it if so.
 */
function maybe_do_redirect() {
	if ( is_admin() ) {
		return false;
	}

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$path = untrailingslashit( str_replace( wp_parse_url( home_url(), PHP_URL_PATH ), '', wp_unslash( $_SERVER['REQUEST_URI'] ) ) );

	/**
	 * Filter the request path before searching for a matching redirect.
	 *
	 * @param string $path
	 */
	$request_path = apply_filters( 'hm_redirects_request_path', $path );
	if ( ! $request_path ) {
		return false;
	}

	// Try to find a matching redirect.
	$redirect_uri = get_redirect_uri( $request_path );
	if ( ! $redirect_uri ) {
		return false;
	}

	/**
	 * Fires when the request path matches a redirect.
	 *
	 * @param string $redirect_uri Redirect-sanitised URL.
	 * @param string $request_path The request path that matched the redirect.
	 */
	do_action( 'hm_redirects_matched_redirect', $redirect_uri, $request_path );

	if ( ! wp_validate_redirect( $redirect_uri ) ) {
		return false;
	}

	header( 'X-HM-Redirects: true' );
	wp_safe_redirect( $redirect_uri, get_redirect_status_code( $request_path ) );
	exit;
}

/**
 * Retrieves the redirect URL.
 *
 * @param string $url The URL to redirect to.
 *
 * @return false|string Redirect-sanitised URL, or false if no valid redirect matched.
 */
function get_redirect_uri( $url ) {
	$url = Utilities\sanitise_and_normalise_url( $url );

	$redirect_post = get_redirect_post( $url );
	if ( is_null( $redirect_post ) ) {
		return false;
	}

	$to_url = $redirect_post->post_excerpt;
	if ( empty( $to_url ) ) {
		return false;
	}

	// If the URL is only a path, prefix it with the `home_url()`.
	$to_url = Utilities\prefix_path( $to_url );

	// If there were any paramters in the original URL, append them to the new URL.
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$to_url .= '/?'.$_SERVER['QUERY_STRING'];
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

	$url = Utilities\normalise_url( $url );
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
	$url_hash      = Utilities\get_url_hash( $url );
	$redirect_post = get_page_by_path( $url_hash, OBJECT, [ Redirects_Post_Type\SLUG ] );
	if ( 'publish' !== get_post_status( $redirect_post ) ) {
		return null;
	}

	return $redirect_post;
}
