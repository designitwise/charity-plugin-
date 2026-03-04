<?php
/**
 * Plugin Name: Donations CPT to Woo Checkout (Instant Refresh)
 * Description: Donation CPT with 3-column cards + single Donate button, placement controls, shortcode, theme-compat display options, and instant totals refresh.
 * Version: 2.5.1
 * Author: ChatGPT
 */

if ( ! defined('ABSPATH') ) exit;

/** Optional: safely include the flycart (no fatal if missing) */
$dw_flycart_path = plugin_dir_path(__FILE__) . 'dw-flycart.php';
if ( file_exists( $dw_flycart_path ) ) {
  require_once $dw_flycart_path;
} else {
  error_log('DW Flycart: dw-flycart.php not found in plugin root.');
}

/** Bootstrap (if you want it) */
add_action( 'wp_enqueue_scripts', function() {
  // Bootstrap CSS
  wp_enqueue_style(
    'bootstrap-cdn',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
    array(),
    '5.3.8'
  );
  wp_style_add_data( 'bootstrap-cdn', 'integrity', 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB' );
  wp_style_add_data( 'bootstrap-cdn', 'crossorigin', 'anonymous' );

  // Bootstrap JS bundle (includes Popper)
  wp_enqueue_script(
    'bootstrap-bundle-cdn',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
    array(),
    '5.3.8',
    true
  );
  wp_script_add_data( 'bootstrap-bundle-cdn', 'integrity', 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI' );
  wp_script_add_data( 'bootstrap-bundle-cdn', 'crossorigin', 'anonymous' );
});

/** Frontend CSS */
if ( ! function_exists( 'donations_cpt_enqueue_frontend' ) ) {
  function donations_cpt_enqueue_frontend() {
    $base = plugin_dir_url( __FILE__ ) . 'assets/';
    if ( file_exists( plugin_dir_path( __FILE__ ) . 'assets/css/style.css' ) ) {
      wp_register_style( 'donations-cpt', $base . 'css/style.css', array(), '1.2.0' );
      wp_enqueue_style( 'donations-cpt' );
    }
  }
  add_action( 'wp_enqueue_scripts', 'donations_cpt_enqueue_frontend' );
}

/** Optional goal widget CSS (if present) */
if ( file_exists(__DIR__ . '/includes/dw-donation-goal.php') ) {
  require_once __DIR__ . '/includes/dw-donation-goal.php';
}
add_action('wp_enqueue_scripts', function () {
  $path = plugin_dir_path(__FILE__) . 'assets/css/dw-donation-goal.css';
  if ( file_exists($path) ) {
    wp_enqueue_style('dw-donation-goal', plugins_url('assets/css/dw-donation-goal.css', __FILE__), [], filemtime($path));
  }
});

/** Divi context bootstrap (optional helper) */
global $donations_cpt_divi_ctx_id;
$donations_cpt_divi_ctx_id = 0;
add_action('template_redirect', function() {
  global $donations_cpt_divi_ctx_id, $post;
  if ( function_exists('et_builder_get_current_page_id') ) {
    $id = intval( et_builder_get_current_page_id() );
    if ($id) $donations_cpt_divi_ctx_id = $id;
  }
  if ( ! $donations_cpt_divi_ctx_id && ! empty($post) && isset($post->ID) ) {
    $donations_cpt_divi_ctx_id = intval($post->ID);
  }
  if ( ! $donations_cpt_divi_ctx_id && isset($GLOBALS['wp_query']->query_vars['p']) ) {
    $id = intval($GLOBALS['wp_query']->query_vars['p']);
    if ($id) $donations_cpt_divi_ctx_id = $id;
  }
}, 5);

/** Includes (admin UI for donation CPT – no extras) */
require_once __DIR__ . '/display-controls.php';
if ( is_admin() ) { require_once __DIR__ . '/admin-metabox.php'; }

/** Helpers */
function donations_cpt_wc_active() { return class_exists('WooCommerce'); }
function donations_cpt_get_option($key, $default = '') { $val = get_option($key, null); return $val !== null ? $val : $default; }
function donations_cpt_sanitize_hex($color, $fallback) { $color = trim((string)$color); return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color) ? $color : $fallback; }

/** Register Donation CPT */
add_action('init', function () {
  register_post_type('donation', [
    'label'         => 'Donations',
    'public'        => true,
    'show_in_menu'  => true,
    'supports'      => ['title','editor','thumbnail'],
    'menu_icon'     => 'dashicons-heart',
    'has_archive'   => true,
    'rewrite'       => ['slug' => 'donations'],
    'taxonomies'    => ['category','post_tag'],
  ]);
});

/** Settings page (General + Appearance + Donation product only) */
add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=donation',
    'Donation Settings',
    'Settings',
    'manage_options',
    'donations-cpt-settings',
    'donations_cpt_settings_page'
  );
});
function donations_cpt_settings_page() {
  if (!current_user_can('manage_options')) return;
  echo '<div class="wrap"><h1>Donation Settings</h1>';
  if (!donations_cpt_wc_active()) { echo '<p><strong>WooCommerce is required.</strong></p></div>'; return; }

  if (isset($_POST['donations_cpt_save'])) {
    check_admin_referer('donations_cpt_settings');

    // Appearance
    $active = donations_cpt_sanitize_hex($_POST['donations_cpt_active_color'] ?? '#d8bd6a', '#d8bd6a');
    $btncol = donations_cpt_sanitize_hex($_POST['donations_cpt_button_color'] ?? '#c8102e', '#c8102e');
    $btntext= sanitize_text_field($_POST['donations_cpt_button_text'] ?? 'DONATE NOW');
    $placeholder = sanitize_text_field($_POST['donations_cpt_custom_placeholder'] ?? 'Enter Custom Amount');
    update_option('donations_cpt_active_color', $active);
    update_option('donations_cpt_button_color', $btncol);
    update_option('donations_cpt_button_text', $btntext);
    update_option('donations_cpt_custom_placeholder', $placeholder);

    // Donation product
    update_option('donations_cpt_product_id', intval($_POST['donations_cpt_product_id'] ?? 0));

    echo '<div class="updated notice"><p>Settings saved.</p></div>';
  }

  // Current values
  $active_color = donations_cpt_get_option('donations_cpt_active_color', '#d8bd6a');
  $button_color = donations_cpt_get_option('donations_cpt_button_color', '#c8102e');
  $button_text  = donations_cpt_get_option('donations_cpt_button_text',  'DONATE NOW');
  $custom_placeholder = donations_cpt_get_option('donations_cpt_custom_placeholder', 'Enter Custom Amount');
  $selected_product = intval(get_option('donations_cpt_product_id', 0));

  // Products dropdown
  $ids = function_exists('wc_get_products') ? wc_get_products(['status'=>['publish','private'],'limit'=>-1,'return'=>'ids']) : [];

  echo '<form method="post">'; wp_nonce_field('donations_cpt_settings');

  echo '<h2 class="title">General</h2>';
  echo '<table class="form-table"><tbody>';
  echo '<tr><th scope="row">Donation Product (one-off)</th><td>';
  if ($ids) {
    echo '<select name="donations_cpt_product_id"><option value="0">— Select —</option>';
    foreach ($ids as $pid) { $t=get_the_title($pid); $s=selected($selected_product,$pid,false); echo "<option value='$pid' $s>".esc_html($t)." (ID: $pid)</option>"; }
    echo '</select><p class="description">Simple, Virtual, Hidden, price 0.00.</p>';
  } else {
    echo '<input type="number" name="donations_cpt_product_id" value="'.esc_attr($selected_product).'">';
  }
  echo '</td></tr>';
  echo '</tbody></table>';

  echo '<h2 class="title">Appearance</h2>';
  echo '<table class="form-table"><tbody>';
  echo '<tr><th><label for="donations_cpt_active_color">Active card colour</label></th><td><input type="text" id="donations_cpt_active_color" name="donations_cpt_active_color" value="'.esc_attr($active_color).'" class="regular-text"></td></tr>';
  echo '<tr><th><label for="donations_cpt_button_color">Donate button colour</label></th><td><input type="text" id="donations_cpt_button_color" name="donations_cpt_button_color" value="'.esc_attr($button_color).'" class="regular-text"></td></tr>';
  echo '<tr><th><label for="donations_cpt_button_text">Donate button text</label></th><td><input type="text" id="donations_cpt_button_text" name="donations_cpt_button_text" value="'.esc_attr($button_text).'" class="regular-text"></td></tr>';
  echo '<tr><th><label for="donations_cpt_custom_placeholder">Custom amount placeholder</label></th><td><input type="text" id="donations_cpt_custom_placeholder" name="donations_cpt_custom_placeholder" value="'.esc_attr($custom_placeholder).'" class="regular-text"></td></tr>';
  echo '</tbody></table>';

  echo '<p><button class="button button-primary" name="donations_cpt_save">Save</button></p>';
  echo '</form></div>';
}

/**
 * Shortcode: [quickdonation id="..." show_id="0|1" id_label="ID:"]
 * Outputs all donation fields in a single horizontal row.
 */
add_shortcode('quickdonation', function( $atts ) {
  $atts = shortcode_atts([
    'id'          => 0,
    'button_text' => '',
    'show_id'     => '0',
    'id_label'    => 'ID:',
  ], $atts, 'quickdonation');

  // Resolve post ID
  $qid     = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
  $given   = intval( $atts['id'] );
  $loop_id = get_the_ID();
  $post_id = intval( $given ?: $qid ?: $loop_id );
  if ( ! $post_id ) return '';

  // Load rows & per-post settings
  $rows         = get_post_meta( $post_id, '_donation_rows_arr', true );
  if ( ! is_array( $rows ) ) $rows = [];
  $allow_custom = get_post_meta( $post_id, '_donation_allow_custom', true ) === '1';

  // Global options / fallbacks
  $button_text        = $atts['button_text'] !== '' ? $atts['button_text'] : donations_cpt_get_option( 'donations_cpt_button_text', 'DONATE NOW' );
  $custom_placeholder = donations_cpt_get_option( 'donations_cpt_custom_placeholder', 'Amount' );

  // Ensure front-end assets (if registered)
  if ( wp_script_is( 'donations-cpt-js', 'registered' ) ) wp_enqueue_script( 'donations-cpt-js' );
  if ( wp_style_is( 'donations-cpt', 'registered' ) )     wp_enqueue_style( 'donations-cpt' );

  $out  = '<div class="quickdonate donation-wrapper" data-post-id="' . (int) $post_id . '">';

  if ( $atts['show_id'] === '1' ) {
    $label = $atts['id_label'] !== '' ? esc_html( $atts['id_label'] ) . ' ' : '';
    $out  .= '<div class="donation-id" aria-hidden="true" style="flex:0 0 auto;">' . $label . '<span class="donation-id-number">' . (int) $post_id . '</span></div>';
  }

  $out .= '<div class="donation-card-grid" role="listbox" aria-label="' . esc_attr__( 'Donation amounts', 'donations-cpt' ) . '">';
  foreach ( $rows as $idx => $row ) {
    $active = $idx === 0 ? ' active' : '';
    $out   .= '<div class="form-widget donation-card' . $active . '"'
           .  ' tabindex="0" role="option" aria-selected="' . ( $idx === 0 ? 'true' : 'false' ) . '"'
           .  ' data-amount="' . esc_attr( $row['amount'] ) . '"'
           .  ' data-desc="' . esc_attr( $row['desc'] ) . '" style="flex:0 0 auto;">';
    $out   .= '<div class="donation-amount">' . ( function_exists( 'wc_price' ) ? wc_price( $row['amount'] ) : esc_html( $row['amount'] ) ) . '</div>';
    $out   .= '<div class="donation-desc">' . esc_html( $row['desc'] ) . '</div>';
    $out   .= '</div>';
  }
  $out .= '</div>';

  if ( $allow_custom ) {
    $out .= '<div class="donation-custom-wrap" style="flex:0 0 auto;">'
         .  '<input type="number" step="1" min="1" inputmode="numeric" pattern="^\d+$"'
         .  ' class="donation-custom-input form-control"'
         .  ' placeholder="' . esc_attr( $custom_placeholder ) . '"'
         .  ' style="min-width:30px;"'
         .  ' oninput="this.value = this.value.split(\\\'.\\\')[0].replace(/[^\\d]/g, \\\'\\\')"'
         .  '>'
         .  '</div>';
  }

  $out .= '<div class="donation-bar">'
       .  do_shortcode( '[donation_goal id="' . $post_id . '"]' )
       .  '<button type="button" class="donation-submit">' . esc_html( $button_text ) . '</button>'
       .  '<p class="donation-msg" style="margin:0;"></p>'
       .  '</div>';

  $out .= '</div>';
  return $out;
});

/** [donation_buttons] (cards + single Donate button) */
add_shortcode('donation_buttons', function ($atts) {
  $qid     = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
  $given   = isset($atts['id']) ? intval($atts['id']) : 0;
  $loop_id = get_the_ID();

  $post_id = intval($given ?: $qid ?: $loop_id);
  if (!$post_id) return '';
  $rows = get_post_meta($post_id, '_donation_rows_arr', true);
  if (!is_array($rows)) $rows = [];
  $allow_custom = get_post_meta($post_id, '_donation_allow_custom', true) === '1';

  $active_color = donations_cpt_sanitize_hex(donations_cpt_get_option('donations_cpt_active_color', '#d8bd6a'), '#d8bd6a');
  $button_color = donations_cpt_sanitize_hex(donations_cpt_get_option('donations_cpt_button_color', '#c8102e'), '#c8102e');
  $button_text  = donations_cpt_get_option('donations_cpt_button_text', 'DONATE NOW');
  $custom_placeholder = donations_cpt_get_option('donations_cpt_custom_placeholder', 'Enter Custom Amount');

  ob_start(); ?>
<div class="donation-wrapper" data-post-id="<?php echo (int) $post_id; ?>">
  <?php
  $permalink = get_permalink( $post_id );
  $title = get_the_title( $post_id );

  if ( has_post_thumbnail( $post_id ) ) {
    echo '<a href="' . esc_url( $permalink ) . '" class="donation-thumb-link">';
    echo get_the_post_thumbnail(
      $post_id,
      'large',
      [ 'loading' => 'lazy', 'alt' => esc_attr( $title ) ]
    );
    echo '</a>';
  }

  if ( $title ) {
    echo '<h2 class="donation-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h2>';
  }
  ?>

  <div class="row donation-card-grid" role="listbox" aria-label="Donation amounts">
    <?php foreach ($rows as $idx => $row): $active = $idx===0 ? ' active' : ''; ?>
      <div class="col-12 col-sm-6 col-md-4 form-widget donation-card<?php echo $active; ?>"
           tabindex="0"
           role="option"
           aria-selected="<?php echo $idx===0 ? 'true':'false'; ?>"
           data-amount="<?php echo esc_attr($row['amount']); ?>"
           data-desc="<?php echo esc_attr($row['desc']); ?>">
        <div class="donation-amount"><?php echo function_exists('wc_price') ? wc_price($row['amount']) : esc_html($row['amount']); ?></div>
        <div class="donation-desc"><?php echo esc_html($row['desc']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row">
    <?php if ($allow_custom): ?>
      <div class="col-12 donation-custom-wrap">
        <input
          type="number"
          step="1"
          min="1"
          inputmode="numeric"
          pattern="^\d+$"
          class="donation-custom-input form-control"
          placeholder="<?php echo esc_attr($custom_placeholder); ?>"
          oninput="this.value = this.value.replace(/[^\d]/g, '')"
        >
      </div>
    <?php endif; ?>
  </div>

  <div class="donation-bar">
    <?php echo do_shortcode('[donation_goal id="'.$post_id.'"]'); ?>
    <button type="button" class="donation-submit"><?php echo esc_html($button_text); ?></button>
    <p class="donation-msg"></p>
  </div>
</div>
<?php
  return ob_get_clean();
});

/** [donation_figure1] – inner figure only */
add_shortcode('donation_figure1', function ($atts) {
  $atts = shortcode_atts([
    'id'          => 0,
    'button_text' => '',
  ], $atts, 'donation_figure1');

  $qid     = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
  $given   = intval($atts['id']);
  $loop_id = get_the_ID();
  $post_id = intval($given ?: $qid ?: $loop_id);
  if (!$post_id) return '';

  $rows          = get_post_meta($post_id, '_donation_rows_arr', true);
  if (!is_array($rows)) $rows = [];
  $allow_custom  = get_post_meta($post_id, '_donation_allow_custom', true) === '1';

  $button_text        = $atts['button_text'] !== '' ? $atts['button_text'] : donations_cpt_get_option('donations_cpt_button_text', 'DONATE NOW');
  $custom_placeholder = donations_cpt_get_option('donations_cpt_custom_placeholder', 'Enter Custom Amount');

  if (wp_script_is('donations-cpt-js', 'registered')) wp_enqueue_script('donations-cpt-js');
  if (wp_style_is('donations-cpt', 'registered'))     wp_enqueue_style('donations-cpt');

  ob_start(); ?>
  <div class="donation-wrapper" data-post-id="<?php echo (int) $post_id; ?>">
    <div class="row donation-card-grid" role="listbox" aria-label="<?php echo esc_attr__('Donation amounts', 'donations-cpt'); ?>">
      <?php foreach ($rows as $idx => $row): $active = $idx===0 ? ' active' : ''; ?>
        <div class="col-12 col-sm-6 col-md-4 form-widget donation-card<?php echo $active; ?>"
             tabindex="0"
             role="option"
             aria-selected="<?php echo $idx===0 ? 'true':'false'; ?>"
             data-amount="<?php echo esc_attr($row['amount']); ?>"
             data-desc="<?php echo esc_attr($row['desc']); ?>">
          <div class="donation-amount">
            <?php echo function_exists('wc_price') ? wc_price($row['amount']) : esc_html($row['amount']); ?>
          </div>
          <div class="donation-desc"><?php echo esc_html($row['desc']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($allow_custom): ?>
      <div class="row">
        <div class="col-12 donation-custom-wrap">
          <input
            type="number"
            step="1"
            min="1"
            inputmode="numeric"
            pattern="^\d+$"
            class="donation-custom-input form-control"
            placeholder="<?php echo esc_attr($custom_placeholder); ?>"
            oninput="this.value = this.value.split('.')[0].replace(/[^0-9]/g,'')"
          >
        </div>
      </div>
    <?php endif; ?>

    <div class="donation-bar">
      <?php echo do_shortcode('[donation_goal id="'.$post_id.'"]'); ?>
      <button type="button" class="donation-submit"><?php echo esc_html($button_text); ?></button>
      <p class="donation-msg"></p>
    </div>
  </div>
  <?php
  return ob_get_clean();
});

/** AJAX: add to cart from donation cards */
add_action('wp_ajax_donation_add_to_cart', 'donation_ajax_add');
add_action('wp_ajax_nopriv_donation_add_to_cart', 'donation_ajax_add');
function donation_ajax_add() {
  if (!wp_verify_nonce($_POST['nonce'] ?? '', 'donation_add_to_cart')) wp_send_json_error(['message'=>'Security check failed'],403);
  if (!donations_cpt_wc_active()) wp_send_json_error(['message'=>'WooCommerce not active.']);

  $amount  = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
  $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
  $desc    = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
  $product_id = intval(get_option('donations_cpt_product_id', 0));

  if ($amount <= 0) wp_send_json_error(['message'=>'Please choose an amount or enter a valid custom amount.']);
  if (!$product_id || !wc_get_product($product_id)) wp_send_json_error(['message'=>'Donation product not set. Go to Donations → Settings.']);

  $added = WC()->cart->add_to_cart($product_id,1,0,[],[
    'donation_amount'=>$amount,
    'donation_post'=>$post_id,
    'donation_label'=>get_the_title($post_id),
    'donation_desc'=>$desc,
  ]);
  if (!$added) wp_send_json_error(['message'=>'Could not add to cart.']);
  wp_send_json_success(['redirect'=> (function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '') ]);
}

/** Dynamic price set on the donation product */
add_action('woocommerce_before_calculate_totals', function($cart){
  if (is_admin() && !defined('DOING_AJAX')) return;
  if (empty($cart->get_cart())) return;
  $pid = intval(get_option('donations_cpt_product_id', 0));
  foreach ($cart->get_cart() as $k=>$item){
    if (!empty($item['donation_amount']) && intval($item['product_id'])===intval($pid)){
      $item['data']->set_price((float)$item['donation_amount']);
    }
  }
});

/** Show meta in cart/checkout & persist to order (no extras logic) */
add_filter('woocommerce_cart_item_thumbnail', function($thumb, $cart_item, $cart_item_key){
  $pid = !empty($cart_item['donation_post']) ? (int) $cart_item['donation_post'] : 0;
  if (!$pid) return $thumb;

  $img = get_the_post_thumbnail(
    $pid,
    'woocommerce_thumbnail',
    ['class'=>'donation-post-thumb','loading'=>'lazy','alt'=>get_the_title($pid)]
  );
  return $img ? $img : $thumb;
}, 10, 3);

add_filter('woocommerce_cart_item_permalink', function($permalink, $cart_item, $cart_item_key){
  if (!empty($cart_item['donation_post'])) {
    return get_permalink((int) $cart_item['donation_post']);
  }
  return $permalink;
}, 10, 3);

add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
  $lines = [];
  if ( ! empty($cart_item['donation_label']) ) {
    $lines[] = esc_html( sanitize_text_field($cart_item['donation_label']) );
  } elseif ( ! empty($cart_item['donation_post']) ) {
    $lines[] = esc_html( get_the_title( (int) $cart_item['donation_post'] ) );
  }
  if ( ! empty($cart_item['donation_amount']) ) {
    $lines[] = function_exists('wc_price')
      ? wc_price( (float) $cart_item['donation_amount'] )
      : esc_html( $cart_item['donation_amount'] );
  }
  if ( ! empty($cart_item['donation_desc']) ) {
    $lines[] = esc_html( $cart_item['donation_desc'] );
  }
  if ( $lines ) {
    $display = implode('<br>', $lines);
    $item_data[] = [
      'key'     => '',
      'value'   => wp_kses_post($display),
      'display' => wp_kses_post($display),
    ];
  }
  return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function($item, $key, $values){
  // Persist Donation (post) title
  $title = '';
  if (!empty($values['donation_label'])) {
    $title = sanitize_text_field($values['donation_label']);
  } elseif (!empty($values['donation_post'])) {
    $title = get_the_title( (int) $values['donation_post'] );
  }
  if ($title !== '') {
    $item->add_meta_data( __('Donation', 'donations-cpt'), $title, true );
  }
  if (isset($values['donation_desc'])) {
    $item->add_meta_data( __('Description','donations-cpt'), sanitize_text_field($values['donation_desc']), true );
  }
  if (isset($values['donation_amount'])) {
    $item->add_meta_data( __('Donation Amount','donations-cpt'),
      function_exists('wc_price') ? wc_price((float)$values['donation_amount']) : $values['donation_amount'],
      true
    );
  }
  if (isset($values['donation_post'])) {
    $item->add_meta_data('donation_post', (int) $values['donation_post'], true);
  }
}, 10, 3);

/** Shortcodes to render donations (no extras) */
if ( ! function_exists('dcpt_render_one_donation') ) {
  function dcpt_render_one_donation($post_id, $atts = []) {
    if (!$post_id) return '';
    if (wp_script_is('donations-cpt-js', 'registered')) wp_enqueue_script('donations-cpt-js');
    if (wp_style_is('donations-cpt', 'registered'))     wp_enqueue_style('donations-cpt');

    $layout = isset($atts['layout']) ? $atts['layout'] : 'card';
    $class  = trim((isset($atts['class']) ? $atts['class'] : '') . ' dcpt-layout-' . sanitize_html_class($layout));

    ob_start();
    echo '<div class="col donation-item ' . esc_attr($class) . '" data-donation-id="' . esc_attr($post_id) . '">';
    echo do_shortcode('[donation_buttons id="' . intval($post_id) . '"' . (!empty($atts['button_text']) ? ' button_text="' . esc_attr($atts['button_text']) . '"' : '') . ']');
    echo '</div>';
    return ob_get_clean();
  }
}

add_shortcode('donation', function($atts) {
  $atts = shortcode_atts([
    'id'          => '',
    'slug'        => '',
    'layout'      => 'card',
    'button_text' => '',
    'class'       => '',
  ], $atts, 'donation');
  $post = null;
  if (!empty($atts['id']))      $post = get_post(intval($atts['id']));
  elseif (!empty($atts['slug']))$post = get_page_by_path(sanitize_title($atts['slug']), OBJECT, 'donation');
  if (!$post || $post->post_type !== 'donation') return '<p>Donation not found.</p>';
  return dcpt_render_one_donation($post->ID, $atts);
});

add_shortcode('donation_current', function($atts) {
  $atts = shortcode_atts([ 'layout'=>'card','button_text'=>'','class'=>'' ], $atts, 'donation_current');
  $p = get_post();
  if (empty($p) || $p->post_type !== 'donation') return '<p>No donation in context.</p>';
  return dcpt_render_one_donation($p->ID, $atts);
});

/** Grid opener (fixed undefined variable) */
if ( ! function_exists('dcpt_open_grid') ) {
  function dcpt_open_grid($cols, $extra_class = '') {
    $cols = max(1, min(6, intval($cols)));
    $extra_class = trim($extra_class);
    return '<div class="row row-cols-1 row-cols-md-' . $cols . ( $extra_class ? ' ' . esc_attr($extra_class) : '' ) . '">';
  }
}

/** Category & list/archive shortcodes (no extras) */
add_shortcode('donation_category', function($atts) {
  $atts = shortcode_atts([ 'slug'=>'','columns'=>3,'layout'=>'card','limit'=>12,'class'=>'','taxonomy'=>'category' ], $atts, 'donation_category');
  if (!$atts['slug']) return '<p>No category specified.</p>';
  $q = new WP_Query([
    'post_type'=>'donation','posts_per_page'=>intval($atts['limit']),
    'tax_query'=>[[ 'taxonomy'=>sanitize_key($atts['taxonomy']),'field'=>'slug','terms'=>sanitize_title($atts['slug']) ]],
  ]);
  if (!$q->have_posts()) return '<p>No donations in this category.</p>';
  if (wp_script_is('donations-cpt-js', 'registered')) wp_enqueue_script('donations-cpt-js');
  ob_start();
  echo dcpt_open_grid($atts['columns'], $atts['class']);
  while ($q->have_posts()) { $q->the_post(); echo dcpt_render_one_donation(get_the_ID(), $atts); }
  echo '</div>';
  wp_reset_postdata();
  return ob_get_clean();
});

add_shortcode('donation_list', function($atts) {
  $atts = shortcode_atts([
    'columns'=>3,'layout'=>'card','limit'=>12,'class'=>'',
    'taxonomy'=>'',
    'slug'=>'',
    'current'=>'',
  ], $atts, 'donation_list');

  $args = [ 'post_type'=>'donation', 'posts_per_page'=>intval($atts['limit']) ];

  if ($atts['current'] === '1') {
    $term = get_queried_object();
    if ($term && !is_wp_error($term) && !empty($term->taxonomy) && !empty($term->slug)) {
      $args['tax_query'] = [[ 'taxonomy'=>$term->taxonomy, 'field'=>'slug', 'terms'=>$term->slug ]];
    }
  } elseif (!empty($atts['slug'])) {
    $taxonomy = $atts['taxonomy'] ? sanitize_key($atts['taxonomy']) : 'category';
    $args['tax_query'] = [[ 'taxonomy'=>$taxonomy, 'field'=>'slug', 'terms'=>sanitize_title($atts['slug']) ]];
  }

  $q = new WP_Query($args);
  if (!$q->have_posts()) return '<p>No donations found.</p>';
  if (wp_script_is('donations-cpt-js', 'registered')) wp_enqueue_script('donations-cpt-js');

  ob_start();
  echo dcpt_open_grid($atts['columns'], $atts['class']);
  while ($q->have_posts()) { $q->the_post(); echo dcpt_render_one_donation(get_the_ID(), $atts); }
  echo '</div>';
  wp_reset_postdata();
  return ob_get_clean();
});

add_shortcode('donation_archive', function($atts) {
  $atts = shortcode_atts([ 'columns'=>3,'layout'=>'card','limit'=>12,'class'=>'','taxonomy'=>'' ], $atts, 'donation_archive');
  $tax_query = [];
  $term = get_queried_object();
  if ($term && !is_wp_error($term) && !empty($term->taxonomy) && !empty($term->slug)) {
    $taxonomy = $atts['taxonomy'] ? sanitize_key($atts['taxonomy']) : $term->taxonomy;
    $tax_query = [[ 'taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$term->slug ]];
  } elseif (!empty($atts['taxonomy']) && !empty($_GET['term'])) {
    $tax_query = [[ 'taxonomy'=>sanitize_key($atts['taxonomy']),'field'=>'slug','terms'=>sanitize_title(wp_unslash($_GET['term'])) ]];
  }
  $args = [ 'post_type'=>'donation','posts_per_page'=>intval($atts['limit']) ];
  if ($tax_query) $args['tax_query'] = $tax_query;
  $q = new WP_Query($args);
  if (!$q->have_posts()) return '<p>No donations found.</p>';
  if (wp_script_is('donations-cpt-js', 'registered')) wp_enqueue_script('donations-cpt-js');
  ob_start();
  echo dcpt_open_grid($atts['columns'], $atts['class']);
  while ($q->have_posts()) { $q->the_post(); echo dcpt_render_one_donation(get_the_ID(), $atts); }
  echo '</div>';
  wp_reset_postdata();
  return ob_get_clean();
});

/** Donate button flow (global) */
add_action('wp_enqueue_scripts', function () {
  wp_enqueue_script('jquery');
  wp_enqueue_script(
    'donations-cpt-js',
    plugin_dir_url(__FILE__) . 'donations.js',
    ['jquery'],
    file_exists(plugin_dir_path(__FILE__).'donations.js') ? filemtime(plugin_dir_path(__FILE__).'donations.js') : '2.6.3',
    true
  );
  wp_localize_script('donations-cpt-js', 'DonationsCPT', [
    'ajax'     => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('donation_add_to_cart'),
    'post_id'  => (is_singular('donation') && function_exists('get_queried_object_id')) ? (int) get_queried_object_id() : 0,
    'checkout' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
    'cart'     => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
  ]);
}, 20);

/** Rename checkout summary title */
add_filter( 'gettext', 'custom_change_order_summary_title', 20, 3 );
function custom_change_order_summary_title( $translated_text, $text, $domain ) {
  if ( $text === 'Your order' && $domain === 'woocommerce' ) {
    $translated_text = 'Donation Summary';
  }
  return $translated_text;
}

/** GoCardless container placeholder */
add_action( 'woocommerce_after_order_notes', 'add_gocardless_checkout_container' );
function add_gocardless_checkout_container( $checkout ) {
  echo '<div id="gocardless-checkout" style="margin:20px 0;"></div>';
}

/* =========================
 * Further Support (admin + frontend + fees)
 * ========================= */

/** Admin page: Further Support items */
add_action('admin_menu', function () {
  add_submenu_page(
    'edit.php?post_type=donation',
    'Further Support',
    'Further Support',
    'manage_options',
    'dw-support-items',
    'dw_support_items_page'
  );
});

function dw_support_items_page() {
  if (!current_user_can('manage_options')) return;

  if (isset($_POST['dw_support_save'])) {
    check_admin_referer('dw_support_items');
    $rows = [];
    if (!empty($_POST['dw_support_label']) && is_array($_POST['dw_support_label'])) {
      $labels  = array_map('sanitize_text_field', $_POST['dw_support_label']);
      $amounts = $_POST['dw_support_amount'];
      foreach ($labels as $i => $lbl) {
        $amt = isset($amounts[$i]) ? floatval($amounts[$i]) : 0;
        if ($lbl !== '' && $amt > 0) {
          $key = sanitize_key(preg_replace('/\s+/', '_', strtolower($lbl)));
          $rows[] = ['key'=>$key, 'label'=>$lbl, 'amount'=>number_format($amt,2,'.','')];
        }
      }
    }
    update_option('dw_support_items', $rows);
    echo '<div class="updated notice"><p>Saved.</p></div>';
  }

  $rows = get_option('dw_support_items', []);
  if (!is_array($rows)) $rows = [];
  if (empty($rows)) {
    $rows = [
      ['key'=>'sadaqah','label'=>'SADAQAH','amount'=>'20.00'],
      ['key'=>'zakat','label'=>'ZAKAT','amount'=>'30.00'],
      ['key'=>'water_programme','label'=>'WATER PROGRAMME','amount'=>'40.00'],
    ];
  }

  echo '<div class="wrap"><h1>Further Support Items</h1>';
  echo '<form method="post">'; wp_nonce_field('dw_support_items');
  echo '<table class="widefat striped" id="dw-support-table"><thead><tr><th>Label</th><th>Amount (£)</th><th></th></tr></thead><tbody>';
  foreach ($rows as $r) {
    echo '<tr>';
    echo '<td><input type="text" name="dw_support_label[]" value="'.esc_attr($r['label']).'" class="regular-text"></td>';
    echo '<td><input type="number" step="0.01" min="0" name="dw_support_amount[]" value="'.esc_attr($r['amount']).'" class="small-text"></td>';
    echo '<td><button class="button link-delete dw-support-remove" type="button">Remove</button></td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo '<p><button type="button" class="button" id="dw-support-add">+ Add row</button></p>';
  echo '<p><button class="button button-primary" name="dw_support_save">Save</button></p>';
  echo '</form></div>';

  // Inline admin JS for repeater
  echo '<script>
  (function(){
    const tbody = document.querySelector("#dw-support-table tbody");
    document.getElementById("dw-support-add").addEventListener("click", function(){
      const tr = document.createElement("tr");
      tr.innerHTML = `<td><input type="text" name="dw_support_label[]" class="regular-text" placeholder="Label"></td>
                      <td><input type="number" step="0.01" min="0" name="dw_support_amount[]" class="small-text" placeholder="0.00"></td>
                      <td><button type="button" class="button link-delete dw-support-remove">Remove</button></td>`;
      tbody.appendChild(tr);
    });
    document.addEventListener("click", function(e){
      if (e.target && e.target.classList.contains("dw-support-remove")) {
        e.preventDefault();
        const tr = e.target.closest("tr");
        if (tr) tr.remove();
      }
    });
  })();
  </script>';
}

/** Helper: get rows */
function dw_support_rows() {
  $rows = get_option('dw_support_items', []);
  return is_array($rows) ? $rows : [];
}

/** Render support block (for mini-cart/flyout) */
function dw_render_support_block() {
  if (is_admin()) return;
  $rows = dw_support_rows();
  if (empty($rows)) return;

  $selected = [];
  if (WC()->session) {
    $selected = (array) WC()->session->get('dw_support_selected', []);
  }

  echo '<div class="dw-support-block">';
  echo '<h4 class="dw-support-title">'.esc_html__('Support us further by donating:','woocommerce').'</h4>';
  echo '<div class="dw-support-list">';
  foreach ($rows as $r) {
    $key = esc_attr($r['key']);
    $lbl = esc_html($r['label']);
    $amt = (float) $r['amount'];
    $is_on = in_array($key, $selected, true);
    echo '<label class="dw-support-row">';
    echo '<input type="checkbox" class="dw-support-toggle" data-key="'.$key.'" data-label="'.$lbl.'" data-amount="'.$amt.'" '.checked($is_on, true, false).'>';
    echo '<span class="dw-support-amount">'.(function_exists('wc_price') ? wc_price($amt) : esc_html($amt)).'</span>';
    echo '<span class="dw-support-label">'.$lbl.'</span>';
    echo '</label>';
  }
  echo '</div>';
  echo '</div>';
}

/** Output inside Woo mini-cart widget */
add_action('woocommerce_widget_shopping_cart_before_buttons', 'dw_render_support_block', 5);

/** Register fragment for the block */
add_filter('woocommerce_add_to_cart_fragments', function($frags){
  ob_start(); dw_render_support_block(); $frags['div.dw-support-block'] = ob_get_clean();
  return $frags;
});

/** Fees: add one per selected key (deduped) */
add_action('woocommerce_cart_calculate_fees', function($cart){
  if (is_admin() && !defined('DOING_AJAX')) return;
  if (!WC()->session) return;

  $rows = dw_support_rows();
  if (empty($rows)) return;

  $selected = (array) WC()->session->get('dw_support_selected', []);
  if (empty($selected)) return;

  $map = [];
  foreach ($rows as $r) $map[$r['key']] = $r;

  foreach (array_unique($selected) as $key) {
    if (!isset($map[$key])) continue;
    $label  = sanitize_text_field($map[$key]['label']);
    $amount = (float) $map[$key]['amount'];
    if ($amount > 0) {
      $cart->add_fee( sprintf(__('Donation — %s','woocommerce'), $label), $amount, false );
    }
  }
}, 20);

/** AJAX: update selected keys */
add_action('wp_ajax_dw_support_update', 'dw_support_update');
add_action('wp_ajax_nopriv_dw_support_update', 'dw_support_update');
function dw_support_update() {
  if (!WC()->session) wp_send_json_success();
  $keys = [];
  if (!empty($_POST['keys'])) {
    $arr = is_array($_POST['keys']) ? $_POST['keys'] : json_decode(stripslashes((string)$_POST['keys']), true);
    if (is_array($arr)) {
      foreach ($arr as $k) {
        $k = sanitize_key((string)$k);
        if ($k !== '') $keys[$k] = true;
      }
    }
  }
  WC()->session->set('dw_support_selected', array_keys($keys));
  wp_send_json_success();
}

/** Enqueue front-end JS */
add_action('wp_enqueue_scripts', function(){
  wp_enqueue_script(
    'dw-support-js',
    plugin_dir_url(__FILE__). 'assets/js/dw-support.js',
    ['jquery','wc-cart-fragments'],
    '1.0.0',
    true
  );
  wp_localize_script('dw-support-js', 'DW_SUPPORT', [
    'ajax'  => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('dw_support_nonce'),
  ]);
}, 25);


// Show the donation amount (from your form) in the mini-cart "qty × price" line
add_filter('woocommerce_widget_cart_item_quantity', function ($html, $cart_item, $cart_item_key) {
    $donation_pid = (int) get_option('donations_cpt_product_id', 0);

    if (!empty($cart_item['donation_amount']) && (int)$cart_item['product_id'] === $donation_pid) {
        $qty   = (int) $cart_item['quantity'];
        $price = function_exists('wc_price') ? wc_price((float) $cart_item['donation_amount']) : (float) $cart_item['donation_amount'];

        $html = '<span class="quantity">' . $qty . ' × ' . $price . '</span>';
    }
    return $html;
}, 10, 3);

// (Optional) also fix the unit price shown on cart/checkout lines
add_filter('woocommerce_cart_item_price', function ($price_html, $cart_item, $cart_item_key) {
    $donation_pid = (int) get_option('donations_cpt_product_id', 0);

    if (!empty($cart_item['donation_amount']) && (int)$cart_item['product_id'] === $donation_pid) {
        $price_html = function_exists('wc_price') ? wc_price((float) $cart_item['donation_amount']) : (float) $cart_item['donation_amount'];
    }
    return $price_html;
}, 10, 3);

// Change the "View cart" button text in the mini-cart / flycart
add_filter('gettext', function($translated_text, $text, $domain) {
    if ($text === 'View cart' && $domain === 'woocommerce') {
        $translated_text = 'Add Another'; // ← your custom text
    }
    return $translated_text;
}, 20, 3);

// ===============================
// Register the Total as a WooCommerce fragment (refreshes on AJAX)
// ===============================
add_filter('woocommerce_add_to_cart_fragments', function($fragments){
    ob_start();
    if (WC()->cart) {
        WC()->cart->calculate_totals();
        echo '<p class="woocommerce-mini-cart__total dw-mini-total extra-total"><strong>' . esc_html__('Total', 'woocommerce') . ':</strong> ' . wp_kses_post(WC()->cart->get_total()) . '</p>';
    }
    // The key must match the selector of the element you just echoed
    $fragments['p.dw-mini-total.extra-total'] = ob_get_clean();
    return $fragments;
});

// (Remove the woocommerce_widget_shopping_cart_total action to avoid duplicates)

// ===============================
// Enqueue the instant total script
// ===============================
add_action('wp_enqueue_scripts', function(){
    $rel  = 'assets/js/dw-support.js';                    // no leading slash
    $path = plugin_dir_path(__FILE__) . $rel;             // filesystem path
    $url  = plugin_dir_url(__FILE__)  . $rel;             // public URL

    wp_enqueue_script(
        'dw-instant-total',
        $url,
        ['jquery','wc-cart-fragments'],                   // ensure fragment events exist
        file_exists($path) ? filemtime($path) : null,
        true
    );
});



add_filter('woocommerce_use_cart_fragments_cache', '__return_false');


?>