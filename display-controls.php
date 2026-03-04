<?php
// File: display-controls.php
if ( ! defined('ABSPATH') ) exit;

/**
 * Donations CPT – Display Controls (safe companion inside your main plugin)
 * - Adds Donations → Display settings (Forms per row / Forms per page)
 * - Registers [donation_buttons_auto] that renders the same form(s) as on Donation posts
 *   and reads the settings by default (overridable via shortcode attributes).
 *
 * NOTE: This code does not modify any existing hooks in your main plugin.
 */

/* ---------- Admin settings: Donations → Display ---------- */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=donation',
        __('Donation Display Settings','donations-cpt'),
        __('Display','donations-cpt'),
        'manage_options',
        'donations-cpt-display',
        function () {
            if ( ! current_user_can('manage_options') ) return;

            if ( isset($_POST['don_cpt_display_nonce']) && wp_verify_nonce($_POST['don_cpt_display_nonce'], 'don_cpt_display_save') ) {
                $per_row  = isset($_POST['donations_cpt_forms_per_row'])  ? max(1, min(6,   (int) $_POST['donations_cpt_forms_per_row']))  : 3;
                $per_page = isset($_POST['donations_cpt_forms_per_page']) ? max(1, min(100, (int) $_POST['donations_cpt_forms_per_page'])) : 9;

                update_option('donations_cpt_forms_per_row',  $per_row);
                update_option('donations_cpt_forms_per_page', $per_page);

                echo '<div  class="updated notice"><p >'.esc_html__('Display settings saved.','donations-cpt').'</p></div>';
            }

            $per_row  = (int) get_option('donations_cpt_forms_per_row', 3);
            $per_page = (int) get_option('donations_cpt_forms_per_page', 9);
            ?>
            <div  class="wrap">
                <h1 ><?php esc_html_e('Donation Display Settings','donations-cpt'); ?></h1>
                <form  method="post">
                    <?php wp_nonce_field('don_cpt_display_save','don_cpt_display_nonce'); ?>
                    <table  class="form-table" role="presentation">
                        <tr >
                            <th  scope="row">
                                <label  for="donations_cpt_forms_per_row"><?php esc_html_e('Forms per row','donations-cpt'); ?></label>
                            </th>
                            <td >
                                <select  name="donations_cpt_forms_per_row" id="donations_cpt_forms_per_row">
                                    <?php foreach ([1,2,3,4,5,6] as $n): ?>
                                        <option  value="<?php echo esc_attr($n); ?>" <?php selected($per_row, $n); ?>><?php echo esc_html($n); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p  class="description"><?php esc_html_e('How many donation forms to show per row when rendering grids.','donations-cpt'); ?></p>
                            </td>
                        </tr>
                        <tr >
                            <th  scope="row">
                                <label  for="donations_cpt_forms_per_page"><?php esc_html_e('Forms per page (mode=all)','donations-cpt'); ?></label>
                            </th>
                            <td >
                                <input  type="number" min="1" max="100" class="small-text"
                                       id="donations_cpt_forms_per_page"
                                       name="donations_cpt_forms_per_page"
                                       value="<?php echo esc_attr($per_page); ?>">
                                <p  class="description">
                                    <?php esc_html_e('Default number of donation forms to render when using mode="all". Can be overridden with count="N" on the shortcode.','donations-cpt'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Changes','donations-cpt')); ?>
                </form>
            </div>
            <?php
        }
    );
});

/* ---------- Admin notice if base shortcode missing (non-fatal) ---------- */
add_action('admin_notices', function () {
    if ( ! current_user_can('manage_options') ) return;
    if ( ! post_type_exists('donation') || ! shortcode_exists('donation_buttons') ) {
        echo '<div  class="notice notice-warning"><p >'
           . esc_html__('Display Controls are active, but the base Donations CPT and/or [donation_buttons] shortcode was not detected. This add-on will be idle until the base plugin is active.', 'donations-cpt')
           . '</p></div>';
    }
});

/* ---------- Universal shortcode: [donation_buttons_auto] ---------- */
add_action('init', function () {

    // Avoid duplicate registration if another copy exists.
    if ( shortcode_exists('donation_buttons_auto') ) {
        remove_shortcode('donation_buttons_auto');
    }

    add_shortcode('donation_buttons_auto', function ($atts) {

        $atts = shortcode_atts([
            'id'          => '',     // explicit donation post ID
            'donation_id' => '',     // alias for id
            'category'    => '',     // category slug for filtering (outside single donation)
            'mode'        => 'first',// first | all
            'count'       => '',     // if empty, default from settings
            'per_row'     => '',     // if empty, default from settings
        ], $atts, 'donation_buttons_auto');

        // Read global defaults from settings
        $opt_per_row  = (int) get_option('donations_cpt_forms_per_row', 3);
        $opt_per_page = (int) get_option('donations_cpt_forms_per_page', 9);

        $per_row  = ($atts['per_row'] !== '') ? max(1, min(6,   (int) $atts['per_row']))  : $opt_per_row;
        $per_page = ($atts['count']   !== '') ? max(1, min(100, (int) $atts['count']))    : $opt_per_page;

        // 1) Explicit ID via attribute or URL
        $explicit_id = 0;
        if ( ! empty($atts['id']) )          $explicit_id = (int) $atts['id'];
        if ( ! $explicit_id && ! empty($atts['donation_id']) ) $explicit_id = (int) $atts['donation_id'];
        if ( ! $explicit_id && ! empty($_GET['donation_id']) ) $explicit_id = (int) $_GET['donation_id'];

        if ( $explicit_id ) {
            return '<div class="donation-forms-grid dcpt-frontend-button-05">'
                 . do_shortcode('[donation_buttons id="'.(int)$explicit_id.'"]')
                 . '</div>';
        }

        // 2) Single Donation context → render that post's form
        if ( function_exists('is_singular') && is_singular('donation') ) {
            $qid = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
            if ( ! $qid ) $qid = (int) get_the_ID();
            if ( $qid ) {
                return '<div class="donation-forms-grid dcpt-frontend-button-05">'
                     . do_shortcode('[donation_buttons id="'.(int)$qid.'"]')
                     . '</div>';
            }
        }

        // 3) Resolve category (archive, attribute, or URL)
        $slug = '';
        if ( function_exists('is_category') && is_category() ) {
            $term = get_queried_object();
            if ( $term && ! empty($term->slug) ) $slug = $term->slug;
        }
        if ( ! $slug && ! empty($atts['category']) ) $slug = sanitize_title($atts['category']);
        if ( ! $slug && ! empty($_GET['cat_slug']) ) $slug = sanitize_title( wp_unslash($_GET['cat_slug']) );

        // 4) If still no slug, fallback to latest Donation (single form)
        if ( ! $slug ) {
            $latest = get_posts([
                'post_type'   => 'donation',
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby'     => 'date',
                'order'       => 'DESC',
                'fields'      => 'ids',
            ]);
            if ( ! empty($latest) ) {
                return '<div class="donation-forms-grid dcpt-frontend-button-05">'
                     . do_shortcode('[donation_buttons id="'.(int)$latest[0].'"]')
                     . '</div>';
            }
            return '<div  class="donation-buttons-auto notice">'.esc_html__('No donations available to render.', 'donations-cpt').'</div>';
        }

        // 5) Query Donations in that category
        $mode_all       = ( strtolower($atts['mode']) === 'all' );
        $posts_per_page = $mode_all ? $per_page : 1;

        $q = new WP_Query([
            'post_type'      => 'donation',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'tax_query'      => [[
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $slug,
            ]],
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        ob_start();
        echo '<div class="donation-forms-grid dcpt-frontend-button-05">';
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                echo '<div  class="donation-form-item">';
                echo do_shortcode('[donation_buttons id="'.(int) get_the_ID().'"]');
                echo '</div>';
            }
        } else {
            echo '<p  class="donation-buttons-auto__empty">'.esc_html__('No donations found in this category.', 'donations-cpt').'</p>';
        }
        echo '</div>';
        wp_reset_postdata();
        return ob_get_clean();
    });
});