<?php
/**
 * Plugin Name: RESTPress
 * Plugin URI: http://apphaus.io/open/labs/restpress
 * Description: RESTPress is a plugin that enables a RESTFul API running on your WordPress powered website. It provides a full extendable format driven, RESTful approach, utilizing the power of the Core WordPress publishing engine and it's facilities, such as the Media Library etc.
 * Version: 1.0.0
 * Author: Byron Rode
 * Author URI: http://www.byronrode.com
 * Requires at least: 3.5
 * Tested up to: 3.6
 *
 *
 * @package RESTPress
 * @category Core
 * @author Byron Rode
 */


if ( ! defined( 'ABSPATH' ) )
	exit; // Die if file, or class accessed directly.

if ( ! class_exists( 'RESTPress' ) ) {

	final class RESTPress {

		public $version = '1.0.0';
		public $api_version = '1';
		
	}
	
}

?>