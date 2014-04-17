<?php
/*
Plugin Name: Filters
Plugin URI: http://wordpress.org/plugins/filters/
Description: A flexible replacement to WordPress admin post listing filters.
Version: 0.3
Author: Scott Kingsley Clark
Author URI: https://scottkclark.com/
License: GPL2
*/

define( 'FILTERS_URL', plugin_dir_url( __FILE__ ) );
define( 'FILTERS_DIR', plugin_dir_path( __FILE__ ) );

class Filters_Plugin {

	public static $active_filters = array();

	function __construct() {

	}

	function admin_init() {

		global $pagenow;

		if ( 'edit.php' == $pagenow ) {
			include_once FILTERS_DIR . 'functions.php';
			include_once FILTERS_DIR . 'ui/wp/ui.php';
		}

	}

}

global $filters_plugin;

$filters_plugin = new Filters_Plugin();

add_action( 'admin_init', array( $filters_plugin, 'admin_init' ) );