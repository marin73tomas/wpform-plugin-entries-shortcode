<?php
add_action("wp_ajax_edit_entry", "edit_entry");
add_action("wp_ajax_nopriv_edit_entry", "edit_entry");
add_action("wp_ajax_delete_entry", "delete_entry");
add_action("wp_ajax_nopriv_delete_entry", "delete_entry");
add_action("wp_ajax_load_edit_entry", "whe_load_edit_entry");
add_action("wp_ajax_load_edit_entry", "whe_load_edit_entry");


function whe_load_scripts()
{
     wp_register_script('whe-edit', plugin_dir_url(__FILE__) . 'scripts/edit.js', []);
     wp_register_script('whe-delete', plugin_dir_url(__FILE__) . 'scripts/delete.js', []);
}
add_action('wp_enqueue_scripts', 'whe_load_scripts');


function whe_load_edit_entry()
{
     $nonce = sanitize_text_field($_POST['nonce']);

     if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
          die('Busted!');
     }

     $form_id = $_POST['form_id'];
     $user_id = get_current_user_id();


     $form = wpforms()->form->get(absint($form_id));
     if (empty($form)) {
          wp_die("Error, the form could not be found");
     }


     // Pull and format the form data out of the form object.
     $form_data = !empty($form->post_content) ? wpforms_decode($form->post_content) : '';

     $form_fields = $form_data['fields'];

     // Here we define what the types of form fields we do NOT want to include,
     // instead they should be ignored entirely.
     $form_fields_disallow = apply_filters('wpforms_frontend_entries_table_disallow', ['divider', 'html', 'pagebreak', 'captcha']);

     // Loop through all form fields and remove any field types not allowed.
     foreach ($form_fields as $field_id => $form_field) {
          if (in_array($form_field['type'], $form_fields_disallow, true)) {
               unset($form_fields[$field_id]);
          }
     }

     $entries_args = [
          'form_id' => absint($form_id),
     ];


     $entries_args['user_id'] = $user_id;



     $entries = wpforms()->entry->get_entries($entries_args);

     if (empty($entries)) {
          wp_die("No entries found");
     }
     $response_entries = [];


     // if (
     //      !is_admin() && !isset($_COOKIE['domain_newvisitor'])
     // ) {
     //      setcookie('domain_newvisitor', 1, time() + 3600 * 24 * 100, '/', 'domain.com', false);
     // }
     //print_r($_COOKIE);

     $url = 'http://astra.local/wp-admin/admin.php?page=wpforms-entries&view=edit&entry_id=3';
     #Create cookie string
     $cookie_string = 'n5}RqZ%6>`&~9atp';
     foreach ($_COOKIE as $k => $v)
          #Assure we are setting the proper string if other cookies are set
          if (preg_match('/(wordpress_test_cookie|wordpress_logged_in_|wp-settings-1|wp-settings-time-1)/', $k))
               $cookie_string .= $k . '=' . urlencode($v) . '; ';

     #Remove stray delimiters
     $cookie_string = trim($cookie_string, '; ');

     #Prep headers
     $headers = array(
          'Cookie' => $cookie_string
     );
     echo $cookie_string;

     // #Make Request
     // $http = new WP_Http;
     // $response = $http->request($url, array('method' => 'GET', 'headers' => $headers));

     // #Retrieve Body
     // $body = wp_remote_retrieve_body($response);
     // echo $body;



     foreach ($entries as $entry) {


          // Entry field values are in JSON, so we need to decode.

          $entry_fields = json_decode($entry->fields, true);

          foreach ($form_fields as $form_field) {


               foreach ($entry_fields as $entry_field) {
                    if (absint($entry_field['id']) === absint($form_field['id']) && $entry->user_id == get_current_user_id()) {

                         array_push($response_entries, [
                              'form_id' => $form_id,
                              'entry_id' => $entry->entry_id,
                              'fields' => json_decode($entry->fields, true),
                              ///'xml_content' => $content
                         ]);
                         // echo apply_filters('wpforms_html_field_value', wp_strip_all_tags($entry_field['value']), $entry_field, $form_data, 'entry-frontend-table');
                         break;
                    }
               }
          }
     }

     // $export_form = null;
     // $forms  = get_posts(
     //      [
     //           'post_type' => 'wpforms',
     //           'nopaging'  => true,
     //           'post__in'  => isset($_POST['forms']) ? array_map('intval', $_POST['forms']) : [], //phpcs:ignore WordPress.Security.NonceVerification
     //      ]
     // );

     // foreach ($forms as $form) {

     //      if (absint($form->ID) == absint($form_id)) {
     //           $export_form = wpforms_decode($form->post_content);
     //           break;
     //      }
     // }



     // echo wp_json_encode([
     //      // "form_fields" => $export_form,
     //      "entry_fields" => $response_entries,
     // ]);

     wp_die();
}

function whe_edit_entry()
{
     echo "";
}


function whe_delete_entry()
{
     echo "";
}
