# Migration Guide - Charity Plugin 2.6.0

This document outlines the major refactoring in version 2.6.0 and how to migrate existing code.

## Overview of Changes

The plugin has been restructured from procedural to object-oriented architecture with proper class organization, improved security, and better extensibility.

### ✅ What's New

- **Class-based architecture** — Easier to test, extend, and maintain
- **Hook/filter system** — Extensibility points for developers
- **Improved security** — Strict nonce verification, escaping, validation
- **Better error handling** — AJAX errors with proper messages
- **Module pattern JS** — Encapsulated JavaScript avoiding global pollution
- **Consolidated shortcuts** — Unified donation form logic

### 🔄 File Structure

**New structure:**
```
charity-plugin/
├── charity-plugin.php              (main plugin file)
├── includes/
│   ├── class-donations-cpt.php     (CPT registration, AJAX)
│   ├── class-metabox-handler.php   (admin metabox)
│   ├── class-settings-handler.php  (settings page)
│   └── class-shortcodes.php        (all shortcodes)
├── assets/
│   ├── js/
│   │   ├── donations.js            (refactored frontend)
│   │   ├── metabox.js              (admin metabox JS)
│   │   └── dw-flycart.js           (floating cart)
│   └── css/
│       └── donations.css           (main styles)
└── README.md                       (documentation)
```

## Migration Steps

### Option 1: Fresh Install
Disable the old plugin and activate the new **Charity Donation Plugin** (`charity-plugin.php`). All existing donation posts will be compatible.

### Option 2: Gradual Migration
If customizing the old code, follow these mappings:

#### Settings & Options
**Old:**
```php
get_option('donations_cpt_active_color', '#d8bd6a')
get_option('donations_cpt_product_id', 0)
```

**New:**
```php
get_option('charity_active_color', '#d8bd6a')
get_option('charity_donation_product_id', 0)
```

**Updated option keys:**
| Old Key | New Key |
|---------|---------|
| `donations_cpt_active_color` | `charity_active_color` |
| `donations_cpt_button_color` | `charity_button_color` |
| `donations_cpt_button_text` | `charity_button_text` |
| `donations_cpt_custom_placeholder` | `charity_custom_placeholder` |
| `donations_cpt_product_id` | `charity_donation_product_id` |

**Migration SQL:**
```sql
UPDATE wp_options SET option_name = 'charity_active_color' 
  WHERE option_name = 'donations_cpt_active_color';
-- Repeat for other keys
```

#### Meta Keys (Post Data)
Meta keys remain unchanged:
- `_donation_rows_arr` — Array of donation amounts
- `_donation_allow_custom` — Boolean for custom amounts
- `_donation_custom_label` — Custom amount label text

#### Shortcodes
Both `[donation_buttons]` and `[quickdonation]` are now unified in the new system but maintain backward compatibility.

**Usage unchanged:**
```
[donation_buttons id="42"]
[quickdonation id="42" show_id="1"]
[donation_goal id="42"]
```

#### JavaScript Global Variables
**Old:**
```javascript
window.DonationsCPT                // Global config object
window._dw_last_post_id           // Last post ID
```

**New:**
```javascript
window.CharityDonations           // New config object
window.CharityDonationForm        // Module reference
```

**Localized data:**
```javascript
// Old
DonationsCPT.ajax_url
DonationsCPT.nonce
DonationsCPT.post_id

// New
CharityDonations.ajax_url
CharityDonations.nonce
```

#### AJAX Endpoint
Endpoint unchanged:
- **Action:** `donation_add_to_cart`
- **Method:** POST
- **Nonce:** Now passed in JavaScript localization

**Request (old):**
```javascript
$.post(ajaxUrl(), {
    action: 'donation_add_to_cart',
    nonce: DonationsCPT.nonce,
    post_id: pid,
    amount: amt
});
```

**Request (new):**
```javascript
// Handled internally by DonationForm module
// Or use directly:
$.ajax({
    url: CharityDonations.ajax_url,
    data: {
        action: 'donation_add_to_cart',
        nonce: CharityDonations.nonce,
        post_id: pid,
        amount: amt,
        description: desc
    }
});
```

#### PHP Hooks
**New action hooks to use:**

```php
// When a donation is saved
add_action( 'charity_donation_saved', function( $post_id, $rows ) {
    // Do something with donation data
}, 10, 2 );

// When settings are updated
add_action( 'charity_settings_saved', function( $settings ) {
    // React to settings change
});

// Plugin initialization complete
add_action( 'charity_plugin_loaded', function() {
    // Extension code here
});
```

**New filter hooks to use:**

```php
// Modify cart item data before adding
add_filter( 'charity_donation_cart_data', function( $data, $post_id, $amount ) {
    $data['my_field'] = 'my_value';
    return $data;
}, 10, 3 );

// Modify AJAX response
add_filter( 'charity_donation_add_response', function( $response, $product_id, $amount ) {
    $response['custom'] = true;
    return $response;
}, 10, 3 );

// Output before donate button
add_filter( 'charity_donation_before_button', function( $html, $post_id, $data ) {
    return $html . '<p>Additional info</p>';
}, 10, 3 );

// Output after donate button
add_filter( 'charity_donation_after_button', function( $html, $post_id, $data ) {
    return $html;
}, 10, 3 );
```

## Backward Compatibility

The plugin maintains compatibility with:
- ✅ Existing donation posts and metadata
- ✅ WooCommerce integration
- ✅ Shortcode usage
- ✅ Theme overrides via CSS
- ✅ Cart fragments and mini-cart

## What Changed

### Code Organization

**Frontend shortcode rendering:**
- **Old:** Procedural code in `donations-cpt.php`
- **New:** `Charity_Shortcodes` class in `includes/class-shortcodes.php`

**Admin metabox:**
- **Old:** Procedural code in `admin-metabox.php`
- **New:** `Charity_Metabox_Handler` class in `includes/class-metabox-handler.php`

**Settings page:**
- **Old:** Procedural code in `donations-cpt.php`
- **New:** `Charity_Settings_Handler` class in `includes/class-settings-handler.php`

**AJAX handler:**
- **Old:** Mixed into CPT registration
- **New:** `Charity_Donations_CPT::handle_add_to_cart()` method

### JavaScript Changes

**Frontend JS (donations.js):**
- Now uses module pattern (`DonationForm` object)
- Better error handling with actual error messages
- Proper event delegation
- No global variables pollution

**Admin JS (metabox.js):**
- Cleaner code with `DonationMetabox` object
- Better number input formatting
- Improved row management

### Security Improvements

1. **Nonce validation** — All AJAX calls now properly verify nonce
2. **Escape output** — All dynamic output uses `esc_html()`, `esc_attr()`, etc.
3. **Sanitize input** — All $_POST data is sanitized before use
4. **Capability checks** — Admin operations check `manage_options`
5. **Type casting** — Explicit type casting for all integer values

## Testing Checklist

After migration, verify:

- [ ] Donation posts display correctly on frontend
- [ ] Shortcodes render with correct styling
- [ ] Card selection works and highlights
- [ ] Custom amount input works
- [ ] Add to cart works via AJAX
- [ ] Cart updates without page reload
- [ ] Cart fragments refresh correctly
- [ ] Settings page loads and saves
- [ ] Donation metabox allows add/remove rows
- [ ] Custom amount option checkbox works
- [ ] No JavaScript errors in browser console
- [ ] Plugin activates without errors

## Common Issues

**Issue: "Charity plugin not found" in admin**
- Clear browser cache
- Deactivate and reactivate the plugin
- Check `includes/` folder exists

**Issue: AJAX returning 404**
- Verify `charity_plugin.php` is the main file
- Check `wp-admin/admin-ajax.php` is accessible
- Review nonce data is being sent

**Issue: Settings not saving**
- Check user has `manage_options` capability
- Verify nonce is present in form
- Check browser console for JavaScript errors

**Issue: Old shortcodes not working**
- Shortcodes should still work but check breakage
- Verify new plugin file is activated
- Check post IDs in shortcodes are correct

## Support

For issues or questions:
1. Check the main [README.md](README.md)
2. Review inline code comments
3. Check browser developer tools for errors
4. Review WordPress error logs (`wp-content/debug.log`)

## Rollback Plan

If you need to rollback:

1. Deactivate **Charity Donation Plugin**
2. Restore old `donations-cpt.php` and related files
3. Reactivate old plugin
4. Note: Donation posts and metadata will be preserved
