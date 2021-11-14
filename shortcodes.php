<?php


add_shortcode("wpform_edit_entries", "shortcode_whe_edit_entries");
add_shortcode("wpform_delete_entries", "shortcode_whe_delete_entries");

function whe_enqueue_scripts($form_id)
{
     $form = null;
     $forms  = get_posts(
          [
               'post_type' => 'wpforms',
               'nopaging'  => true,
               'post__in'  => isset($_POST['forms']) ? array_map('intval', $_POST['forms']) : [], //phpcs:ignore WordPress.Security.NonceVerification
          ]
     );

     foreach ($forms as $formv) {

          if (absint($formv->ID) == absint($form_id)) {
               $form = $formv;
               break;
          }
     }

     //$min = wpforms_get_min_suffix();

     if (wpforms_has_field_setting('input_mask', $form, true)) {
          // Load jQuery input mask library - https://github.com/RobinHerbots/jquery.inputmask.
          wp_enqueue_script(
               'wpforms-maskedinput',
               WPFORMS_PLUGIN_URL . 'assets/js/jquery.inputmask.min.js',
               ['jquery'],
               '5.0.6',
               true
          );
     }

     // Load admin utils JS.
     wp_enqueue_script(
          'wpforms-admin-utils',
          WPFORMS_PLUGIN_URL . 'assets/js/admin-utils.js',
          ['jquery'],
          WPFORMS_VERSION,
          true
     );

     wp_enqueue_script(
          'wpforms-punycode',
          WPFORMS_PLUGIN_URL . "assets/js/punycode.js",
          [],
          '1.0.0',
          true
     );

     if (wpforms_has_field_type('richtext', $form)) {
          wp_enqueue_script(
               'wpforms-richtext-field',
               WPFORMS_PLUGIN_URL . "pro/assets/js/fields/richtext.js",
               ['jquery'],
               WPFORMS_VERSION,
               true
          );
     }

     // Load frontend base JS.
     wp_enqueue_script(
          'wpforms-frontend',
          plugin_dir_url(__FILE__) . 'scripts/customwpforms.js',
          ['jquery'],
          WPFORMS_VERSION,
          true
     );

     // Load admin JS.
     wp_enqueue_script(
          'wpforms-admin-edit-entry',
          WPFORMS_PLUGIN_URL . "pro/assets/js/admin/edit-entry.js",
          ['jquery'],
          WPFORMS_VERSION,
          true
     );

     // Localize frontend strings.
     wp_localize_script(
          'wpforms-frontend',
          'wpforms_settings',
          wpforms()->frontend->get_strings()
     );

     // Localize edit entry strings.
     wp_localize_script(
          'wpforms-admin-edit-entry',
          'wpforms_admin_edit_entry',
          get_localized_data()
     );
}

function whe_load_scripts()
{
     wp_register_script('whe-edit', plugin_dir_url(__FILE__) . 'scripts/edit.js', []);
     wp_register_script('whe-delete', plugin_dir_url(__FILE__) . 'scripts/delete.js', []);
     wp_register_style('whe-style-entries', plugin_dir_url(__FILE__) . 'styles/entries.css', []);
}
add_action('wp_enqueue_scripts', 'whe_load_scripts');

function get_localized_data()
{

     $data['strings'] = [
          'update'            => esc_html__('Update', 'wpforms'),
          'success'           => esc_html__('Success', 'wpforms'),
          'continue_editing'  => esc_html__('Continue Editing', 'wpforms'),
          'view_entry'        => esc_html__('View Entry', 'wpforms'),
          'msg_saved'         => esc_html__('The entry was successfully saved.', 'wpforms'),
          'entry_delete_file' => esc_html__('Are you sure you want to permanently delete the file "{file_name}"?', 'wpforms'),
          'entry_empty_file'  => esc_html__('Empty', 'wpforms'),
     ];

     // View Entry URL.
     $data['strings']['view_entry_url'] = add_query_arg(
          [
               'page'     => 'wpforms-entries',
               'view'     => 'details',
          ],
          admin_url('admin.php')
     );

     return $data;
}
function generateRandomString($length = 10)
{
     return substr(str_shuffle(str_repeat($x = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}
function shortcode_whe_edit_entries($atts)
{
     $r_str = generateRandomString();
     if (empty($atts['form_id'])) {
          echo "Error please insert a form id";
          return;
     }
?>
     <div class="whe-edit" id="<?php echo $r_str; ?>">
          <div class="table-container"></div>
          <div class="form-container"></div>
     </div>
<?php
     wp_localize_script('whe-edit', 'ajax_var', array(
          'url'    => admin_url('admin-ajax.php'),
          'nonce'  => wp_create_nonce('my-ajax-nonce'),
          'action' => 'whe_edit_entry',
          'form_id' => $atts['form_id'],
          'id' => $r_str
     ));

     wp_enqueue_script('whe-edit');
     wp_enqueue_style('whe-style-entries');
     whe_enqueue_scripts($atts['form_id']);
}

function shortcode_whe_delete_entries()
{
?>

<?php
     wp_enqueue_script('whe-delete');
}
