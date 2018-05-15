<?php
/**
 * Plugin Name: HM Redirects
 *
 * @package hm-redirects
 *
 * Description: Simple plugin for handling redirects in a scalable manner.
 * Version: 0.2
 * Author: Human Made Limited
 * Author URI: https://humanmade.com/
 * Text Domain: hm-redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/post-type.php';
HM\Redirects\Post_Type\setup();

require_once __DIR__ . '/includes/admin.php';
HM\Redirects\Admin_UI\setup();

require_once __DIR__ . '/includes/handle-redirects.php';
HM\Redirects\Handle_Redirects\setup();

require_once __DIR__ . '/includes/utilities.php';
