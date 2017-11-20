<?php
/**
 * Test for the admin UI.
 */

namespace HM\Redirects\Tests;

use HM\Redirects\Admin_UI;
use WP_UnitTestCase;

class Admin_UI_Test extends WP_UnitTestCase {

	/**
	 * Test the validation method.
	 */
	public function test_validate_meta() {
		$this->assertSame( 'Fields are required', Admin_UI\validate_meta( '', '' ) );
	}
}
