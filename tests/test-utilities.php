<?php
/**
 * Test for the Utility functions.
 */

namespace HM\Redirects\Tests;

use HM\Redirects\Utilities;
use WP_UnitTestCase;

class Utilities_Test extends WP_UnitTestCase {

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
		$result = Utilities\normalise_url( $original_url );
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
		$result = Utilities\normalise_url( $original_url );
		$this->assertInstanceOf( 'WP_Error', $result );
	}
}
