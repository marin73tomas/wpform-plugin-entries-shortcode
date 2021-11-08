<?php


add_shortcode("wpform_edit_entries", "shortcode_whe_edit_entries");
add_shortcode("wpform_delete_entries", "shortcode_whe_delete_entries");

function shortcode_whe_edit_entries($args)
{

     if (empty($args['form_id'])) {
          echo "Error please insert a form id";
          return;
     }
?>
     <div class="whe-edit">
          <form action="">


          </form>
     </div>
<?php
     wp_localize_script('whe-edit', 'ajax_var', array(
          'url'    => admin_url('admin-ajax.php'),
          'nonce'  => wp_create_nonce('my-ajax-nonce'),
          'action' => 'whe_edit_entry',
          'form_id' => $args['form_id']
     ));
     wp_enqueue_script('whe-edit');
}

function shortcode_whe_delete_entries()
{
?>

<?php
     wp_enqueue_script('whe-delete');
}
