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
use WP_Query;

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

	$url = untrailingslashit( str_replace( wp_parse_url( home_url(), PHP_URL_PATH ), '', sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$url .= '?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );
	}

	$request_path = apply_filters( 'hm_redirects_request_path', $url );

	if ( ! $url ) {
		return false;
	}

	$redirect_uri = get_redirect_uri( $request_path );

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
 * @return bool|false|string
 */
function get_redirect_uri( $url ) {
	$url = Utilities\normalise_url( $url );
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
	$url_hash = Utilities\get_url_hash( $url );

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
