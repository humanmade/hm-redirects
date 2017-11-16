<?php
/**
 * The Sun Redirector.
 *
 * @package TheSun_Redirector_Plugin
 * @author Human Made Limited
 */

/**
 * Class Utils_Test
 */
class Utils_Test extends TheSun_TestCase {

	/**
	 * @var TheSun_Redirector_Utils
	 */
	protected $utils;

	/**
	 *
	 */
	public function setUp() {
		$this->utils = new TheSun_Redirector_Utils();
	}

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
			[ 'http://thesun.dev' ], // No path.
			[ 'http://url%20invalid%20charcaters' ], // Invalid character.
			[ '/invalidcharacters¡™£¢∞§¶•ªº%5B%5D/here' ], // Invalid character.
		];
	}

	/**
	 * Provides valid test URLS.
  *
	 * @return array
	 */
	public function provider_redirect_uri_valid() {
		return [
			[ '/original-post', '/redirected-post', 301 ],
			[ '/original-post?with=query-param', '/redirected-post?with=query-param', 303 ],
		];
	}

	/**
	 * Tests normalise_url.
	 *
	 * @dataProvider provider_normalised_url_valid
	 *
	 * @param string $original_url Original URL.
	 * @param string $expected_result Expected result.
	 */
	public function test_normalise_url_returns_normalized_url( $original_url, $expected_result ) {
		$result = $this->utils->normalise_url( $original_url );
		$this->assertEquals( $expected_result, $result );
	}

	/**
	 * Tests normalise_url.
	 *
	 * @dataProvider provider_normalised_url_invalid
	 *
	 * @param string $original_url Original URL.
	 */
	public function test_normalize_url_returns_error_for_invalid_urls( $original_url ) {
		$result = $this->utils->normalise_url( $original_url );
		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Tests get_redirect_post.
	 */
	public function test_get_redirect_post_invalid_urls() {
		$this->assertNull( $this->invokeMethod( $this->utils, 'get_redirect_post', array( 'sfsgsdfgdfgdfgdf' ) ) );
	}

	/**
	 * Tests get_redirect_post.
	 */
	public function test_get_redirect_post_valid_urls() {
		$p = $this->factory->post->create( array( 'post_title' => 'Test Post', 'post_name' => md5( '/test/this/path' ), 'post_type' => 'thesun-redirects' ) );
		$this->assertInstanceOf( 'WP_Post', $this->invokeMethod( $this->utils, 'get_redirect_post', array( '/test/this/path' ) ) );

		wp_delete_post( $p );
	}

	/**
	 * Tests get_redirect_uri.
	 *
	 * @dataProvider provider_redirect_uri_valid
	 *
	 * @param string $original_url Original URL.
	 * @param string $expected_result Expected result.
	 */
	public function test_get_redirect_uri_valid_urls( $original_url, $expected_result ) {

		$p = $this->factory->post->create(
			[
				'post_title'   => 'Test Post',
				'post_name'    => md5( $original_url ),
				'post_type'    => 'thesun-redirects',
				'post_excerpt' => $expected_result,
			]
		);

		$result = $this->utils->get_redirect_uri( $original_url );
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
		$this->assertInstanceOf( 'WP_Error', $this->utils->get_redirect_status_code() );

		$this->assertEquals( 302, $this->utils->get_redirect_status_code( '"^<>{}`' ) );

		$p = $this->factory->post->create(
			[
				'post_title'            => 'Test Post',
				'post_name'             => md5( $original_url ),
				'post_type'             => 'thesun-redirects',
				'post_excerpt'          => $expected_result,
				'post_content_filtered' => $status_code
			]
		);

		$this->assertEquals( $status_code, $this->utils->get_redirect_status_code( $original_url ) );

		wp_delete_post( $p );
	}
}
