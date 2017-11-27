<?php
/**
 * Register the redirects custom post type.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Post_Type;

/**
 * Redirects post type slug.
 */
const SLUG = 'hm_redirect';

/**
 * Setup hooks.
 */
function setup() {
	add_action( 'init', __NAMESPACE__ . '\\register_post_type' );
}

/**
 * Register the post type.
 */
function register_post_type() {
	$labels = [
		'name'               => esc_html__( 'Redirects', 'hm-redirects' ),
		'singular_name'      => esc_html__( 'Redirect', 'hm-redirects' ),
		'add_new'            => esc_html__( 'Add New', 'hm-redirects' ),
		'add_new_item'       => esc_html__( 'Add New Redirect', 'hm-redirects' ),
		'edit_item'          => esc_html__( 'Edit Redirect', 'hm-redirects' ),
		'new_item'           => esc_html__( 'New Redirect', 'hm-redirects' ),
		'view_item'          => esc_html__( 'View Redirect', 'hm-redirects' ),
		'search_items'       => esc_html__( 'Search Redirects', 'hm-redirects' ),
		'not_found'          => esc_html__( 'No redirects found', 'hm-redirects' ),
		'not_found_in_trash' => esc_html__( 'No redirects found in trash', 'hm-redirects' ),
		'all_items'          => esc_html__( 'All Redirects', 'hm-redirects' ),
	];

	\register_post_type(
		SLUG,
		[
			'labels'              => $labels,
			'show_in_feed'        => false,
			'supports'            => [ 'title' ],
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-migrate',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
		]
	);
}
