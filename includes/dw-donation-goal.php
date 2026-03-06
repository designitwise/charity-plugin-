<?php
if (!defined('ABSPATH')) exit;

/**
 * Donation Goal extension (auto-raised, product lock, refunds, cached)
 */

// ---------- Register meta ----------
add_action('init', function () {
    $cpt = 'donation';

    register_post_meta($cpt, 'dw_donation_goal', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => function ($value) {
            $value = preg_replace('/[^0-9.\-]/', '', (string)$value);
            return $value === '' ? '' : $value;
        },
        'auth_callback'     => function () { return current_user_can('edit_posts'); },
    ]);

    register_post_meta($cpt, 'dw_donation_goal_enabled', [
        'type'         => 'boolean',
        'single'       => true,
        'show_in_rest' => true,
        'default'      => false,
        'auth_callback'=> function () { return current_user_can('edit_posts'); },
    ]);

    register_post_meta($cpt, 'dw_donation_raised', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => function ($value) {
            $value = preg_replace('/[^0-9.\-]/', '', (string)$value);
            return $value === '' ? '' : $value;
        },
        'auth_callback'     => function () { return current_user_can('edit_posts'); },
    ]);
});

// ---------- Admin meta box ----------
add_action('add_meta_boxes', function () {
    $cpt = 'donation';
    add_meta_box(
        'dw_donation_goal_box',
        __('Donation Goal', 'dw'),
        function ($post) {
            wp_nonce_field('dw_donation_goal_save', 'dw_donation_goal_nonce');
            $enabled = (bool) get_post_meta($post->ID, 'dw_donation_goal_enabled', true);
            $goal    = get_post_meta($post->ID, 'dw_donation_goal', true);
            $raised  = get_post_meta($post->ID, 'dw_donation_raised', true);
            ?>
            <p>
                <label>
                    <input type="checkbox" name="dw_donation_goal_enabled" value="1" <?php checked($enabled); ?> />
                    <?php _e('Enable goal for this donation', 'dw'); ?>
                </label>
            </p>
            <p>
                <label for="dw_donation_goal"><strong><?php _e('Goal Amount', 'dw'); ?></strong></label><br>
                <input type="number" step="0.01" min="0" id="dw_donation_goal" name="dw_donation_goal"
                       value="<?php echo esc_attr($goal); ?>" style="width:100%;">
            </p>
            <p>
                <label for="dw_donation_raised"><strong><?php _e('Raised So Far (manual fallback)', 'dw'); ?></strong></label><br>
                <input type="number" step="0.01" min="0" id="dw_donation_raised" name="dw_donation_raised"
                       value="<?php echo esc_attr($raised); ?>" style="width:100%;">
                <small><?php _e('Usually auto-calculated from paid orders. This is only used if WooCommerce is unavailable.', 'dw'); ?></small>
            </p>
            <?php
        },
        $cpt,
        'normal',
        'high'
    );
});

// ---------- Save handler ----------
add_action('save_post_donation', function ($post_id) {
    if (!isset($_POST['dw_donation_goal_nonce']) || !wp_verify_nonce($_POST['dw_donation_goal_nonce'], 'dw_donation_goal_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    update_post_meta($post_id, 'dw_donation_goal_enabled', isset($_POST['dw_donation_goal_enabled']) ? 1 : 0);

    if (isset($_POST['dw_donation_goal'])) {
        $goal = preg_replace('/[^0-9.\-]/', '', (string)$_POST['dw_donation_goal']);
        update_post_meta($post_id, 'dw_donation_goal', $goal);
    }
    if (isset($_POST['dw_donation_raised'])) {
        $raised = preg_replace('/[^0-9.\-]/', '', (string)$_POST['dw_donation_raised']);
        update_post_meta($post_id, 'dw_donation_raised', $raised);
    }
});

// ---------- Currency helper ----------
if (!function_exists('dw_price')) {
    function dw_price($amount, $currency_symbol = '£') {
        if (function_exists('wc_price')) {
            return wc_price((float)$amount);
        }
        return $currency_symbol . number_format_i18n((float)$amount, 2);
    }
}

// ---------- Admin: Gutenberg sidebar script ----------
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'donation') return;

    // Correct paths: assets live in plugin root `assets/js/...`
    $script_path = plugin_dir_path(__DIR__ . '/../donations-cpt.php') . 'assets/js/donation-goal-sidebar.js';
    $script_url  = plugins_url('assets/js/donation-goal-sidebar.js', __DIR__ . '/../donations-cpt.php');
    if (!file_exists($script_path)) return;

    wp_enqueue_script(
        'dw-donation-goal-sidebar',
        $script_url,
        ['wp-plugins','wp-edit-post','wp-element','wp-components','wp-data'],
        filemtime($script_path),
        true
    );
});

// ===== AUTO-CALC: Raised from WooCommerce orders (cached) =====

// Which order statuses count as "paid" towards the goal?
if (!function_exists('dw_donation_paid_statuses')) {
    function dw_donation_paid_statuses() {
        return apply_filters('dw_donation_paid_statuses', ['processing', 'completed']);
    }
}

/**
 * Compute total raised for a donation by scanning paid orders.
 * Uses line items that carry meta 'donation_post' == $post_id.
 * Subtracts refunds tied to those items.
 */
if (!function_exists('dw_donation_calculate_raised_from_orders')) {
    function dw_donation_calculate_raised_from_orders($post_id) {
        if (!function_exists('wc_get_orders')) return null;
        $post_id = (int) $post_id;
        if ($post_id <= 0) return null;

        $donation_product_id = (int) get_option('donations_cpt_product_id', 0);
        $lock = apply_filters('dw_donation_product_lock_enabled', true); // default: lock to configured product

        $orders = wc_get_orders([
            'status' => dw_donation_paid_statuses(),
            'limit'  => -1,
            'return' => 'objects',
        ]);

        $total = 0.0;

        foreach ($orders as $order) {
            $order_item_totals = [];
            foreach ($order->get_items('line_item') as $item_id => $item) {
                $item_post_id = (int) $item->get_meta('donation_post', true);
                if ($item_post_id !== $post_id) continue;

                if ($lock) {
                    // If no donation product configured, skip counting entirely
                    if (!$donation_product_id) continue;
                    $pid = (int) $item->get_product_id();
                    if ($pid !== $donation_product_id) continue;
                }

                $line_total = (float) $item->get_total();
                $order_item_totals[$item_id] = $line_total;
                $total += $line_total;
            }

            if (!$order_item_totals) continue;

            foreach ($order->get_refunds() as $refund) {
                foreach ($refund->get_items('line_item') as $ref_item_id => $ref_item) {
                    $parent_id = $ref_item->get_meta('_refunded_item_id', true);
                    $parent_id = $parent_id ? (int) $parent_id : 0;
                    if ($parent_id && isset($order_item_totals[$parent_id])) {
                        $total -= abs((float) $ref_item->get_total());
                    }
                }
            }
        }

        if ($total < 0) $total = 0.0;
        return round($total, wc_get_price_decimals());
    }
}

/**
 * Get raised amount with caching.
 */
if (!function_exists('dw_donation_get_raised_auto')) {
    function dw_donation_get_raised_auto($post_id) {
        $post_id = (int) $post_id;
        $cached = get_post_meta($post_id, '_dw_donation_raised_auto', true);
        if ($cached !== '' && $cached !== null) {
            return (float) $cached;
        }
        $calc = dw_donation_calculate_raised_from_orders($post_id);
        if ($calc !== null) {
            update_post_meta($post_id, '_dw_donation_raised_auto', (string) $calc);
        }
        return (float) $calc;
    }
}

/**
 * Refresh cache for donations impacted by this order.
 */
if (!function_exists('dw_donation_touch_cache_for_order')) {
    function dw_donation_touch_cache_for_order($order_id) {
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $donation_ids = [];

        foreach ($order->get_items('line_item') as $item_id => $item) {
            $pid = (int) $item->get_meta('donation_post', true);
            if ($pid) $donation_ids[$pid] = true;
        }
        foreach ($order->get_refunds() as $refund) {
            foreach ($refund->get_items('line_item') as $ref_item_id => $ref_item) {
                $pid = (int) $ref_item->get_meta('donation_post', true);
                if ($pid) $donation_ids[$pid] = true;
            }
        }

        if (!$donation_ids) return;

        foreach (array_keys($donation_ids) as $donation_post_id) {
            $calc = dw_donation_calculate_raised_from_orders($donation_post_id);
            if ($calc !== null) {
                update_post_meta($donation_post_id, '_dw_donation_raised_auto', (string) $calc);
            }
        }
    }
}

// Hooks to refresh cache when orders change
add_action('woocommerce_order_status_changed', function($order_id, $old, $new){
    $paid = dw_donation_paid_statuses();
    if (in_array($old, $paid, true) || in_array($new, $paid, true)) {
        dw_donation_touch_cache_for_order($order_id);
    }
}, 10, 3);

add_action('woocommerce_refund_created', function($refund_id, $args){
    $refund = wc_get_order($refund_id);
    if (!$refund) return;
    $parent_id = $refund->get_parent_id();
    if ($parent_id) dw_donation_touch_cache_for_order($parent_id);
}, 10, 2);

add_action('before_delete_post', function($post_id){
    if (!function_exists('wc_get_order')) return;
    $order = wc_get_order($post_id);
    if ($order) dw_donation_touch_cache_for_order($post_id);
});

// ---------- Renderer (template tag) ----------
if (!function_exists('dw_render_donation_goal')) {
    function dw_render_donation_goal($post_id = null) {
        $post_id = $post_id ?: get_the_ID();
        $enabled = (bool) get_post_meta($post_id, 'dw_donation_goal_enabled', true);
        $goal    = (float) get_post_meta($post_id, 'dw_donation_goal', true);
        // Prefer automatic tally from orders; fall back to manual meta if auto isn't available
        $raised_auto = dw_donation_get_raised_auto($post_id);
        $raised_meta = (float) get_post_meta($post_id, 'dw_donation_raised', true);
        $raised      = is_numeric($raised_auto) ? (float) $raised_auto : (float) $raised_meta;

        if (!$enabled || $goal <= 0) return '';

        $pct   = $goal > 0 ? max(0, min(100, ($raised / $goal) * 100)) : 0;
        $pct_r = number_format_i18n($pct, 0);

        ob_start(); ?>
        <div class="donation-goal" role="group" aria-label="<?php esc_attr_e('Donation goal progress', 'dw'); ?>">
            <div class="donation-goal__meta">
                <span class="donation-goal__raised">
                    <?php printf( esc_html__('Raised: %s', 'dw'), wp_kses_post( dw_price($raised) ) ); ?>
                </span>
                <span class="donation-goal__goal">
                    <?php printf( esc_html__('Goal: %s', 'dw'), wp_kses_post( dw_price($goal) ) ); ?>
                </span>
            </div>
            <div class="donation-goal__bar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($pct_r); ?>" role="progressbar">
                <div class="donation-goal__fill" style="width: <?php echo esc_attr($pct_r); ?>%;"></div>
            </div>
            <div class="donation-goal__percent"><?php echo esc_html(sprintf(__('%s%% funded', 'dw'), $pct_r)); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// ---------- Shortcode ----------
add_shortcode('donation_goal', function ($atts) {
    $atts = shortcode_atts(['id' => null], $atts, 'donation_goal');
    $post_id = $atts['id'] ? (int)$atts['id'] : get_the_ID();
    return dw_render_donation_goal($post_id);
});

// ---------- Optional: admin columns ----------
add_filter('manage_donation_posts_columns', function ($cols) {
    $cols['dw_goal']    = __('Goal', 'dw');
    $cols['dw_enabled'] = __('Goal Enabled', 'dw');
    $cols['dw_auto']    = __('Raised (auto)', 'dw');
    return $cols;
});
add_action('manage_donation_posts_custom_column', function ($col, $post_id) {
    if ($col === 'dw_goal') {
        $goal = get_post_meta($post_id, 'dw_donation_goal', true);
        echo $goal !== '' ? esc_html(number_format_i18n((float)$goal, 2)) : '—';
    }
    if ($col === 'dw_enabled') {
        $enabled = (bool) get_post_meta($post_id, 'dw_donation_goal_enabled', true);
        echo $enabled ? '✓' : '—';
    }
    if ($col === 'dw_auto') {
        $auto = dw_donation_get_raised_auto($post_id);
        echo is_numeric($auto) ? esc_html(number_format_i18n((float)$auto, 2)) : '—';
    }
}, 10, 2);
