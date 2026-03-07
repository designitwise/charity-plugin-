<?php
// AJAX handler to return mini checkout billing details from WooCommerce session
add_action('wp_ajax_get_mini_billing_details', 'get_mini_billing_details');
add_action('wp_ajax_nopriv_get_mini_billing_details', 'get_mini_billing_details');
function get_mini_billing_details() {
    $user_id = get_current_user_id();
    if ($user_id) {
        $details = [
            'first_name' => get_user_meta($user_id, 'billing_first_name', true),
            'last_name'  => get_user_meta($user_id, 'billing_last_name', true),
            'email'      => get_user_meta($user_id, 'billing_email', true),
            'phone'      => get_user_meta($user_id, 'billing_phone', true),
        ];
    } else if (function_exists('WC') && WC()->session) {
        $details = [
            'first_name' => WC()->session->get('billing_first_name', ''),
            'last_name'  => WC()->session->get('billing_last_name', ''),
            'email'      => WC()->session->get('billing_email', ''),
            'phone'      => WC()->session->get('billing_phone', ''),
        ];
    } else {
        $details = [
            'first_name' => '',
            'last_name'  => '',
            'email'      => '',
            'phone'      => '',
        ];
    }
    wp_send_json_success($details);
}
