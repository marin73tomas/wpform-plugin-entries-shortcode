<?php

use WPForms\Pro\Forms\Fields\Base\EntriesEdit;

add_action("wp_ajax_edit_entry", "edit_entry");
add_action("wp_ajax_nopriv_edit_entry", "edit_entry");
add_action("wp_ajax_delete_entry", "delete_entry");
add_action("wp_ajax_nopriv_delete_entry", "delete_entry");

add_action("wp_ajax_load_edit_entries", "whe_load_edit_entries");
add_action("wp_ajax_nopriv_load_edit_entries", "whe_load_edit_entries");




add_action("wp_ajax_load_entry_form", "load_entry_form");

add_action("wp_ajax_load_entry_form", "load_entry_form");
function display_edit_form_field_non_editable($field_value)
{

     echo '<p class="wpforms-entry-field-value">';
     echo !wpforms_is_empty_string($field_value) ?
          nl2br(make_clickable($field_value)) : // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          esc_html__('Empty', 'wpforms');
     echo '</p>';
}
function get_empty_entry_field_data($field_properties)
{

     return [
          'name'      => !empty($field_properties['label']) ? $field_properties['label'] : '',
          'value'     => '',
          'value_raw' => '',
          'id'        => !empty($field_properties['id']) ? $field_properties['id'] : '',
          'type'      => !empty($field_properties['type']) ? $field_properties['type'] : '',
     ];
}
function is_field_can_be_displayed($type)
{

     $dont_display = ['divider', 'entry-preview', 'html', 'pagebreak'];

     return !empty($type) && !in_array($type, (array) apply_filters('wpforms_pro_admin_entries_edit_fields_dont_display', $dont_display), true);
}
function is_field_entries_editable($type)
{

     $editable = in_array($type,  wpforms()->get('entry')->get_editable_field_types(), true);

     /**
      * Allow change if the field is editable regarding to its type.
      *
      * @since 1.6.0
      *
      * @param bool   $editable True if is editable.
      * @param string $type     Field type.
      *
      * @return bool
      */
     return (bool) apply_filters('wpforms_pro_admin_entries_edit_field_editable', $editable, $type);
}
function get_entries_edit_field_object($type)
{

     // Runtime objects holder.
     static $objects = [];

     // Getting the class name.
     $class_name = implode('', array_map('ucfirst', explode('-', $type)));
     $class_name = '\WPForms\Pro\Forms\Fields\\' . $class_name . '\EntriesEdit';

     // Init object if needed.
     if (empty($objects[$type])) {
          $objects[$type] = class_exists($class_name) ? new $class_name() : new EntriesEdit($type);
     }

     return apply_filters("wpforms_pro_admin_entries_edit_field_object_{$type}", $objects[$type]);
}
function load_entry_form()
{
     // No entry ID was provided, abort.
     if (empty($_POST['entry_id'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
          $abort_message = esc_html__('It looks like the provided entry ID isn\'t valid.', 'wpforms');
          $abort         = true;

          return;
     }

     // Find the entry.
     $entry = wpforms()->entry->get(absint($_POST['entry_id'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

     // No entry was found, abort.
     if (!$entry || empty($entry)) {
          $abort_message = esc_html__('It looks like the entry you are trying to access is no longer available.', 'wpforms');
          $abort         = true;

          return;
     }

     // Find the form information.
     $form = wpforms()->form->get($entry->form_id, ['cap' => 'view_entries_form_single']);

     // Form details.
     $form_data      = wpforms_decode($form->post_content);
     $form_id        = !empty($form_data['id']) ? $form_data['id'] : $entry->form_id;
     $form->form_url = add_query_arg(
          [
               'page'    => 'wpforms-entries',
               'view'    => 'list',
               'form_id' => absint($form_id),
          ],
          admin_url('admin.php')
     );

     // Define other entry details.
     $entry->entry_next       = wpforms()->entry->get_next($entry->entry_id, $form_id);
     $entry->entry_next_url   = !empty($entry->entry_next) ? add_query_arg(array('page' => 'wpforms-entries', 'view' => 'details', 'entry_id' => absint($entry->entry_next->entry_id)), admin_url('admin.php')) : '#';
     $entry->entry_next_class = !empty($entry->entry_next) ? '' : 'inactive';
     $entry->entry_prev       = wpforms()->entry->get_prev($entry->entry_id, $form_id);
     $entry->entry_prev_url   = !empty($entry->entry_prev) ? add_query_arg(array('page' => 'wpforms-entries', 'view' => 'details', 'entry_id' => absint($entry->entry_prev->entry_id)), admin_url('admin.php')) : '#';
     $entry->entry_prev_class = !empty($entry->entry_prev) ? '' : 'inactive';
     $entry->entry_prev_count = wpforms()->entry->get_prev_count($entry->entry_id, $form_id);
     $entry->entry_count      = wpforms()->entry->get_entries(['form_id' => $form_id], true);

     $entry->entry_notes = wpforms()->entry_meta->get_meta(
          [
               'entry_id' => $entry->entry_id,
               'type'     => 'note',
          ]
     );
     $entry->entry_logs  = wpforms()->entry_meta->get_meta(
          [
               'entry_id' => $entry->entry_id,
               'type'     => 'log',
          ]
     );

     // Check for other entries by this user.
     if (!empty($entry->user_id) || !empty($entry->user_uuid)) {
          $args    = [
               'form_id'   => $form_id,
               'user_id'   => !empty($entry->user_id) ? $entry->user_id : '',
               'user_uuid' => !empty($entry->user_uuid) ? $entry->user_uuid : '',
          ];
          $related = wpforms()->entry->get_entries($args);

          foreach ($related as $key => $r) {
               if ((int) $r->entry_id === (int) $entry->entry_id) {
                    unset($related[$key]);
               }
          }
          $entry->entry_related = $related;
     }

     // Make public.


     // Lastly, mark entry as read if needed.
     if ($entry->viewed !== '1' && empty($_POST['action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
          $is_success = wpforms()->entry->update(
               $entry->entry_id,
               [
                    'viewed' => '1',
               ]
          );
     }

     if (!empty($is_success)) {
          wpforms()->entry_meta->add(
               [
                    'entry_id' => $entry->entry_id,
                    'form_id'  => $form_id,
                    'user_id'  => get_current_user_id(),
                    'type'     => 'log',
                    'data'     => wpautop(sprintf('<em>%s</em>', esc_html__('Entry read.', 'wpforms'))),
               ],
               'entry_meta'
          );

          $entry->viewed     = '1';
          $entry->entry_logs = wpforms()->entry_meta->get_meta(
               [
                    'entry_id' => $entry->entry_id,
                    'type'     => 'log',
               ]
          );
     }

?>
     <div id="wpforms-entries-single" class="wrap wpforms-admin-wrap">

          <h1 class="page-title">

               <?php esc_html_e('View Entry', 'wpforms'); ?>

               <?php


               $form_data = wpforms_decode($form->post_content);
               ?>

               <a href="<?php echo esc_url($form->form_url); ?>" class="add-new-h2 wpforms-btn-orange"><?php esc_html_e('Back to All Entries', 'wpforms'); ?></a>

               <div class="wpforms-entry-navigation">
                    <span class="wpforms-entry-navigation-text">
                         <?php
                         printf(
                              /* translators: %1$s - current number of entry; %2$s - total number of entries. */
                              esc_html__('Entry %1$s of %2$s', 'wpforms'),
                              $entry->entry_prev_count + 1,
                              $entry->entry_count
                         );
                         ?>
                    </span>
                    <span class="wpforms-entry-navigation-buttons">
                         <a href="<?php echo esc_url($entry->entry_prev_url); ?>" title="<?php esc_attr_e('Previous form entry', 'wpforms'); ?>" id="wpforms-entry-prev-link" class="add-new-h2 wpforms-btn-grey <?php echo $entry->entry_prev_class; ?>">
                              <span class="dashicons dashicons-arrow-left-alt2"></span>
                         </a>

                         <span class="wpforms-entry-current" title="<?php esc_attr_e('Current form entry', 'wpforms'); ?>"><?php echo $entry->entry_prev_count + 1; ?></span>

                         <a href="<?php echo esc_url($entry->entry_next_url); ?>" title="<?php esc_attr_e('Next form entry', 'wpforms'); ?>" id="wpforms-entry-next-link" class=" add-new-h2 wpforms-btn-grey <?php echo $entry->entry_next_class; ?>">
                              <span class="dashicons dashicons-arrow-right-alt2"></span>
                         </a>
                    </span>
               </div>

          </h1>

          <div class="wpforms-admin-content">

               <div id="poststuff">

                    <div id="post-body" class="metabox-holder columns-2">

                         <!-- Left column -->
                         <div id="post-body-content" style="position: relative;">
                              <?php
                              $hide_empty = isset($_COOKIE['wpforms_entry_hide_empty']) && 'true' === $_COOKIE['wpforms_entry_hide_empty'];
                              $form_title = !isset($form_data['settings']['form_title']) ? $form_data['settings']['form_title'] : '';

                              if (empty($form_title)) {
                                   $form = wpforms()->get('form')->get($entry->form_id);

                                   $form_title = !empty($form)
                                        ? $form->post_title
                                        : sprintf( /* translators: %d - form id. */
                                             esc_html__('Form (#%d)', 'wpforms'),
                                             $entry->form_id
                                        );
                              }
                              ?>
                              <!-- Entry Fields metabox -->
                              <div id="wpforms-entry-fields" class="postbox">

                                   <div class="postbox-header">
                                        <h2 class="hndle">
                                             <?php echo '1' === (string) $entry->starred ? '<span class="dashicons dashicons-star-filled"></span>' : ''; ?>
                                             <span><?php echo esc_html($form_title); ?></span>
                                             <a href="#" class="wpforms-empty-field-toggle">
                                                  <?php echo $hide_empty ? esc_html__('Show Empty Fields', 'wpforms') : esc_html__('Hide Empty Fields', 'wpforms'); ?>
                                             </a>
                                        </h2>
                                   </div>

                                   <div class="inside">

                                        <?php
                                        $fields = apply_filters('wpforms_entry_single_data', wpforms_decode($entry->fields), $entry, $form_data);

                                        if (empty($fields)) {

                                             // Whoops, no fields! This shouldn't happen under normal use cases.
                                             echo '<p class="no-fields">' . esc_html__('This entry does not have any fields', 'wpforms') . '</p>';
                                        } else {

                                             // Display the fields and their values
                                             foreach ($fields as $key => $field) {

                                                  // We can't display the field of unknown type.
                                                  if (empty($field['type'])) {
                                                       continue;
                                                  }

                                                  $field_value  = isset($field['value']) ? $field['value'] : '';
                                                  $field_value  = apply_filters('wpforms_html_field_value', wp_strip_all_tags($field_value), $field, $form_data, 'entry-single');
                                                  $field_class  = sanitize_html_class('wpforms-field-' . $field['type']);
                                                  $field_class .= wpforms_is_empty_string($field_value) ? ' empty' : '';
                                                  $field_style  = $hide_empty && empty($field_value) ? 'display:none;' : '';

                                                  echo '<div class="wpforms-entry-field ' . $field_class . '" style="' . $field_style . '">';

                                                  // Field name
                                                  echo '<p class="wpforms-entry-field-name">';
                                                  /* translators: %d - field ID. */
                                                  echo !empty($field['name']) ? wp_strip_all_tags($field['name']) : sprintf(esc_html__('Field ID #%d', 'wpforms'), absint($field['id']));
                                                  echo '</p>';

                                                  // Field value
                                                  echo '<div class="wpforms-entry-field-value">';
                                                  echo !wpforms_is_empty_string($field_value) ? nl2br(make_clickable($field_value)) : esc_html__('Empty', 'wpforms');
                                                  echo '</div>';

                                                  echo '</div>';
                                             }
                                        }
                                        ?>

                                   </div>

                              </div>

                         </div>

                         <!-- Right column -->
                         <div id="postbox-container-1" class="postbox-container">

                         </div>

                    </div>

               </div>

          </div>

     </div>

     <?php
     echo "xd";

     $view_entry_url = add_query_arg(
          [
               'page' => 'wpforms-entries',
               'view' => 'details',
               'entry_id' => $entry->entry_id,
          ],
          admin_url('admin.php')
     );

     $form_atts = [
          'id' => 'wpforms-edit-entry-form',
          'class' => ['wpforms-form', 'wpforms-validate'],
          'data' => [
               'formid' => $form_id,
          ],
          'atts' => [
               'method' => 'POST',
               'enctype' => 'multipart/form-data',
               'action' => esc_url_raw(remove_query_arg('wpforms')),
          ],
     ];
     $entry_fields = wpforms_decode($entry->fields);
     ?>

     <div id="wpforms-entries-single" class="wrap wpforms-admin-wrap">

          <h1 class="page-title">
               <?php esc_html_e('Edit Entry', 'wpforms'); ?>
               <a href="<?php echo esc_url($view_entry_url); ?>" class="add-new-h2 wpforms-btn-orange"><?php esc_html_e('Back to Entry', 'wpforms'); ?></a>
          </h1>

          <div class="wpforms-admin-content">

               <div id="poststuff">

                    <div id="post-body" class="metabox-holder columns-2">

                         <?php
                         printf('<div class="wpforms-container wpforms-edit-entry-container" id="wpforms-%d">', (int) $form_id);
                         echo '<form ' . wpforms_html_attributes($form_atts['id'], $form_atts['class'], $form_atts['data'], $form_atts['atts']) . '>';
                         ?>

                         <!-- Left column -->
                         <div id="post-body-content" style="position: relative;">
                              <?php $hide_empty = isset($_COOKIE['wpforms_entry_hide_empty']) && 'true' === $_COOKIE['wpforms_entry_hide_empty'];
                              ?>
                              <!-- Edit Entry Form metabox -->
                              <div id="wpforms-entry-fields" class="postbox">

                                   <div class="postbox-header">
                                        <h2 class="hndle">
                                             <?php echo '1' === (string) $entry->starred ? '<span class="dashicons dashicons-star-filled"></span>' : ''; ?>
                                             <span><?php echo esc_html($form_data['settings']['form_title']); ?></span>
                                             <a href="#" class="wpforms-empty-field-toggle">
                                                  <?php echo $hide_empty ? esc_html__('Show Empty Fields', 'wpforms') : esc_html__('Hide Empty Fields', 'wpforms'); ?>
                                             </a>
                                        </h2>
                                   </div>

                                   <div class="inside">

                                        <?php
                                        if (empty($entry_fields)) {

                                             // Whoops, no fields! This shouldn't happen under normal use cases.
                                             echo '<p class="no-fields">' . esc_html__('This entry does not have any fields', 'wpforms') . '</p>';
                                        } else {

                                             // Display the fields and their editable values.
                                             $form_id = (int) $form_data['id'];

                                             echo '<input type="hidden" name="wpforms[id]" value="' . esc_attr($form_id) . '">';
                                             echo '<input type="hidden" name="wpforms[entry_id]" value="' . esc_attr($entry->entry_id) . '">';
                                             echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('wpforms-entry-edit-' . $form_id . '-' . $entry->entry_id)) . '">';

                                             if (empty($form_data['fields']) || !is_array($form_data['fields'])) {
                                                  echo '<div class="wpforms-edit-entry-field empty">';
                                                  echo '<p class="wpforms-entry-field-value">';

                                                  if (\wpforms_current_user_can('edit_form_single', $form_data['id'])) {
                                                       $edit_url = add_query_arg(
                                                            array(
                                                                 'page'    => 'wpforms-builder',
                                                                 'view'    => 'fields',
                                                                 'form_id' => absint($form_data['id']),
                                                            ),
                                                            admin_url('admin.php')
                                                       );
                                                       printf(
                                                            wp_kses( /* translators: %s - form edit URL. */
                                                                 __('You don\'t have any fields in this form. <a href="%s">Add some!</a>', 'wpforms'),
                                                                 [
                                                                      'a' => [
                                                                           'href' => [],
                                                                      ],
                                                                 ]
                                                            ),
                                                            esc_url($edit_url)
                                                       );
                                                  } else {
                                                       esc_html_e('You don\'t have any fields in this form.', 'wpforms');
                                                  }

                                                  echo '</p>';
                                                  echo '</div>';

                                                  return;
                                             }

                                             foreach ($form_data['fields'] as $field_id => $field) {
                                                  $field_type = !empty($field['type']) ? $field['type'] : '';

                                                  // Do not display some fields at all.
                                                  if (!is_field_can_be_displayed($field_type)) {
                                                       return;
                                                  }

                                                  $entry_field = !empty($entry_fields[$field_id]) ? $entry_fields[$field_id] : get_empty_entry_field_data($field);

                                                  $field_value = !empty($entry_field['value']) ? $entry_field['value'] : '';
                                                  $field_value = apply_filters('wpforms_html_field_value', wp_strip_all_tags($field_value), $entry_field, $form_data, 'entry-single');

                                                  $field_class  = !empty($field['type']) ? sanitize_html_class('wpforms-edit-entry-field-' . $field['type']) : '';
                                                  $field_class .= wpforms_is_empty_string($field_value) ? ' empty' : '';
                                                  $field_class .= !empty($field['required']) ? ' wpforms-entry-field-required' : '';

                                                  $field_style = $hide_empty && empty($entry_field['value']) ? 'display:none;' : '';

                                                  echo '<div class="wpforms-edit-entry-field ' . esc_attr($field_class) . '" style="' . esc_attr($field_style) . '">';

                                                  // Field label.
                                                  printf(
                                                       '<p class="wpforms-entry-field-name">%s</p>',
                                                       /* translators: %d - field ID. */
                                                       !empty($field['label']) ? esc_html(wp_strip_all_tags($field['label'])) : sprintf(esc_html__('Field ID #%d', 'wpforms'), (int) $field_id)
                                                  );

                                                  $field['css'] = '';

                                                  // Add properties to the field.
                                                  $field['properties'] = wpforms()->frontend->get_field_properties($field, $form_data);

                                                  $is_editable = (bool) apply_filters(
                                                       'wpforms_pro_admin_entries_edit_field_output_editable',
                                                       is_field_entries_editable($field['type']),
                                                       $field,
                                                       $entry_fields,
                                                       $form_data
                                                  );

                                                  // Field output.
                                                  if ($is_editable) {
                                                       wpforms()->frontend->field_container_open($field, $form_data);

                                                       $field_object = get_entries_edit_field_object($field['type']);

                                                       $field_object->field_display(
                                                            $entry_field,
                                                            $field,
                                                            $form_data
                                                       );

                                                       echo '</div>';
                                                  } else {
                                                       display_edit_form_field_non_editable($field_value);
                                                  }

                                                  echo '</div>';
                                             }
                                        }
                                        ?>

                                   </div>

                              </div>
                         </div>

                         <!-- Right column -->
                         <div id="postbox-container-1" class="postbox-container">
                              <?php echo printf(
                                   '<div id="publishing-action">
				<button class="button button-primary button-large wpforms-submit" id="wpforms-edit-entry-update">%s</button>
				<img src="%sassets/images/submit-spin.svg" class="wpforms-submit-spinner" style="display: none;">
			</div>',
                                   esc_html__('Update', 'wpforms'),
                                   esc_url(WPFORMS_PLUGIN_URL)
                              ); ?>
                         </div>

                         </form>
                    </div>

               </div>

          </div>

     </div>

     </div>
<?php
     wp_die();
}


function whe_ajax_process_errors($wp_form, $form_id, $form_data)
{

     $errors = isset($wp_form->errors[$form_id]) ? $wp_form->errors[$form_id] : array();

     $errors = apply_filters('wpforms_ajax_submit_errors', $errors, $form_id, $form_data);

     if (empty($errors)) {
          wp_send_json_error();
     }

     // General errors are errors that cannot be populated with jQuery Validate plugin.
     $general_errors = array_intersect_key($errors, array_flip(array('header', 'footer', 'recaptcha')));

     foreach ($general_errors as $key => $error) {
          ob_start();
          wpforms()->frontend->form_error($key, $error);
          $general_errors[$key] = ob_get_clean();
     }

     $fields = isset($form_data['fields']) ? $form_data['fields'] : array();

     // Get registered fields errors only.
     $field_errors = array_intersect_key($errors, $fields);

     // Transform field ids to field names for jQuery Validate plugin.
     foreach ($field_errors as $key => $error) {

          $name = $wp_form->ajax_error_field_name($fields[$key], $form_data, $error);
          if ($name) {
               $field_errors[$name] = $error;
          }

          unset($field_errors[$key]);
     }

     $response = array();

     if ($general_errors) {
          $response['errors']['general'] = $general_errors;
     }

     if ($field_errors) {
          $response['errors']['field'] = $field_errors;
     }

     $response = apply_filters('wpforms_ajax_submit_errors_response', $response, $form_id, $form_data);

     do_action('wpforms_ajax_submit_completed', $form_id, $response);

     wp_send_json_error($response);
}


function whe_load_edit_entries()
{
     @ini_set('display_errors', 1);

     $nonce = sanitize_text_field($_POST['nonce']);

     if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
          die('Busted!');
     }

     $form_id = sanitize_text_field($_POST['form_id']);
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




     ob_start();
     echo '<table class="wpforms-frontend-entries">';

     echo '<thead><tr>';

     // Loop through the form data so we can output form field names in
     // the table header.
     foreach ($form_fields as $form_field) {

          // Output the form field name/label.
          echo '<th>';
          echo esc_html(sanitize_text_field($form_field['label']));
          echo '</th>';
     }

     echo '</tr></thead>';

     echo '<tbody>';
     foreach ($entries as $entry) {
          $eid = $entry->entry_id;

          echo "<tr class='eid-$eid'>";
          // Entry field values are in JSON, so we need to decode.

          $entry_fields = json_decode($entry->fields, true);

          foreach ($form_fields as $form_field) {

               echo '<td>';
               foreach ($entry_fields as $entry_field) {
                    if (absint($entry_field['id']) === absint($form_field['id']) && $entry->user_id == get_current_user_id()) {
                         echo apply_filters('wpforms_html_field_value', wp_strip_all_tags($entry_field['value']), $entry_field, $form_data, 'entry-frontend-table');
                         break;
                    }
               }
               echo '</td>';
          }
          echo '</tr>';
     }
     echo '</tbody>';
     echo '</table>';
     $output = ob_get_clean();
     wp_die($output);
}

function whe_edit_entry()
{
     echo "";
}


function whe_delete_entry()
{
     echo "";
}
