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
	exit;

if ( ! class_exists( 'RESTPress' ) ) {

	final class RESTPress {

		public $version = '1.0.0';
		public $api_version = '1';
		public $api_formats = array('json');
		public $selected_api_format;
		public $restpress_settings = array();
		public $api_methods = array();
		
		public function __construct()
		{
			
			$this->configure();
			register_activation_hook( __FILE__, array($this, 'activate'));
			
		}
		
		// Installation Functions
		public function configure()
		{
			
			$this->restpress_settings = $this->get_public_settings();
			$this->api_version = apply_filters('RESTPress_API_Version', $this->api_version);
			$this->api_formats = apply_filters('RESTPress_API_Formats', $this->api_formats);
			$this->api_methods = apply_filters('RESTPress_API_Methods', array('posts', 'pages', 'categories', 'users'));
			$this->selected_api_format = ($this->restpress_settings['selected_api_format']) ? $this->restpress_settings['selected_api_format'] : 'json'; // Default to JSON
			
			// Add Custom URLS
			add_action('query_vars', array($this, 'add_query_vars'));
			add_action('rewrite_rules_array', array($this, 'create_url_rewrite_rules'));
			add_action('template_redirect', array($this, 'start_api'));
		}
		
		public function create_url_rewrite_rules( $rules )
		{

			$api_rules = array();
			
			// Allow the URL to override the default output format
			$api_rules['api/v([^/]*)/([^/]*)/([^/]*)\.([^/]*)$'] = 'index.php?pagename=api&rp_api_version=$matches[1]&rp_base=$matches[2]&rp_item=$matches[3]&rp_format=$matches[4]';

			// Setup the default rewrite rules
			$api_rules['api/v([^/]*)/([^/]*)/([^/]*)$'] = 'index.php?pagename=api&rp_api_version=$matches[1]&rp_base=$matches[2]&rp_item=$matches[3]&rp_format='.$this->selected_api_format;
			$api_rules['api/v([^/]*)/([^/]*)'] = 'index.php?pagename=api&rp_api_version=$matches[1]&rp_base=$matches[2]';
			$api_rules['api/v([^/]*)'] = 'index.php?pagename=api&rp_api_version=$matches[1]';
			$api_rules['api'] = 'index.php?pagename=api';
			
			$rules = $api_rules + $rules;
			return $rules;

		}
		
		public function add_query_vars( $vars )
		{
			array_push($vars, 'rp_format', 'rp_item', 'rp_base', 'rp_api_version');
			return $vars;
		}
		
		public static function activate()
		{
			
			// Flush Rewrite Rules
			global $wp_rewrite;
		    $wp_rewrite->flush_rules();
			
		}
		
		// Admin Functions
		public function get_public_settings()
		{
			
			while(FALSE !== $_restpress_settings = unserialize(get_option('RESTPress_Public_Settings'))){
				return $_restpress_settings;
			}
			
			return array();
			
		}
		
		// API Functions
		public function start_api()
		{
			
			global $wp_query;
			
			if(!empty($wp_query->query['pagename']) && $wp_query->query['pagename'] === 'api'){
				
				$headers = $this->set_headers();
				$api_format = $this->get_api_format();
								
				// Setup basic error handling

				// No API Version
				if($wp_query->query['rp_api_version'] == ''){
					$this->return_error('No API version specified.');
				}
				
				// Incorrect Version
				if($wp_query->query['rp_api_version'] > $this->api_version){
					$this->return_error('API version specified does not exist.');
				}

				// No API Method Defined
				if($wp_query->query['rp_base'] == ''){
					$this->return_error('No API method has been defined.');
				}
				
				// Method Checking
				if(!in_array($wp_query->query['rp_base'], $this->api_methods)){
					$this->return_error('No API method "'.$wp_query->query['rp_base'].'" exists.');
				}
				
				// Check for HTTP Request Method
				// GET?
				if($_SERVER['REQUEST_METHOD'] === 'GET'){
					switch($api_format){
						case 'json':
						default: 
							echo json_encode(
								array(
									$wp_query->query['rp_base'] => $this->get_api_query($wp_query->query['rp_base'], $wp_query),
									'api_query' => $wp_query->query
								)
							);
						break;
						case 'xml':
							echo $this->xml_encode(array($wp_query->query['rp_base'] => $wp_query->query));
						break;
					}
				}
				
				// POST?
				if($_SERVER['REQUEST_METHOD'] === 'POST'){
					switch($api_format){
						case 'json':
						default: 
							echo json_encode(
								array(
									$wp_query->query['rp_base'] => $this->get_api_query($wp_query->query['rp_base'], $wp_query),
									'api_query' => $wp_query->query
								)
							);
						break;
						case 'xml':
							echo $this->xml_encode(array($wp_query->query['rp_base'] => $wp_query->query));
						break;
					}
				}
				
				// Kill the script to ensure no unneccessary WP fluff is sent afterwards.
				exit;
				
			}
			
		}
		
		public function get_api_query($base_method, $wp_query)
		{
			$api_query_results = array();
			
			// For sake of the plugin working for the talk, this is hard-coded in here, but in a future version this
			// will be modified.
			
			switch($base_method){
				case 'users':
					if(empty($wp_query->query['rp_item'])){
						$api_query_results = get_users();
					}else{
						$item = $wp_query->query['rp_item'];
						$api_query_results = get_users(array('include'=>array($item)));

						// Check for existance and return an error on no results.
						if(empty($api_query_results))
							$this->return_error('No "'.$wp_query->query['rp_base'].'" with the ID "'. $item .'" exists.');
					}
				break;
				
				case 'posts':
					if(empty($wp_query->query['rp_item'])){
						$api_query_results = get_posts();
					}else{
						$item = $wp_query->query['rp_item'];
						$api_query_results = get_posts(array('include'=>array($item)));

						// Check for existance and return an error on no results.
						if(empty($api_query_results))
							$this->return_error('No "'.$wp_query->query['rp_base'].'" with the ID "'. $item .'" exists.');
					}
				break;
			}
			
			return $api_query_results;
		}
		
		public function set_headers()
		{
			
			$api_format = $this->get_api_format();
				
			switch($api_format){
				case 'json':
				default:
					header('Content-Type: application/json');
				break;
				case 'xml':
					header('Content-Type: text/xml');
				break;
			}
			
		}
		
		private function get_api_format()
		{
			
			global $wp_query;
			$api_format = $this->selected_api_format;
			if($wp_query->query['rp_format'])
				$api_format = $wp_query->query['rp_format'];
				
			return $api_format;
			
		}
		
		private function return_error($message)
		{
			switch($api_format){
				case 'json':
				default:
					echo json_encode(array('error' => $message));
				break;
				case 'xml':
					echo $this->xml_encode(array('error' => $message));
				break;
			}
			
			exit;
			
		}
		
		private function xml_encode($mixed, $domElement=null, $DOMDocument=null) 
		{
		    
			if (is_null($DOMDocument)) {
		        $DOMDocument =new DOMDocument;
		        $DOMDocument->formatOutput = false;
		        $this->xml_encode($mixed, $DOMDocument, $DOMDocument);
		        echo $DOMDocument->saveXML();
		    }
		    else {
		        if (is_array($mixed)) {
		            foreach ($mixed as $index => $mixedElement) {
		                if (is_int($index)) {
		                    if ($index === 0) {
		                        $node = $domElement;
		                    }
		                    else {
		                        $node = $DOMDocument->createElement($domElement->tagName);
		                        $domElement->parentNode->appendChild($node);
		                    }
		                }
		                else {
		                    $plural = $DOMDocument->createElement($index);
		                    $domElement->appendChild($plural);
		                    $node = $plural;
		                    if (!(rtrim($index, 's') === $index)) {
		                        $singular = $DOMDocument->createElement(rtrim($index, 's'));
		                        $plural->appendChild($singular);
		                        $node = $singular;
		                    }
		                }

		                $this->xml_encode($mixedElement, $node, $DOMDocument);
		            }
		        }
		        else {
		            $mixed = is_bool($mixed) ? ($mixed ? 'true' : 'false') : $mixed;
		            $domElement->appendChild($DOMDocument->createTextNode($mixed));
		        }
		    }
		}
		
	}
	
}

$restpress = &new RESTPress();

?>