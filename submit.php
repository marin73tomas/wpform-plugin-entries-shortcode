<?php

namespace WPForms\Pro\Admin\Entries;

use WPForms\Pro\Forms\Fields\Base\EntriesEdit;

class WPForms_Process_Custom
{

     /**
      * Store errors.
      *
      * @since 1.0.0
      *
      * @var array
      */
     public $errors;

     /**
      * Confirmation message.
      *
      * @var string
      */
     public $confirmation_message;

     /**
      * Current confirmation.
      *
      * @since 1.6.9
      *
      * @var array
      */
     private $confirmation;

     /**
      * Store formatted fields.
      *
      * @since 1.0.0
      *
      * @var array
      */
     public $fields;

     /**
      * Store the ID of a successful entry.
      *
      * @since 1.2.3
      *
      * @var int
      */
     public $entry_id = 0;

     /**
      * Form data and settings.
      *
      * @since 1.4.5
      *
      * @var array
      */
     public $form_data;

     /**
      * If a valid return has was processed.
      *
      * @since 1.4.5
      *
      * @var bool
      */
     public $valid_hash = false;

     /**
      * Primary class constructor.
      *

      * @since 1.0.0
      */
     public function __construct()
     {

          add_action('wp', array($this, 'listen'));

          add_action('wp_ajax_whe_ajax_submit', array($this, 'whe_ajax_submit'));
          add_action('wp_ajax_nopriv_whe_ajax_submit', array($this, 'whe_ajax_submit'));
     }

     /**
      * Listen to see if this is a return callback or a posted form entry.
      *
      * @since 1.0.0
      */
     public function listen()
     {

          // Catch the post_max_size overflow.
          if ($this->post_max_size_overflow()) {
               return;
          }

          if (!empty($_GET['wpforms_return'])) { // phpcs:ignore
               $this->entry_confirmation_redirect('', $_GET['wpforms_return']); // phpcs:ignore
          }

          if (!empty($_POST['wpforms']['id'])) { // phpcs:ignore
               $this->process(stripslashes_deep($_POST['wpforms'])); // phpcs:ignore

               $form_id = wp_unslash($_POST['wpforms']['id']);
               if (wpforms_is_amp()) {
                    // Send 400 Bad Request when there are errors.
                    if (!empty($this->errors[$form_id])) {
                         $message = $this->errors[$form_id]['header'];
                         if (!empty($this->errors[$form_id]['footer'])) {
                              $message .= ' ' . $this->errors[$form_id]['footer'];
                         }
                         wp_send_json(
                              array(
                                   'message' => $message,
                              ),
                              400
                         );
                    } else {
                         wp_send_json(
                              array(
                                   'message' => $this->get_confirmation_message($this->form_data, $this->fields, $this->entry_id),
                              ),
                              200
                         );
                    }
               }
          }
     }

     /**
      * Process the form entry.
      *
      * @since 1.0.0
      * @since 1.6.4 Added hCaptcha support.
      *
      * @param array $entry Form submission raw data ($_POST).
      */

     private function process($entry)
     {

          // Setup variables.
          $this->fields = [];
          $this->entry  = wpforms()->entry->get($this->entry_id);
          $form_id      = $this->form_id;
          $this->form   = wpforms()->form->get($this->form_id, ['cap' => 'edit_entries_form_single']);

          // Validate form is real.
          if (!$this->form) {
               $this->errors['header'] = esc_html__('Invalid form.', 'wpforms');
               $this->process_errors();
               return;
          }

          // Validate entry is real.
          if (!$this->entry) {
               $this->errors['header'] = esc_html__('Invalid entry.', 'wpforms');
               $this->process_errors();
               return;
          }

          // Formatted form data for hooks.
          $this->form_data = apply_filters('wpforms_pro_admin_entries_edit_process_before_form_data', wpforms_decode($this->form->post_content), $entry);

          $this->form_data['created'] = $this->form->post_date;

          // Existing entry fields data.
          $this->entry_fields = apply_filters('wpforms_pro_admin_entries_edit_existing_entry_fields', wpforms_decode($this->entry->fields), $this->entry, $this->form_data);

          // Pre-process/validate hooks and filter.
          // Data is not validated or cleaned yet so use with caution.
          $entry = apply_filters('wpforms_pro_admin_entries_edit_process_before_filter', $entry, $this->form_data);

          do_action('wpforms_pro_admin_entries_edit_process_before', $entry, $this->form_data);
          do_action("wpforms_pro_admin_entries_edit_process_before_{$this->form_id}", $entry, $this->form_data);

          // Validate fields.
          $this->process_fields($entry, 'validate');

          // Validation errors.
          if (!empty(wpforms()->process->errors[$form_id])) {
               $this->errors = wpforms()->process->errors[$form_id];
               if (empty($this->errors['header'])) {
                    $this->errors['header'] = esc_html__('Entry has not been saved, please see the fields errors.', 'wpforms');
               }
               $this->process_errors();
               exit;
          }

          // Format fields.
          $this->process_fields($entry, 'format');

          // This hook is for internal purposes and should not be leveraged.
          do_action('wpforms_pro_admin_entries_edit_process_format_after', $this->form_data);

          // Entries edit process hooks/filter.
          $this->fields = apply_filters('wpforms_pro_admin_entries_edit_process_filter', wpforms()->process->fields, $entry, $this->form_data);

          do_action('wpforms_pro_admin_entries_edit_process', $this->fields, $entry, $this->form_data);
          do_action("wpforms_pro_admin_entries_edit_process_{$form_id}", $this->fields, $entry, $this->form_data);

          $this->fields = apply_filters('wpforms_pro_admin_entries_edit_process_after_filter', $this->fields, $entry, $this->form_data);

          // Success - update data and send success.
          $this->process_update();
     }
     private function add_entry_meta($message)
     {

          // Add record to entry meta.
          wpforms()->entry_meta->add(
               [
                    'entry_id' => (int) $this->entry_id,
                    'form_id'  => (int) $this->form_id,
                    'user_id'  => get_current_user_id(),
                    'type'     => 'log',
                    'data'     => wpautop(sprintf('<em>%s</em>', esc_html($message))),
               ],
               'entry_meta'
          );
     }
     private function process_update_fields_data()
     {

          $updated_fields = [];

          if (!is_array($this->fields)) {
               return $updated_fields;
          }

          // Get saved fields data from DB.
          $entry_fields_obj = wpforms()->entry_fields;
          $dbdata_result    = $entry_fields_obj->get_fields(['entry_id' => $this->entry_id]);
          $dbdata_fields    = [];
          if (!empty($dbdata_result)) {
               $dbdata_fields = array_combine(wp_list_pluck($dbdata_result, 'field_id'), $dbdata_result);
               $dbdata_fields = array_map('get_object_vars', $dbdata_fields);
          }

          $this->date_modified = current_time('Y-m-d H:i:s');

          foreach ($this->fields as $field) {
               $save_field          = apply_filters('wpforms_entry_save_fields', $field, $this->form_data, $this->entry_id);
               $field_id            = $save_field['id'];
               $field_type          = empty($save_field['type']) ? '' : $save_field['type'];
               $save_field['value'] = empty($save_field['value']) ? '' : (string) $save_field['value'];
               $dbdata_value_exist  = isset($dbdata_fields[$field_id]['value']);

               // Process the field only if value was changed or not existed in DB at all. Also check if field is editable.
               if (
                    !$this->is_field_entries_editable($field_type) ||
                    ($dbdata_value_exist &&
                         isset($save_field['value']) &&
                         (string) $dbdata_fields[$field_id]['value'] === $save_field['value'])
               ) {
                    continue;
               }

               if ($dbdata_value_exist) {
                    // Update field data in DB.
                    $entry_fields_obj->update(
                         (int) $dbdata_fields[$field_id]['id'],
                         [
                              'value' => $save_field['value'],
                              'date'  => $this->date_modified,
                         ],
                         'id',
                         'edit_entry'
                    );
               } else {
                    // Add field data to DB.
                    $entry_fields_obj->add(
                         [
                              'entry_id' => $this->entry_id,
                              'form_id'  => (int) $this->form_data['id'],
                              'field_id' => (int) $field_id,
                              'value'    => $save_field['value'],
                              'date'     => $this->date_modified,
                         ]
                    );
               }
               $updated_fields[$field_id] = $field;
          }

          return $updated_fields;
     }
     private function get_updated_entry_fields($updated_fields)
     {

          if (empty($updated_fields)) {
               return $this->entry_fields;
          }

          $result_fields = [];
          $form_fields   = !empty($this->form_data['fields']) ? $this->form_data['fields'] : [];

          foreach ($form_fields as $field_id => $field) {
               $entry_field = isset($this->entry_fields[$field_id]) ?
                    $this->entry_fields[$field_id] :
                    $this->get_empty_entry_field_data($field);

               $result_fields[$field_id] = isset($updated_fields[$field_id]) ? $updated_fields[$field_id] : $entry_field;
          }
          return $result_fields;
     }
     private function process_update()
     {

          // Update entry fields.
          $updated_fields = $this->process_update_fields_data();

          // Silently return success if no changes performed.
          if (empty($updated_fields)) {
               wp_send_json_success();
          }

          // Update entry.
          $entry_data = [
               'fields'        => wp_json_encode($this->get_updated_entry_fields($updated_fields)),
               'date_modified' => $this->date_modified,
          ];
          wpforms()->entry->update($this->entry_id, $entry_data, '', 'edit_entry', ['cap' => 'edit_entry_single']);

          // Add record to entry meta.
          $this->add_entry_meta(esc_html__('Entry edited.', 'wpforms'));

          $removed_files = \WPForms_Field_File_Upload::delete_uploaded_files_from_entry($this->entry_id, $updated_fields, $this->entry_fields);

          array_map([$this, 'add_removed_file_meta'], $removed_files);

          $response = [
               'modified' => esc_html(date_i18n(get_option('date_format') . ' @ ' . get_option('time_format'), strtotime($this->date_modified) + (get_option('gmt_offset') * 3600))),
          ];

          do_action('wpforms_pro_admin_entries_edit_submit_completed', $this->form_data, $response, $updated_fields, $this->entry);

          wp_send_json_success($response);
     }
     private function is_field_entries_editable($type)
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
     private function process_fields($entry, $action = 'validate')
     {

          if (empty($this->form_data['fields'])) {
               return;
          }

          $form_data = $this->form_data;

          $action = empty($action) ? 'validate' : $action;

          foreach ((array) $form_data['fields']  as $field_properties) {

               if (!$this->is_field_entries_editable($field_properties['type'])) {
                    continue;
               }

               $field_id     = isset($field_properties['id']) ? $field_properties['id'] : '0';
               $field_type   = !empty($field_properties['type']) ? $field_properties['type'] : '';
               $field_submit = isset($entry['fields'][$field_id]) ? $entry['fields'][$field_id] : '';
               $field_data   = !empty($this->entry_fields[$field_id]) ? $this->entry_fields[$field_id] : $this->get_empty_entry_field_data($field_properties);

               if ($action === 'validate') {

                    // Some fields can be `required` but have an empty value because the field is hidden by CL on the frontend.
                    // For cases like this we should allow empty value even for the `required` fields.
                    if (
                         !empty($form_data['fields'][$field_id]['required']) &&
                         (!isset($field_data['value']) ||
                              (string) $field_data['value'] === '')
                    ) {
                         unset($form_data['fields'][$field_id]['required']);
                    }
               }

               if ($action === 'validate' || $action === 'format') {
                    $this->get_entries_edit_field_object($field_type)->$action($field_id, $field_submit, $field_data, $form_data);
               }
          }
     }
     public function get_empty_entry_field_data($field_properties)
     {

          return [
               'name'      => !empty($field_properties['label']) ? $field_properties['label'] : '',
               'value'     => '',
               'value_raw' => '',
               'id'        => !empty($field_properties['id']) ? $field_properties['id'] : '',
               'type'      => !empty($field_properties['type']) ? $field_properties['type'] : '',
          ];
     }
     private function get_entries_edit_field_object($type)
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
     /**
      * Check if combined upload size exceeds allowed maximum.
      *
      * @since 1.6.0
      *
      * @param \WP_Post $form Form post object.
      */
     public function validate_combined_upload_size($form)
     {

          $form_id = (int) $form->ID;
          $upload_fields = wpforms_get_form_fields($form, array('file-upload'));

          if (!empty($upload_fields) && !empty($_FILES)) {

               // Get $_FILES keys generated by WPForms only.
               $files_keys = preg_filter('/^/', 'wpforms_' . $form_id . '_', array_keys($upload_fields));

               // Filter uploads without errors. Individual errors are handled by WPForms_Field_File_Upload class.
               $files = wp_list_filter(wp_array_slice_assoc($_FILES, $files_keys), array('error' => 0));
               $files_size = array_sum(wp_list_pluck($files, 'size'));
               $files_size_max = wpforms_max_upload(true);

               if ($files_size > $files_size_max) {

                    // Add new header error preserving previous ones.
                    $this->errors[$form_id]['header'] = !empty($this->errors[$form_id]['header']) ? $this->errors[$form_id]['header'] . '<br>' : '';
                    $this->errors[$form_id]['header'] .= esc_html__('Uploaded files combined size exceeds allowed maximum.', 'wpforms-lite');
               }
          }
     }

     /**
      * Validate the form return hash.
      *
      * @since 1.0.0
      *
      * @param string $hash Base64-encoded hash of form and entry IDs.
      *
      * @return array|false False for invalid or form id.
      */
     public function validate_return_hash($hash = '')
     {

          $query_args = base64_decode($hash);

          parse_str($query_args, $output);

          // Verify hash matches.
          if (wp_hash($output['form_id'] . ',' . $output['entry_id']) !== $output['hash']) {
               return false;
          }

          // Get lead and verify it is attached to the form we received with it.
          $entry = wpforms()->entry->get($output['entry_id'], ['cap' => false]);

          if (empty($entry->form_id)) {
               return false;
          }

          if ($output['form_id'] !== $entry->form_id) {
               return false;
          }

          return array(
               'form_id' => absint($output['form_id']),
               'entry_id' => absint($output['form_id']),
               'fields' => null !== $entry && isset($entry->fields) ? $entry->fields : array(),
          );
     }

     /**
      * Check if the confirmation data are valid.
      *
      * @since 1.6.4
      *
      * @param array $data The confirmation data.
      *
      * @return bool
      */
     protected function is_valid_confirmation($data)
     {

          if (empty($data['type'])) {
               return false;
          }

          // Confirmation type: redirect, page or message.
          $type = $data['type'];

          return isset($data[$type]) && !wpforms_is_empty_string($data[$type]);
     }

     /**
      * Redirect user to a page or URL specified in the form confirmation settings.
      *
      * @since 1.0.0
      *
      * @param array $form_data Form data and settings.
      * @param string $hash Base64-encoded hash of form and entry IDs.
      */
     public function entry_confirmation_redirect($form_data = array(), $hash = '')
     {

          // Maybe process return hash.
          if (!empty($hash)) {

               $hash_data = $this->validate_return_hash($hash);

               if (!$hash_data || !is_array($hash_data)) {
                    return;
               }

               $this->valid_hash = true;
               $this->entry_id = absint($hash_data['entry_id']);
               $this->fields = json_decode($hash_data['fields'], true);
               $this->form_data = wpforms()->form->get(
                    absint($hash_data['form_id']),
                    array(
                         'content_only' => true,
                    )
               );
          } else {

               $this->form_data = $form_data;
          }

          // Backward compatibility.
          if (empty($this->form_data['settings']['confirmations'])) {
               $this->form_data['settings']['confirmations'][1]['type'] = !empty($this->form_data['settings']['confirmation_type']) ? $this->form_data['settings']['confirmation_type'] : 'message';
               $this->form_data['settings']['confirmations'][1]['message'] = !empty($this->form_data['settings']['confirmation_message']) ? $this->form_data['settings']['confirmation_message'] : esc_html__('Thanks for contacting us! We will be in touch with you shortly.', 'wpforms-lite');
               $this->form_data['settings']['confirmations'][1]['message_scroll'] = !empty($this->form_data['settings']['confirmation_message_scroll']) ? $this->form_data['settings']['confirmation_message_scroll'] : 1;
               $this->form_data['settings']['confirmations'][1]['page'] = !empty($this->form_data['settings']['confirmation_page']) ? $this->form_data['settings']['confirmation_page'] : '';
               $this->form_data['settings']['confirmations'][1]['redirect'] = !empty($this->form_data['settings']['confirmation_redirect']) ? $this->form_data['settings']['confirmation_redirect'] : '';
          }

          if (empty($this->form_data['settings']['confirmations']) || !is_array($this->form_data['settings']['confirmations'])) {
               return;
          }

          $confirmations = $this->form_data['settings']['confirmations'];

          // Reverse sort confirmations by id to process newer ones first.
          krsort($confirmations);

          $default_confirmation_key = min(array_keys($confirmations));

          foreach ($confirmations as $confirmation_id => $confirmation) {
               // Last confirmation should execute in any case.
               if ($default_confirmation_key === $confirmation_id) {
                    break;
               }

               if (!$this->is_valid_confirmation($confirmation)) {
                    continue;
               }

               $process_confirmation = apply_filters('wpforms_entry_confirmation_process', true, $this->fields, $form_data, $confirmation_id);
               if ($process_confirmation) {
                    break;
               }
          }

          $url = '';
          // Redirect if needed, to either a page or URL, after form processing.
          if (!empty($confirmations[$confirmation_id]['type']) && 'message' !== $confirmations[$confirmation_id]['type']) {

               if ('redirect' === $confirmations[$confirmation_id]['type']) {
                    add_filter('wpforms_field_smart_tag_value', 'rawurlencode');
                    $url = apply_filters('wpforms_process_Custom_smart_tags', $confirmations[$confirmation_id]['redirect'], $this->form_data, $this->fields, $this->entry_id);
               }

               if ('page' === $confirmations[$confirmation_id]['type']) {
                    $url = get_permalink((int) $confirmations[$confirmation_id]['page']);
               }
          }

          if (!empty($url)) {
               $url = apply_filters('wpforms_process_Custom_redirect_url', $url, $this->form_data['id'], $this->fields, $this->form_data, $this->entry_id);
               if (wpforms_is_amp()) {
                    /** This filter is documented in wp-includes/pluggable.php */
                    $url = apply_filters('wp_redirect', $url, 302);
                    $url = wp_sanitize_redirect($url);
                    header(sprintf('AMP-Redirect-To: %s', $url));
                    header('Access-Control-Expose-Headers: AMP-Redirect-To', false);
                    wp_send_json(
                         array(
                              'message' => __('Redirecting…', 'wpforms-lite'),
                              'redirecting' => true,
                         ),
                         200
                    );
               } else {
                    wp_redirect(esc_url_raw($url)); // phpcs:ignore
               }
               do_action('wpforms_process_Custom_redirect', $this->form_data['id']);
               do_action("wpforms_process_Custom_redirect_{$this->form_data['id']}", $this->form_data['id']);
               exit;
          }

          // Pass a message to a frontend if no redirection happened.
          if (!empty($confirmations[$confirmation_id]['type']) && 'message' === $confirmations[$confirmation_id]['type']) {
               $this->confirmation = $confirmations[$confirmation_id];
               $this->confirmation_message = $confirmations[$confirmation_id]['message'];

               if (!empty($confirmations[$confirmation_id]['message_scroll'])) {
                    wpforms()->frontend->confirmation_message_scroll = true;
               }
          }
     }

     /**
      * Get confirmation message.
      *
      * @since 1.5.3
      *
      * @param array $form_data Form data and settings.
      * @param array $fields Sanitized field data.
      * @param int $entry_id Entry id.
      *
      * @return string Confirmation message.
      */
     public function get_confirmation_message($form_data, $fields, $entry_id)
     {

          if (empty($this->confirmation_message)) {
               return '';
          }

          $confirmation_message = apply_filters('wpforms_process_Custom_smart_tags', $this->confirmation_message, $form_data, $fields, $entry_id);
          $confirmation_message = apply_filters('wpforms_frontend_confirmation_message', wpautop($confirmation_message), $form_data, $fields, $entry_id);

          return $confirmation_message;
     }

     /**
      * Get current confirmation.
      *
      * @since 1.6.9
      *
      * @return array
      */
     public function get_current_confirmation()
     {

          return !empty($this->confirmation) ? $this->confirmation : [];
     }

     /**
      * Catch the post_max_size overflow.
      *
      * @since 1.5.2
      *
      * @return bool
      */
     public function post_max_size_overflow()
     {

          if (empty($_SERVER['CONTENT_LENGTH']) || empty($_GET['wpforms_form_id'])) { // phpcs:ignore
               return false;
          }

          $form_id = (int) $_GET['wpforms_form_id'];
          $total_size = (int) $_SERVER['CONTENT_LENGTH'];
          $post_max_size = wpforms_size_to_bytes(ini_get('post_max_size'));

          if (!($total_size > $post_max_size && empty($_POST) && $form_id > 0)) {
               return false;
          }

          $total_size = number_format($total_size / 1048576, 3);
          $post_max_size = number_format($post_max_size / 1048576, 3);
          $error_msg = esc_html__('Form has not been submitted, please see the errors below.', 'wpforms-lite');
          $error_msg .= '<br>' . esc_html__('The total size of the selected files {totalSize} Mb exceeds the allowed limit {maxSize} Mb.', 'wpforms-lite');
          $error_msg = str_replace('{totalSize}', $total_size, $error_msg);
          $error_msg = str_replace('{maxSize}', $post_max_size, $error_msg);

          $this->errors[$form_id]['header'] = $error_msg;

          return true;
     }

     /**
      * Send entry email notifications.
      *
      * @since 1.0.0
      *
      * @param array $fields List of fields.
      * @param array $entry Submitted form entry.
      * @param array $form_data Form data and settings.
      * @param int $entry_id Saved entry id.
      * @param string $context In which context this email is sent.
      */
     public function entry_email($fields, $entry, $form_data, $entry_id, $context = '')
     {

          // Check that the form was configured for email notifications.
          if (empty($form_data['settings']['notification_enable'])) {
               return;
          }

          // Provide the opportunity to override via a filter.
          if (!apply_filters('wpforms_entry_email', true, $fields, $entry, $form_data)) {
               return;
          }

          // Make sure we have and entry id.
          if (empty($this->entry_id)) {
               $this->entry_id = (int) $entry_id;
          }

          $fields = apply_filters('wpforms_entry_email_data', $fields, $entry, $form_data);

          // Backwards compatibility for notifications before v1.4.3.
          if (empty($form_data['settings']['notifications'])) {
               $notifications[1] = array(
                    'email' => $form_data['settings']['notification_email'],
                    'subject' => $form_data['settings']['notification_subject'],
                    'sender_name' => $form_data['settings']['notification_fromname'],
                    'sender_address' => $form_data['settings']['notification_fromaddress'],
                    'replyto' => $form_data['settings']['notification_replyto'],
                    'message' => '{all_fields}',
               );
          } else {
               $notifications = $form_data['settings']['notifications'];
          }

          foreach ($notifications as $notification_id => $notification) :

               if (empty($notification['email'])) {
                    continue;
               }

               $process_email = apply_filters('wpforms_entry_email_process', true, $fields, $form_data, $notification_id, $context);

               if (!$process_email) {
                    continue;
               }

               $email = array();

               // Setup email properties.
               /* translators: %s - form name. */
               $email['subject'] = !empty($notification['subject']) ? $notification['subject'] : sprintf(esc_html__('New %s Entry', 'wpforms-lite'), $form_data['settings']['form_title']);
               $email['address'] = explode(',', apply_filters('wpforms_process_Custom_smart_tags', $notification['email'], $form_data, $fields, $this->entry_id));
               $email['address'] = array_map('sanitize_email', $email['address']);
               $email['sender_address'] = !empty($notification['sender_address']) ? $notification['sender_address'] : get_option('admin_email');
               $email['sender_name'] = !empty($notification['sender_name']) ? $notification['sender_name'] : get_bloginfo('name');
               $email['replyto'] = !empty($notification['replyto']) ? $notification['replyto'] : false;
               $email['message'] = !empty($notification['message']) ? $notification['message'] : '{all_fields}';
               $email = apply_filters('wpforms_entry_email_atts', $email, $fields, $entry, $form_data, $notification_id);

               // Create new email.
               $emails = new \WPForms_WP_Emails();
               $emails->__set('form_data', $form_data);
               $emails->__set('fields', $fields);
               $emails->__set('notification_id', $notification_id);
               $emails->__set('entry_id', $this->entry_id);
               $emails->__set('from_name', $email['sender_name']);
               $emails->__set('from_address', $email['sender_address']);
               $emails->__set('reply_to', $email['replyto']);

               // Maybe include CC.
               if (!empty($notification['carboncopy']) && wpforms_setting('email-carbon-copy', false)) {
                    $emails->__set('cc', $notification['carboncopy']);
               }

               $emails = apply_filters('wpforms_entry_email_before_send', $emails);

               // Go.
               foreach ($email['address'] as $address) {
                    $emails->send(trim($address), $email['subject'], $email['message']);
               }
          endforeach;
     }

     /**
      * Save entry to database.
      *
      * @since 1.0.0
      *
      * @param array $fields List of form fields.
      * @param array $entry User submitted data.
      * @param int $form_id Form ID.
      * @param array $form_data Prepared form settings.
      *
      * @return int
      */
     public function entry_save($fields, $entry, $form_id, $form_data = array())
     {

          do_action('wpforms_process_Custom_entry_save', $fields, $entry, $form_id, $form_data);

          return $this->entry_id;
     }

     /**
      * Process AJAX form submit.
      *
      * @since 1.5.3
      */
     private function process_errors()
     {

          $errors = $this->errors;

          if (empty($errors)) {
               wp_send_json_error();
          }

          $fields       = isset($this->form_data['fields']) ? $this->form_data['fields'] : [];
          $field_errors = array_intersect_key($errors, $fields);

          $response = [];

          $response['errors']['general'] = !empty($errors['header']) ? wpautop(esc_html($errors['header'])) : '';
          $response['errors']['field']   = !empty($field_errors) ? $field_errors : [];

          $response = apply_filters('wpforms_pro_admin_entries_edit_submit_errors_response', $response, $this->form_data);

          do_action('wpforms_pro_admin_entries_edit_submit_completed', $this->form_data, $response, [], $this->entry);

          wp_send_json_error($response);
     }
     public function whe_ajax_submit()
     {

          echo json_encode("culo");

          $this->form_id  = !empty($_POST['wpforms']['id']) ? (int) $_POST['wpforms']['id'] : 0;
          $this->entry_id = !empty($_POST['wpforms']['entry_id']) ? (int) $_POST['wpforms']['entry_id'] : 0;
          $this->errors   = [];

          if (empty($this->form_id)) {
               $this->errors['header'] = esc_html__('Invalid form.', 'wpforms');
          }

          if (empty($this->entry_id)) {
               $this->errors['header'] = esc_html__('Invalid Entry.', 'wpforms');
          }

          if (empty($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'wpforms-entry-edit-' . $this->form_id . '-' . $this->entry_id)) {
               $this->errors['header'] = esc_html__('You do not have permission to perform this action.', 'wpforms');
          }

          if (!empty($this->errors['header'])) {
               $this->process_errors();
          }

          // Hook for add-ons.
          do_action('wpforms_pro_admin_entries_edit_submit_before_processing', $this->form_id, $this->entry_id);

          // Process the data.
          $this->process(stripslashes_deep(wp_unslash($_POST['wpforms']))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

     }

     /**
      * Process AJAX errors.
      *
      * @since 1.5.3
      * @todo This should be re-used/combined for AMP verify-xhr requests.
      *
      * @param int $form_id Form ID.
      * @param array $form_data Form data and settings.
      */
     protected function ajax_process_errors($form_id, $form_data)
     {

          $errors = isset($this->errors[$form_id]) ? $this->errors[$form_id] : array();

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

               $name = $this->ajax_error_field_name($fields[$key], $form_data, $error);
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

     /**
      * Get field name for ajax error message.
      *
      * @since 1.6.3
      *
      * @param array $field Field settings.
      * @param array $form_data Form data and settings.
      * @param string $error Error message.
      *
      * @return string
      */
     private function ajax_error_field_name($field, $form_data, $error)
     {

          $props = wpforms()->frontend->get_field_properties($field, $form_data);

          return apply_filters('wpforms_process_Custom_ajax_error_field_name', '', $field, $props, $error);
     }

     /**
      * Process AJAX redirect.
      *
      * @since 1.5.3
      *
      * @param string $url Redirect URL.
      */
     public function ajax_process_redirect($url)
     {

          $form_id = isset($_POST['wpforms']['id']) ? absint($_POST['wpforms']['id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification

          if (empty($form_id)) {
               wp_send_json_error();
          }

          $response = array(
               'form_id' => $form_id,
               'redirect_url' => $url,
          );

          $response = apply_filters('wpforms_ajax_submit_redirect', $response, $form_id, $url);

          do_action('wpforms_ajax_submit_completed', $form_id, $response);

          wp_send_json_success($response);
     }
}
new WPForms_Process_Custom();
