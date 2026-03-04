<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adds the Donation Options metabox to the "donation" CPT
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'donation_amounts',
        __('Donation Options', 'donations-cpt'),
        'donations_cpt_admin_metabox_render',
        'donation',
        'normal',
        'default'
    );
});

/**
 * Render callback for the Donation Options metabox
 */
function donations_cpt_admin_metabox_render( WP_Post $post ){
    wp_nonce_field('donations_cpt_metabox', 'donations_cpt_nonce');

    // Load saved data
    $rows         = get_post_meta($post->ID, '_donation_rows_arr', true);
    $allow_custom = get_post_meta($post->ID, '_donation_allow_custom', true) === '1';
    $custom_label = get_post_meta($post->ID, '_donation_custom_label', true);

    if ( ! is_array($rows) ) {
        // Provide one empty row by default
        $rows = array(
            array('amount' => '', 'desc' => ''),
        );
    }

    ?>
    <p><?php esc_html_e('Enter your donation rows below. Use "Add row" to insert more.', 'donations-cpt'); ?></p>

    <table class="widefat striped dcpt-admin-amount-01" id="donation-rows-table">
      <thead>
        <tr>
          <th class="dcpt-admin-amount-02"><?php esc_html_e('Amount', 'donations-cpt'); ?></th>
          <th><?php esc_html_e('Description', 'donations-cpt'); ?></th>
          <th class="dcpt-admin-amount-03"><?php esc_html_e('Actions', 'donations-cpt'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $rows as $row ) :
            $amt  = isset($row['amount']) ? (string) $row['amount'] : '';
            $desc = isset($row['desc'])   ? (string) $row['desc']   : '';
        ?>
        <tr>
          <td>
  <input
    type="number"
    step="1"
    min="0"
    name="donation_rows_amount[]"
    value="<?php echo esc_attr( $amt ); ?>"
    placeholder="10"
    inputmode="numeric"
    pattern="^\d+$"
    class="widefat"
    oninput="this.value = this.value.split('.')[0].replace(/[^0-9]/g,'')"
  >
</td>
          <td>
            <input type="text"
                   name="donation_rows_desc[]"
                   value="<?php echo esc_attr($desc); ?>"
                   placeholder="<?php esc_attr_e('What this supports', 'donations-cpt'); ?>"
                   class="widefat">
          </td>
          <td>
            <button type="button" class="button link-delete donation-row-remove">
              <?php esc_html_e('Remove','donations-cpt'); ?>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3">
            <button type="button" class="button" id="donation-row-add">
              <?php esc_html_e('Add row','donations-cpt'); ?>
            </button>
          </td>
        </tr>
      </tfoot>
    </table>
<?php /* Row template + inline JS (self-contained) */ ?>
<script type="text/x-template" id="donation-row-template">
<tr>
  <td>
    <input
      type="number"
      step="1"
      min="0"
      name="donation_rows_amount[]"
      value=""
      placeholder="10"
      inputmode="numeric"
      pattern="^\d+$"
      class="widefat"
      oninput="this.value = this.value.split('.')[0].replace(/[^0-9]/g,'')"
    >
  </td>
  <td>
    <input type="text"
           name="donation_rows_desc[]"
           value=""
           placeholder="<?php echo esc_attr__('What this supports','donations-cpt'); ?>"
           class="widefat">
  </td>
  <td>
    <button type="button" class="button link-delete donation-row-remove">
      <?php echo esc_html__('Remove','donations-cpt'); ?>
    </button>
  </td>
</tr>
</script>

<script>
(function($){
  function dcptReady(fn){
    if (document.readyState === 'complete' || document.readyState === 'interactive') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  dcptReady(function(){
    var $table = $('#donation-rows-table');
    if (!$table.length) return;

    function addRow() {
      var tpl = $('#donation-row-template').html();
      if (!tpl) return;
      $table.find('tbody').append(tpl);
    }

    // Add row
    $(document).on('click', '#donation-row-add', function(e){
      e.preventDefault();
      addRow();
    });

    // Remove row (delegated)
    $(document).on('click', '.donation-row-remove', function(e){
      e.preventDefault();
      var $tbody = $table.find('tbody');
      $(this).closest('tr').remove();
      if ($tbody.children('tr').length === 0) addRow();
    });

    // Ensure at least one row
    if ($table.find('tbody tr').length === 0) addRow();
  });
})(jQuery);
</script>


    <p class="dcpt-admin-amount-04" style="margin-top:12px;">
      <label>
        <input type="checkbox" name="donation_allow_custom" value="1" <?php checked( $allow_custom, true ); ?>>
        <?php esc_html_e('Allow custom amount', 'donations-cpt'); ?>
      </label>
      &nbsp;&nbsp;
      <label>
        <?php esc_html_e('Custom amount label', 'donations-cpt'); ?>:
        <input type="text" name="donation_custom_label"
               value="<?php echo esc_attr( $custom_label ); ?>"
               placeholder="<?php esc_attr_e('Enter amount', 'donations-cpt'); ?>"
               class="regular-text">
      </label>
    </p>
    <?php
}

/**
 * Save handler for the Donation Options metabox
 */
add_action('save_post_donation', function( $post_id ){
    // Nonce / capability / autosave checks
    if ( ! isset($_POST['donations_cpt_nonce']) || ! wp_verify_nonce($_POST['donations_cpt_nonce'], 'donations_cpt_metabox') ) {
        return;
    }
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can('edit_post', $post_id) ) {
        return;
    }

    // Sanitize rows
    $amounts = isset($_POST['donation_rows_amount']) ? (array) $_POST['donation_rows_amount'] : array();
    $descs   = isset($_POST['donation_rows_desc'])   ? (array) $_POST['donation_rows_desc']   : array();

    $rows = array();
    foreach ( $amounts as $i => $amt ) {
        $amt_raw = trim( (string) $amt );
        $desc    = isset($descs[$i]) ? sanitize_text_field( $descs[$i] ) : '';

        if ( $amt_raw === '' || ! is_numeric( $amt_raw ) ) {
            continue;
        }

        $rows[] = array(
            'amount' => number_format( (float) $amt_raw, 2, '.', '' ),
            'desc'   => $desc,
        );
    }

    update_post_meta( $post_id, '_donation_rows_arr', $rows );

    // Flags / labels
    $allow_custom = ! empty( $_POST['donation_allow_custom'] ) ? '1' : '0';
    $custom_label = isset($_POST['donation_custom_label']) ? sanitize_text_field( $_POST['donation_custom_label'] ) : '';

    update_post_meta( $post_id, '_donation_allow_custom', $allow_custom );
    update_post_meta( $post_id, '_donation_custom_label', $custom_label );
});