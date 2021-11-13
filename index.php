<?php

/*

Plugin Name: WPForms handle entries

Description: A plugin that adds two forms via shortcodes and displays a form that the frontend users can use to edit/delete their WPform entries data.
 
Version: 0.1
 
Author: Tomas
 
Author URI: https://github.com/marin73tomas/wpform-plugin-entries-shortcode

*/

if (!defined('ABSPATH')) {
     exit; // Exit if accessed directly.
}


include_once("submit.php");
//include_once("edit.php");
include_once("ajax.php");
include_once("shortcodes.php");
