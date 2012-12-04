<?php
/*
Plugin Name: Filters
Plugin URI: http://wordpress.org/extend/plugins/filters/
Description: A flexible replacement to WP's admin listing filters.
Version: 0.1
Author: Scott Kingsley Clark
Author URI: https://scottkclark.com/
License: GPL2
*/

define( 'FILTERS_URL', plugin_dir_url( __FILE__ ) );
define( 'FILTERS_DIR', plugin_dir_path( __FILE__ ) );

include FILTERS_DIR . 'functions.php';
include FILTERS_DIR . 'wp/ui.php';

$filters_plugin = new Filters_Plugin();

class Filters_Plugin
{
    function __construct() {
    }
}