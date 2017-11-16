<?php
/**
 * Test for the admin UI.
 */

namespace HM\Redirects\Tests;

use WP_UnitTestCase;

class Admin_UI_Test extends WP_UnitTestCase {

	/**
	 * Test the validation method.
	 */
	public function test_validate_meta() {
		$this->assertEquals( 'Fields are required', $this->invokeMethod( $this->admin, 'validate_meta', [ '', '', true ] ) );
	}
}
