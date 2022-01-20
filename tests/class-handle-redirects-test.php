<?php
/**
 * Test for the Utility functions.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Tests;

use HM\Redirects\Handle_Redirects;
use HM\Redirects\Post_Type as Redirects_Post_Type;
use HM\Redirects\Utilities;
use WP_UnitTestCase;

/**
 * Class Handle_Redirects_Test
 *
 * @package HM\Redirects\Tests
 */
class Handle_Redirects_Test extends WP_UnitTestCase {

	/**
	 * Provides valid test URLS.
	 *
	 * @return array
	 */
	public function provider_normalised_url_valid() {
		return [
			[ '/only/the/path', '/only/the/path' ],
			[ '/a/path/?with=queryparam', '/a/path/?with=queryparam' ],
			[ '/a/path/withbrakets?foo[bar]=baz', '/a/path/withbrakets?foo[bar]=baz' ],
			[ '/Mr%20%20WordPress', '/Mr%20%20WordPress' ],
		];
	}

	/**
	 * Provides invalid test URLs.
	 *
	 * @return array
	 */
	public function provider_normalised_url_invalid() {
		return [
			[ 'http://example.com' ], // No path.
			[ 'http://url%20invalid%20charcaters' ], // Invalid character.
			[ '/invalidcharacters¡™£¢∞§¶•ªº%5B%5D/here' ], // Invalid character.
		];
	}

	/**
	 * Provides valid test URLS.
	 *
	 * From, To, Status, Request, Result.
	 *
	 * @return array
	 */
	public function provider_redirect_uri_valid() {
		return [
			// Straight redirect.
			[ '/original-post', '/redirected-post', '/original-post', '/redirected-post', 301 ],
			[ '/?go=here', '/redirected-post', '/?go=here', '/redirected-post', 301 ],
			// Preserves query string parameters/
			[ '/original-post', '/redirected-post', '/original-post?with=query-param', '/redirected-post?with=query-param', 303 ],
			[ '/original-post', '/redirected-post?with=query-param', '/original-post', '/redirected-post?with=query-param', 303 ],
			[ '/original-post', '/redirected-post?with=query-param', '/original-post?with=query-param', '/redirected-post?with=query-param', 304 ],
			[ '/original-post', '/redirected-post?with=query-param', '/original-post?diff=query-param', '/redirected-post?with=query-param&diff=query-param', 303 ],
			[ '/original-post?b=c&a=b', '/redirected-post', '/original-post?a=b&b=c&foo=bar', '/redirected-post?foo=bar', 301 ],
		];
	}

	/**
	 * Tests get_redirect_post.
	 */
	public function test_get_redirect_post_invalid_urls() {
		$this->assertNull( Handle_Redirects\get_redirect_post( 'sfsgsdfgdfgdfgdf' ) );
	}

	/**
	 * Tests get_redirect_post.
	 */
	public function test_get_redirect_post_valid_urls() {
		$p = $this->factory->post->create( [
			'post_title' => 'Test Post',
			'post_name'  => md5( '/test/this/path' ),
			'post_type'  => Redirects_Post_Type\SLUG,
		] );
		$this->assertInstanceOf( 'WP_Post', Handle_Redirects\get_redirect_post( '/test/this/path' ) );

		wp_delete_post( $p );
	}

	/**
	 * Tests get_redirect_uri.
	 *
	 * @dataProvider provider_redirect_uri_valid
	 *
	 * @param string $from From URL.
	 * @param string $to To URL.
	 * @param string $request Requested URL.
	 * @param string $expected_result Expected resulting URL.
	 * @param int $status_code Redirect status code.
	 * @return void
	 */
	public function test_get_redirect_uri_valid_urls( $from, $to, $request, $expected_result, $status_code ) {

		$p = Utilities\insert_redirect( $from, $to, $status_code );

		$result = Handle_Redirects\get_redirect_uri( $request );
		$this->assertEquals( home_url() . $expected_result, $result );

		wp_delete_post( $p );
	}

	/**
	 * Tests get_redirect_status_code.
	 *
	 * @dataProvider provider_redirect_uri_valid
	 *
	 * @param string $original_url Original URL.
	 * @param string $expected_result Expected result.
	 * @param int    $status_code HTTP status code.
	 */
	public function test_get_redirect_status_code( $original_url, $expected_result, $status_code ) {
		// make sure we catch error when argument is missing.
		$this->assertInstanceOf( 'WP_Error', Handle_Redirects\get_redirect_status_code() );

		$this->assertEquals( 302, Handle_Redirects\get_redirect_status_code( '"^<>{}`' ) );

		$p = $this->factory->post->create(
			[
				'post_title'            => 'Test Post',
				'post_name'             => md5( $original_url ),
				'post_type'             => Redirects_Post_Type\SLUG,
				'post_excerpt'          => $expected_result,
				'post_content_filtered' => $status_code,
			]
		);

		$this->assertEquals( $status_code, Handle_Redirects\get_redirect_status_code( $original_url ) );

		wp_delete_post( $p );
	}
}
