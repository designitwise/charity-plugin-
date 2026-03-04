# Development Guide - Charity Plugin

Guide for developers extending the Charity Donation Plugin.

## Plugin Structure

### Bootstrap (`charity-plugin.php`)
Main plugin entry point that:
- Registers plugin header
- Defines constants
- Loads text domain
- Includes core classes
- Enqueues admin and frontend assets
- Fires initialization hooks

### Core Classes

#### `Charity_Donations_CPT`
**File:** `includes/class-donations-cpt.php`

Handles:
- Post type registration
- AJAX handlers
- Utility methods

**Key Methods:**
```php
Charity_Donations_CPT::init()               // Initialize hooks
Charity_Donations_CPT::register_post_type() // Register CPT
Charity_Donations_CPT::handle_add_to_cart() // AJAX handler
Charity_Donations_CPT::get_option()         // Get option with fallback
Charity_Donations_CPT::sanitize_hex_color() // Validate color
```

**Extending:**
```php
// Add custom post type argument
add_filter( 'charity_donation_cpt_args', function( $args ) {
    $args['taxonomies'][] = 'my-taxonomy';
    return $args;
});
```

#### `Charity_Metabox_Handler`
**File:** `includes/class-metabox-handler.php`

Handles:
- Admin metabox registration
- Metabox rendering
- Data saving and validation

**Key Methods:**
```php
Charity_Metabox_Handler::init()      // Initialize metabox
Charity_Metabox_Handler::render_metabox() // Render UI
Charity_Metabox_Handler::save_metabox()   // Save data
```

**Extending:**
```php
// Add custom metabox fields
add_action( 'charity_donation_saved', function( $post_id, $rows ) {
    // Process after donation is saved
}, 10, 2 );
```

#### `Charity_Settings_Handler`
**File:** `includes/class-settings-handler.php`

Handles:
- Settings page registration
- Form submission and validation
- Option storage

**Key Methods:**
```php
Charity_Settings_Handler::init()        // Initialize settings
Charity_Settings_Handler::get_all_settings() // Get all options
```

**Accessing Settings:**
```php
$settings = Charity_Settings_Handler::get_all_settings();
echo $settings['active_color'];     // #d8bd6a
echo $settings['button_color'];     // #c8102e
echo $settings['button_text'];      // DONATE NOW
echo $settings['product_id'];       // 123
```

#### `Charity_Shortcodes`
**File:** `includes/class-shortcodes.php`

Handles:
- Shortcode registration
- Form rendering
- Post ID resolution

**Shortcodes:**
```php
[donation_buttons]    // Full form with thumbnail
[quickdonation]       // Horizontal form
[donation_goal]       // Goal progress
```

**Key Methods:**
```php
Charity_Shortcodes::donation_buttons_shortcode()
Charity_Shortcodes::quickdonation_shortcode()
Charity_Shortcodes::donation_goal_shortcode()
```

## Hooks & Filters

### Actions

**charity_plugin_loaded**
```php
add_action( 'charity_plugin_loaded', function() {
    // Initialize your extension
    // Fires after all core classes are loaded
});
```

**charity_donation_saved**
```php
add_action( 'charity_donation_saved', function( $post_id, $rows ) {
    // $post_id: int - Donation post ID
    // $rows: array - Donation amounts array
    
    // Example: Store data externally
    update_option( "my_donation_$post_id", json_encode( $rows ) );
}, 10, 2 );
```

**charity_settings_saved**
```php
add_action( 'charity_settings_saved', function( $settings ) {
    // $settings: array
    //   [
    //     'product_id' => 123,
    //     'active_color' => '#d8bd6a',
    //     'button_color' => '#c8102e',
    //     'button_text' => 'DONATE NOW',
    //     'custom_placeholder' => 'Enter amount'
    //   ]
    
    // Example: Log changes
    error_log( 'Settings updated: ' . json_encode( $settings ) );
}, 10, 1 );
```

### Filters

**charity_donation_cart_data**
```php
add_filter( 'charity_donation_cart_data', function( $data, $post_id, $amount ) {
    // $data: array - Cart item metadata
    // $post_id: int - Donation post ID
    // $amount: float - Donation amount
    
    // Add custom field
    $data['donor_type'] = 'anonymous';
    $data['campaign'] = get_the_title( $post_id );
    
    return $data;
}, 10, 3 );
```

**charity_donation_add_response**
```php
add_filter( 'charity_donation_add_response', function( $response, $product_id, $amount ) {
    // $response: array - AJAX response
    // $product_id: int - WooCommerce product ID
    // $amount: float - Donation amount
    
    // Add tracking data
    $response['tracking'] = [
        'amount' => $amount,
        'timestamp' => current_time( 'mysql' )
    ];
    
    return $response;
}, 10, 3 );
```

**charity_donation_before_button**
```php
add_filter( 'charity_donation_before_button', function( $html, $post_id, $data ) {
    // $html: string - Current HTML
    // $post_id: int - Donation post ID
    // $data: array - Donation data
    
    // Add info box
    $html .= '<div class="donation-info">';
    $html .= '<p>Your donation helps us serve the community.</p>';
    $html .= '</div>';
    
    return $html;
}, 10, 3 );
```

**charity_donation_after_button**
```php
add_filter( 'charity_donation_after_button', function( $html, $post_id, $data ) {
    // Add disclaimer after button
    $html .= '<small>A processing fee may apply</small>';
    return $html;
}, 10, 3 );
```

## Frontend JavaScript API

### DonationForm Module

The frontend exposes `window.CharityDonationForm` module:

```javascript
// Get configuration
CharityDonations.ajax_url   // AJAX endpoint
CharityDonations.nonce      // Security nonce

// Access module methods
CharityDonationForm.parseAmount( '100.50' )        // 100.50
CharityDonationForm.showMessage( $wrapper, msg )   // Show feedback
```

### Localized Data

Data available in JavaScript:
```javascript
CharityDonations = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce:    'abc123...'
};
```

### Custom Event Handling

Hook into form events:
```javascript
jQuery(document).on('click', '.donation-card', function() {
    // Custom code when card is selected
});

jQuery(document).on('submit', '.donation-wrapper', function() {
    // Custom code on form submit
});
```

## Extending Functionality

### Example: Custom Donation Tracking

```php
// 1. Hook into saves
add_action( 'charity_donation_saved', function( $post_id, $rows ) {
    // Create custom post type for transactions
    wp_insert_post( [
        'post_type' => 'donation_transaction',
        'post_title' => 'Donation: ' . get_the_title( $post_id ),
        'post_status' => 'publish',
        'meta_input' => [
            '_donation_campaign_id' => $post_id,
            '_rows' => serialize( $rows )
        ]
    ]);
}, 10, 2 );

// 2. Add cart item metadata
add_filter( 'charity_donation_cart_data', function( $data, $post_id, $amount ) {
    $data['campaign_title'] = get_the_title( $post_id );
    $data['donation_timestamp'] = current_time( 'mysql' );
    return $data;
}, 10, 3 );

// 3. Handle AJAX response
add_filter( 'charity_donation_add_response', function( $response, $product_id, $amount ) {
    $response['tracking_id'] = wp_generate_uuid4();
    return $response;
}, 10, 3 );
```

### Example: Custom Validation

```php
// Override AJAX handler with validation
add_action( 'wp_ajax_donation_add_to_cart', function() {
    // Get data
    $post_id = intval( $_POST['post_id'] );
    
    // Custom validation
    if ( ! user_can_donate_to( $post_id ) ) {
        wp_send_json_error( [ 'message' => 'Not eligible' ] );
    }
    
    // Let default handler continue
}, 8, 0 );
```

## Testing

### Unit Test Example

```php
class Charity_Plugin_Test extends WP_UnitTestCase {
    
    function test_donation_post_type_exists() {
        $this->assertTrue( post_type_exists( 'donation' ) );
    }
    
    function test_get_option_with_default() {
        $value = Charity_Donations_CPT::get_option( 'nonexistent', 'default' );
        $this->assertEquals( 'default', $value );
    }
    
    function test_hex_color_validation() {
        $color = Charity_Donations_CPT::sanitize_hex_color( '#fff', '#000' );
        $this->assertEquals( '#fff', $color );
        
        $invalid = Charity_Donations_CPT::sanitize_hex_color( 'notacolor', '#000' );
        $this->assertEquals( '#000', $invalid );
    }
}
```

### Manual Testing

1. **Create test donation:**
   - Title: "Test Campaign"
   - Amounts: $10, $25, $50
   - Allow custom: Yes

2. **Test shortcode:**
   ```
   [donation_buttons id="42"]
   ```

3. **Test form interaction:**
   - Click cards
   - Enter custom amount
   - Submit form
   - Check AJAX calls in DevTools

4. **Verify events fire:**
   ```javascript
   // In browser console
   jQuery(document.body).on('charity_donation_added', function() {
       console.log('Donation added!');
   });
   ```

## Best Practices

1. **Always use hooks** — Avoid modifying plugin files directly
2. **Sanitize input** — Use WP functions like `sanitize_text_field()`
3. **Escape output** — Use `esc_html()`, `esc_attr()`, etc.
4. **Use constants** — Refer to `CHARITY_PLUGIN_DIR`, `CHARITY_PLUGIN_VERSION`
5. **Handle errors gracefully** — Provide fallbacks
6. **Use text domains** — Wrap strings in `esc_html__( 'text', 'charity-plugin' )`
7. **Cache data** — Use transients for expensive operations
8. **Version compatibility** — Check for function existence

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Developer Docs](https://docs.woocommerce.com/)
- [WordPress Hooks Documentation](https://developer.wordpress.org/plugins/hooks/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

## Support

Found a bug or have a feature request? Please open an issue on GitHub.
