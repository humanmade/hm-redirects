<?php
/**
 * Test for the Utility functions.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Tests;

use const HM\Redirects\Post_Type\SLUG as REDIRECTS_POST_TYPE;
use HM\Redirects\Admin_UI;
use WP_UnitTestCase;

/**
 * Class Admin_Test
 *
 * @package HM\Redirects\Tests
 */
class Admin_Test extends WP_UnitTestCase {
	/**
	 * Tests handle_redirect_saving.
	 */
	public function test_handle_redirect_saving() {
		$redirect_post_id = self::factory()->post->create( [ 'post_type' => REDIRECTS_POST_TYPE ] );

		// Nonce missing.
		$this->assertFalse( Admin_UI\handle_redirect_saving( $redirect_post_id ) );

		// Post data missing.
		$_POST['hm_redirects_nonce'] = wp_create_nonce( 'hm_redirects' );
		$this->assertFalse( Admin_UI\handle_redirect_saving( $redirect_post_id ) );

		// All data set.
		$_POST['hm_redirects_from_url']    = 'http://example.com/from';
		$_POST['hm_redirects_to_url']      = 'http://example.com/to';
		$_POST['hm_redirects_status_code'] = '403';
		$this->assertTrue( Admin_UI\handle_redirect_saving( $redirect_post_id ) );

		$saved_data = get_post( $redirect_post_id );
		$this->assertSame( md5( 'http://example.com/from' ), $saved_data->post_name );
		$this->assertSame( 'http://example.com/from', $saved_data->post_title );
		$this->assertSame( 'http://example.com/to', $saved_data->post_excerpt );
		$this->assertSame( '403', $saved_data->post_content_filtered );
	}
}
