<?php
/**
 * Test for the Utility functions.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\Tests;

use HM\Redirects\Utilities;
use WP_UnitTestCase;

/**
 * Class Utilities_Test
 *
 * @package HM\Redirects\Tests
 */
class Utilities_Test extends WP_UnitTestCase {
	/**
	 * Provides invalid test URLs.
	 *
	 * @return array
	 */
	public function provider_normalised_url_invalid() {
		return [
			[ 'http://example.com' ], // No path.
			[ 'http://url%20invalid%20charcaters' ], // Invalid character.
		];
	}

	/**
	 * Provides valid test URLs.
	 *
	 * @return array
	 */
	public function provider_normalised_url_valid() {
		return [
			// Absolute URL.
			[ 'http://example.com/path', '/path' ],
			// Relative URL, with a leading slash.
			[ '/only/the/path', '/only/the/path' ],
			// Relative URL, without a leading slash.
			[ 'only/the/path', 'only/the/path' ],
			// Relative URL, with query parameter.
			[ '/a/path/?with=queryparam', '/a/path/?with=queryparam' ],
			// Relative URL, with query parameter using brackets.
			[ '/a/path/withbrakets?foo[bar]=baz', '/a/path/withbrakets?foo[bar]=baz' ],
			// Relative URL, with encoded spaces.
			[ '/Mr%20%20WordPress', '/Mr%20%20WordPress' ],
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
	 * Provides absolute and relative ASCII only URLs.
	 *
	 * @return array
	 */
	public function provider_ascii_urls() {
		return [
			// Absolute URL.
			[ 'http://example.com/path', 'http://example.com/path' ],
			// Relative URL, with a leading slash.
			[ '/only/the/path', '/only/the/path' ],
			// Relative URL, without a leading slash.
			[ 'only/the/path', '/only/the/path' ],
			// Relative URL, with query parameter.
			[ '/a/path/?with=queryparam', '/a/path/?with=queryparam' ],
			// Ordered query parameters.
			[ '/a/path/?c=d&a=b', '/a/path/?a=b&c=d' ],
			// Relative URL, with query parameter using brackets.
			[ '/a/path/withbrakets?foo[bar]=baz', '/a/path/withbrakets?foo%5Bbar%5D=baz' ],
			// Relative URL, with encoded spaces.
			[ '/Mr%20%20WordPress', '/Mr%20%20WordPress' ],
		];
	}

	/**
	 * Provides absolute and relative internationalised URLs.
	 *
	 * @return array
	 */
	public function provider_non_ascii_urls() {
		return [
			// Absolute URL, URL encoded.
			[ 'http://example.com/%E3%81%AE%E5%9B%BD%E9%9A%9B%E7%B7%9A%E3%81%AE%E5%8F%97%E8%A8%97%E6%89%8B%E8%8D%B7%E7%89%A9*%E3%81%A8', 'http://example.com/の国際線の受託手荷物*と' ],
			// Relative URL, with leading slash, URL encoded.
			[ '%2F%E3%81%AE%E5%9B%BD%E9%9A%9B%E7%B7%9A%E3%81%AE%E5%8F%97%E8%A8%97%E6%89%8B%E8%8D%B7%E7%89%A9*%E3%81%A8', '/の国際線の受託手荷物*と' ],
			// Relative URL, without leading slash, URL encoded.
			[ '%E3%81%AE%E5%9B%BD%E9%9A%9B%E7%B7%9A%E3%81%AE%E5%8F%97%E8%A8%97%E6%89%8B%E8%8D%B7%E7%89%A9*%E3%81%A8', '/の国際線の受託手荷物*と' ],
			// Absolute URL.
			[ 'http://example.com/の国際線の受託手荷物*と', 'http://example.com/の国際線の受託手荷物*と' ],
			// Relative URL, with leading slash.
			[ '/の国際線の受託手荷物*と', '/の国際線の受託手荷物*と' ],
			// Relative URL, without leading slash.
			[ 'の国際線の受託手荷物*と', '/の国際線の受託手荷物*と' ],
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

	/**
	 * Test `test_add_leading_slash()`.
	 */
	public function test_add_leading_slash() {
		$this->assertSame( '/foo', Utilities\add_leading_slash( 'foo' ) );
		$this->assertSame( '/foo', Utilities\add_leading_slash( '/foo' ) );
		$this->assertSame( '/foo', Utilities\add_leading_slash( '//foo' ) );
	}

	/**
	 * Test `sanitise_and_normalise_url()` with ASCII and non-ASCII URLs.
	 *
	 * @dataProvider provider_non_ascii_urls
	 * @dataProvider provider_ascii_urls
	 *
	 * @param string $original_url Original URL.
	 * @param string $expected_url Expected URL.
	 */
	public function test_sanitise_and_normalise_url( $original_url, $expected_url ) {
		$this->assertSame( $expected_url, Utilities\sanitise_and_normalise_url( $original_url ) );
	}
}
