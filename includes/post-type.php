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
		'name'               => 'Redirects',
		'singular_name'      => 'Redirect',
		'add_new'            => 'Add New',
		'add_new_item'       => 'Add New Redirect',
		'edit_item'          => 'Edit Redirect',
		'new_item'           => 'New Redirect',
		'view_item'          => 'View Redirect',
		'search_items'       => 'Search Redirects',
		'not_found'          => 'No redirects found',
		'not_found_in_trash' => 'No redirects found in trash',
		'all_items'          => 'All Redirects',
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
