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
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
	add_action( 'manage_' . Redirects_Post_Type\SLUG . '_posts_custom_column', __NAMESPACE__ . '\\posts_columns_content', 10, 2 );
	add_filter( 'manage_' . Redirects_Post_Type\SLUG . '_posts_columns', __NAMESPACE__ . '\\filter_posts_columns' );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_plugin_textdomain' );
	add_action( 'save_post', __NAMESPACE__ . '\\handle_redirect_saving', 13 );
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
 * Enqueue scripts & styles.
 */
function enqueue_scripts() {
	if ( get_current_screen()->post_type !== Redirects_Post_Type\SLUG ) {
		return;
	}

	wp_enqueue_style( 'hm-redirects-admin', plugins_url( '/assets/admin.css', dirname( __FILE__ ) ), [], null, 'screen' );
}

/**
 * Filter the posts listing columns.
 *
 * @param array $columns List of column ids and labels.
 * @return array
 */
function filter_posts_columns( array $columns ) : array {
	$columns = [
		'title' => __( 'From', 'hm-redirects' ),
		'to' => __( 'To', 'hm-redirects' ),
		'status' => __( 'Status', 'hm-redirects' ),
		'date' => __( 'Date' ),
	];
	return $columns;
}

/**
 * Output for the custom admin columns.
 *
 * @param string $column Current column ID.
 * @param int    $post_id Current post ID.
 */
function posts_columns_content( string $column, int $post_id ) {
	$post = get_post( $post_id );

	if ( $column === 'to' ) {
		echo esc_html( sanitize_text_field( $post->post_excerpt ) );
	}

	if ( $column === 'status' ) {
		echo intval( $post->post_content_filtered );
	}
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

	/**
	 * Filter available status codes.
	 *
	 * The array keys are the status code and the values are the labels.
	 *
	 * Use this filter to modify the labels or remove options.
	 *
	 * @param array $status_code_labels Array of status codes and labels.
	 */
	$status_code_labels = apply_filters( 'hm_redirects_status_codes', $status_code_labels );

	/**
	 * Filter the default selected status code.
	 *
	 * Defaults to 302.
	 *
	 * @param int $default_status_code
	 */
	$default_status_code = apply_filters( 'hm_redirects_default_status_code', 302 );

	if ( ! in_array( $default_status_code, $valid_status_codes, true ) ) {
		$default_status_code = 302;
	}

	$status_code = ! empty( $post->post_content_filtered ) ? $post->post_content_filtered : $default_status_code;
	?>
	<p>
		<label for="hm_redirects_from_url"><?php esc_html_e( 'From URL', 'hm-redirects' ); ?></label><br>
		<input type="text" name="hm_redirects_from_url" id="hm_redirects_from_url" value="<?php echo esc_attr( $post->post_title ); ?>" class="code widefat"/>
	</p>
	<p class="description"><?php esc_html_e( 'This path should be relative to the root of the site.', 'hm-redirects' ); ?></p>

	<p>
		<label for="hm_redirects_to_url"><?php esc_html_e( 'To URL', 'hm-redirects' ); ?></label><br>
		<input type="text" name="hm_redirects_to_url" id="hm_redirects_to_url" value="<?php echo esc_attr( $post->post_excerpt ); ?>" class="code widefat"/>
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

	if ( ! isset( $_POST['hm_redirects_from_url'] ) || ! isset( $_POST['hm_redirects_to_url'] ) || ! isset( $_POST['hm_redirects_status_code'] ) ) {
		return false;
	}

	// phpcs:disable WordPress.VIP.ValidatedSanitizedInput
	// We're using a custom sanitisation function.
	$data = sanitise_and_normalise_redirect_data(
		wp_unslash( $_POST['hm_redirects_from_url'] ),
		wp_unslash( $_POST['hm_redirects_to_url'] ),
		wp_unslash( $_POST['hm_redirects_status_code'] )
	);
	// phpcs:enable

	$redirect_id = Utilities\insert_redirect( $data['from_url'], $data['to_url'], $data['status_code'], $post_id );

	return $redirect_id === $post_id;
}

/**
 * Sanitise and normalise data for a redirect post.
 *
 * @param string $unsafe_from        From URL.
 * @param string $unsafe_to          To URL.
 * @param string $unsafe_status_code HTTP status code.
 *
 * @return array
 */
function sanitise_and_normalise_redirect_data( $unsafe_from, $unsafe_to, $unsafe_status_code ) {
	return [
		'from_url'    => Utilities\normalise_url( Utilities\sanitise_and_normalise_url( $unsafe_from ) ),
		'to_url'      => Utilities\sanitise_and_normalise_url( $unsafe_to ),
		'status_code' => absint( $unsafe_status_code ),
	];
}

/**
 * Load the plugin translations.
 */
function load_plugin_textdomain() {
	\load_plugin_textdomain( 'hm-redirects', false, basename( dirname( dirname( __FILE__ ) ) ) . '/languages/' );
}
