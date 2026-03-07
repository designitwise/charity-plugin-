<?php
// AJAX handler for saving billing details from flyout
add_action('wp_ajax_save_flyout_billing', 'save_flyout_billing');
add_action('wp_ajax_nopriv_save_flyout_billing', 'save_flyout_billing');
function save_flyout_billing() {
    $first = sanitize_text_field($_POST['billing_first_name'] ?? '');
    $last = sanitize_text_field($_POST['billing_last_name'] ?? '');
    $email = sanitize_email($_POST['billing_email'] ?? '');
    $phone = sanitize_text_field($_POST['billing_phone'] ?? '');
    $user_id = get_current_user_id();
    if ($user_id) {
        update_user_meta($user_id, 'billing_first_name', $first);
        update_user_meta($user_id, 'billing_last_name', $last);
        update_user_meta($user_id, 'billing_email', $email);
        update_user_meta($user_id, 'billing_phone', $phone);
    } else if (function_exists('WC') && WC()->session) {
        WC()->session->set('billing_first_name', $first);
        WC()->session->set('billing_last_name', $last);
        WC()->session->set('billing_email', $email);
        WC()->session->set('billing_phone', $phone);
    }
    wp_send_json_success();
}
