<?php
/**
 * Adds a user interface for defining redirects.
 *
 * @package hm_Redirects_Plugin
 * @author Human Made Limited
 */

namespace HM\Redirects\Admin_UI;

use HM\Redirects\Post_Type as Redirects_Post_Type;
use HM\Redirects\Utilities;
use WP_Post;

/**
 * Register hooks.
 */
function setup() {
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_meta_box' );
	add_action( 'save_post', __NAMESPACE__ . '\\save_meta', 13, 3 );
	add_action( 'admin_notices', __NAMESPACE__ . '\\admin_notices' );
}


/**
 * Add the UI for the redirect URLs.
 */
function add_meta_box() {
	\add_meta_box(
		'hm-redirects-meta',
		'Redirect Settings',
		__NAMESPACE__ . '\\mb_callback',
		Redirects_Post_Type\SLUG,
		'normal',
		'high'
	);
}

/**
 * Displays the fields.
 *
 * @param WP_Post $post An instance of the post being saved.
 */
function mb_callback( WP_Post $post ) {
	$valid_status_codes = [ 301, 302, 303, 307, 403, 404 ];
	$status_code_labels = [
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		307 => 'Temporary Redirect',
		403 => 'Forbidden',
		404 => 'Not Found',
	];

	$from_url    = get_post_meta( $post->ID, '_hm_redirect_from_url', true );
	$to_url      = get_post_meta( $post->ID, '_hm_redirect_to_url', true );
	$status_code = get_post_meta( $post->ID, '_hm_redirect_rule_status_code', true );
	if ( empty( $status_code ) ) {
		$status_code = 302;
	}
	?>
	<p>
		<label for="hm_redirect_from_url">From URL</label><br>
		<input type="text" name="hm_redirect_from_url" id="hm_redirect_from_url" value="<?php echo esc_attr( $from_url ); ?>" class="regular-text code"/>
	</p>
	<p class="description">This path should be relative to the root of the site.</p>

	<p>
		<label for="hm_redirect_to_url">To URL</label><br>
		<input type="text" name="hm_redirect_to_url" id="hm_redirect_to_url" value="<?php echo esc_attr( $to_url ); ?>" class="regular-text code"/>
	</p>

	<p>
		<label for="hm_redirect_rule_status_code">HTTP Status Code:</label>
		<select name="hm_redirect_rule_status_code" id="hm_redirect_rule_status_code">
			<?php foreach ( $valid_status_codes as $code ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $status_code, $code ); ?>><?php echo esc_html( $code . ' ' . $status_code_labels[ $code ] ); ?></option>
			<?php endforeach; ?>
		</select>
		<em>If you don't know what this is, leave it as is.</em>
	</p>

	<?php

	wp_nonce_field( 'hm_redirect_meta', 'hm_redirect_meta_nonce' );
}


/**
 * Saves the redirect data to the posts meta.
 *
 * @param int     $post_id ID of the post.
 * @param WP_Post $post Instance of the post object being saved.
 * @param bool    $update Whether or not this is a new post or an update.
 */
function save_meta( $post_id, $post, $update ) {
	if ( ! user_can_save( $post_id, 'hm_redirect_meta_nonce', 'hm_redirect_meta' ) ) {
		return;
	}

	$from_url = format_path( wp_unslash( $_POST['hm_redirect_from_url'] ) );
	// $to_url can be a path or a full URL.
	$to_url      = wp_unslash( $_POST['hm_redirect_to_url'] );
	$is_full_url = filter_var( $to_url, FILTER_VALIDATE_URL );
	$is_url      = filter_var( $to_url, FILTER_VALIDATE_URL, ~FILTER_FLAG_SCHEME_REQUIRED );
	if ( ! $is_full_url && ! $is_url ) {
		$to_url = format_path( $to_url );
	}
	$error       = validate_meta( $from_url, $to_url, $update );
	$status_code = wp_unslash( $_POST['hm_redirect_rule_status_code'] );

	if ( strlen( $error ) === 0 ) {
		$from_url_hash = Utilities\get_url_hash( $from_url );
		update_post_meta( $post_id, '_hm_redirect_from_url', sanitize_text_field( $from_url ) );
		update_post_meta( $post_id, '_hm_redirect_to_url', sanitize_text_field( $to_url ) );

		update_post_meta( $post_id, '_hm_redirect_rule_status_code', sanitize_text_field( $status_code ) );

		remove_action( 'save_post', __NAMESPACE__ . '\\save_meta', 13 );
		wp_update_post(
			[
				'ID'                    => $post_id,
				'post_name'             => sanitize_text_field( $from_url_hash ),
				'post_title'            => sanitize_text_field( $from_url ),
				'post_excerpt'          => sanitize_text_field( $to_url ),
				'post_content_filtered' => sanitize_text_field( $status_code ),
			]
		);
		add_action( 'save_post', __NAMESPACE__ . '\\save_meta', 13, 2 );
	} else {
		// If we have errors, set status to draft, so the redirect is disabled.
		remove_action( 'save_post', __NAMESPACE__ . '\\save_meta', 13 );
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);
		add_action( 'save_post', __NAMESPACE__ . '\\save_meta', 13, 2 );
		set_validation_errors( $error );
	}
}

/**
 * Prefix path with a slash if needed.
 *
 * @param string $path The path to process.
 *
 * @return string
 */
function format_path( $path ) {
	if ( strpos( $path, '/' ) !== 0 ) {
		$path = '/' . $path;
	}

	return untrailingslashit( ltrim( $path ) );
}

/**
 * Run pre-save validation checks.
 *
 * @param string $from_url The URL being redirected from.
 * @param string $to_url The URL being redirected to.
 *
 * @return string
 */
function validate_meta( $from_url, $to_url ) {
	if ( is_wp_error( Utilities\normalise_url( $from_url ) ) ) {
		return 'Invalid FROM value';
	}

	if ( empty( $from_url ) || empty( $to_url ) ) {
		return 'Fields are required';
	}

	if ( false === filter_var( Utilities\prefix_path( $from_url ), FILTER_VALIDATE_URL ) ) {
		return 'Invalid URL';
	}

	// 1. a path '/my-path'
	// 2. a full URL 'https://www.example.co.uk'
	// 3. a URL with no scheme 'www.example.co.uk'
	$is_full_url = filter_var( $to_url, FILTER_VALIDATE_URL );
	$is_url      = filter_var( $to_url, FILTER_VALIDATE_URL, ~FILTER_FLAG_SCHEME_REQUIRED );
	$is_path     = filter_var( Utilities\prefix_path( $to_url ), FILTER_VALIDATE_URL );
	if ( ! $is_full_url && $is_url ) {
		return 'Please provide URL scheme (http/https)';
	}
	if ( is_wp_error( $from_url ) ) {
		return $from_url->get_error_message();
	}

	// From URL must be unique. Multiple URLs can redirect to the same target URL.
	$redirect_post = Utilities\get_redirect_post( $from_url );
	$post_ID       = (int) filter_input( INPUT_POST, 'post_ID' );
	if ( $redirect_post && $post_ID !== $redirect_post->ID ) {
		return 'A redirect rule for this URL already exists.';
	}

	return '';
}

/**
 * Save the error message.
 *
 * @param string $error Error message.
 */
function set_validation_errors( $error ) {
	add_settings_error( 'invalid-redirect-meta', 'invalid-redirect-meta', $error, 'error' );

	set_transient( 'settings_errors', get_settings_errors() );
}

/**
 * Display validation errors.
 */
function admin_notices() {
	if ( 'redirects' !== get_current_screen()->id ) {
		return;
	}

	// If there are no errors, then we'll exit the function.
	if ( ! ( $errors = get_transient( 'settings_errors' ) ) ) {
		return;
	}

	// Otherwise, build the list of errors that exist in the settings errors.
	$safe_message = '<div id="thesun-redirect-message" class="notice error"><ul>';
	foreach ( $errors as $error ) {
		$safe_message .= '<li>' . esc_html( $error['message'] ) . '</li>';
	}
	$safe_message .= '</ul></div>';

	// Write them out to the screen.
	echo $safe_message; // XSS ok.

	// Clear and the transient and unhook any other notices so we don't see duplicate messages.
	delete_transient( 'settings_errors' );
	remove_action( 'admin_notices', '_location_admin_notices' );
}

/**
 * Determines whether or not the current user has the ability to save meta data associated with this post.
 *
 * @param int    $post_id The ID of the post being saved.
 * @param string $nonce A unique string to avoid XSS.
 * @param string $action Action being checked for a nonce.
 *
 * @return bool
 */
function user_can_save( $post_id, $nonce, $action ) {
	$is_autosave    = wp_is_post_autosave( $post_id );
	$is_revision    = wp_is_post_revision( $post_id );
	$is_valid_nonce = ( isset( $_POST[ $nonce ] ) && wp_verify_nonce( $_POST[ $nonce ], $action ) );

	// Return true if the user is able to save; otherwise, false.
	return ! ( $is_autosave || $is_revision ) && $is_valid_nonce;
}
