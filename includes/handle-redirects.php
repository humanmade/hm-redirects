<?php

namespace HM\Redirects\Handle_Redirects;

use HM\Redirects\Utilities;

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
	global $whitelist_hosts;

	if ( is_admin() ) {
		return;
	}

	$url = untrailingslashit( str_replace( parse_url( home_url(), PHP_URL_PATH ), '', $_SERVER['REQUEST_URI'] ) );

	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$url .= '?' . $_SERVER['QUERY_STRING'];
	}

	$request_path = apply_filters( 'hm_redirects_request_path', $url );

	if ( $request_path ) {
		$redirect_uri = Utilities\get_redirect_uri( $request_path );
		if ( ! $redirect_uri ) {
			return;
		}

		$parsed_redirect = parse_url( $redirect_uri );
		if ( is_array( $parsed_redirect ) && ! empty( $parsed_redirect['host'] ) ) {
			$whitelist_hosts = $parsed_redirect['host'];
			add_filter( 'allowed_redirect_hosts', __NAMESPACE__ . '\\filter_allowed_redirect_hosts' );
		}
		if ( wp_validate_redirect( $redirect_uri ) ) {
			header( 'X-HM-Redirects: true' );
			wp_safe_redirect( $redirect_uri, Utilities\get_redirect_status_code( $request_path ) );
			exit;
		}
	}
}


/**
 * Apply whitelisted hosts to allowed_redirect_hosts filter
 *
 * @param array $content
 *
 * @return array
 */
function filter_allowed_redirect_hosts( $content ) {
	global $whitelist_hosts;

	foreach ( $whitelist_hosts as $host ) {
		$without_www = preg_replace( '/^www\./i', '', $host );
		$with_www    = 'www.' . $without_www;

		if ( ! in_array( $without_www, $content, true ) ) {
			$content[] = $without_www;
		}

		if ( ! in_array( $with_www, $content, true ) ) {
			$content[] = $with_www;
		}
	}

	return $content;
}

