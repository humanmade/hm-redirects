<?php
/**
 * Adds a user interface for defining redirects.
 *
 * @package hm-redirects
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
	add_action( 'save_post', __NAMESPACE__ . '\\handle_redirect_saving', 13 );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_plugin_textdomain' );
}

/**
 * Add the metabox for the redirects.
 */
function add_meta_box() {
	\add_meta_box(
		'hm-redirects-meta',
		esc_html__( 'Redirect Settings', 'hm-redirects' ),
		__NAMESPACE__ . '\\output_meta_box',
		Redirects_Post_Type\SLUG,
		'normal',
		'high'
	);
}

/**
 * Output the redirects metabox,
 *
 * @param WP_Post $post The currently edited post.
 */
function output_meta_box( WP_Post $post ) {
	$valid_status_codes = [ 301, 302, 303, 307, 403, 404 ];
	$status_code_labels = [
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		307 => 'Temporary Redirect',
		403 => 'Forbidden',
		404 => 'Not Found',
	];

	$status_code = ! empty( $post->post_content_filtered ) ? $post->post_content_filtered : 302;
	?>
	<p>
		<label for="hm_redirects_from_url"><?php esc_html_e( 'From URL', 'hm-redirects' ); ?></label><br>
		<input type="text" class="widefat" name="hm_redirects_from_url" id="hm_redirects_from_url" value="<?php echo esc_attr( $post->post_title ); ?>" class="regular-text code"/>
	</p>
	<p class="description"><?php esc_html_e( 'This path should be relative to the root of the site.', 'hm-redirects' ); ?></p>

	<p>
		<label for="hm_redirects_to_url"><?php esc_html_e( 'To URL', 'hm-redirects' ); ?></label><br>
		<input type="text" class="widefat" name="hm_redirects_to_url" id="hm_redirects_to_url" value="<?php echo esc_attr( $post->post_excerpt ); ?>" class="regular-text code"/>
	</p>

	<p>
		<label for="hm_redirects_status_code"><?php esc_html_e( 'HTTP Status Code:', 'hm-redirects' ); ?></label>
		<select name="hm_redirects_status_code" id="hm_redirects_status_code">
			<?php foreach ( $valid_status_codes as $code ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $status_code, $code ); ?>><?php echo esc_html( $code . ' ' . $status_code_labels[ $code ] ); ?></option>
			<?php endforeach; ?>
		</select>
		<em><?php esc_html_e( "If you don't know what this is, leave it as is.", 'hm-redirects' ); ?></em>
	</p>
	<?php
	wp_nonce_field( 'hm_redirects', 'hm_redirects_nonce' );
}

/**
 * Save the redirect information.
 *
 * @param int $post_id Saved post id.
 *
 * @return bool Whether the redirect was saved successfully.
 */
function handle_redirect_saving( $post_id ) {
	if ( ! isset( $_POST['hm_redirects_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hm_redirects_nonce'] ) ), 'hm_redirects' ) ) {
		return false;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return false;
	}

	$from        = isset( $_POST['hm_redirects_from_url'] ) ? sanitize_text_field( wp_unslash( $_POST['hm_redirects_from_url'] ) ) : '';
	$to          = isset( $_POST['hm_redirects_to_url'] ) ? esc_url_raw( wp_unslash( $_POST['hm_redirects_to_url'] ) ) : '';
	$status_code = isset( $_POST['hm_redirects_status_code'] ) ? (int) $_POST['hm_redirects_status_code'] : 302;

	$redirect_id = Utilities\insert_redirect( $from, $to, $status_code, $post_id );

	return $redirect_id === $post_id;
}

/**
 * Load the plugin translations.
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'hm-redirects', false, basename( dirname( dirname( __FILE__ ) ) ) . '/languages/' );
}
