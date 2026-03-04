# Charity Donation Plugin

A complete WordPress donation system with WooCommerce integration, featuring donation forms, custom amounts, and a floating cart drawer.

## ✨ Features

- **Donation Custom Post Type** — Create and manage donation campaigns
- **Flexible Donation Amounts** — Pre-set amounts with optional custom input
- **WooCommerce Integration** — Seamless cart and checkout flow
- **Floating Cart Drawer** — Beautiful off-canvas shopping cart
- **Shortcodes** — Multiple layout options for embedding donations
- **Customizable Appearance** — Colors, button text, and more
- **Admin Settings** — Configure products, colors, and defaults
- **Accessibility** — ARIA labels, keyboard navigation, semantic HTML

## 📦 Installation

1. Place the plugin folder in `/wp-content/plugins/`
2. Activate **Charity Donation Plugin** from the Plugins page
3. Configure settings from **Donations → Settings**
4. Create donation campaigns from **Donations** menu

## ⚙️ Configuration

### Plugin Settings

Navigate to **Donations → Settings** to configure:

- **Donation Product** — Select the WooCommerce product for collecting donations
- **Active Card Color** — Color when donation amount is selected (hex code)
- **Button Color** — Donate button background color (hex code)
- **Button Text** — Custom button label
- **Custom Amount Placeholder** — Input field placeholder text

### Creating a Donation

1. Go to **Donations → Add New**
2. Add a title and description
3. Set donation amounts in the **Donation Options** metabox:
   - **Amount** — The donation value (e.g., `25`)
   - **Description** — What the donation supports (e.g., "Education Programs")
4. Enable **Allow custom amount** if users can enter any amount
5. Publish

## 🎯 Shortcodes

### `[donation_buttons]`
Full donation form with thumbnail, title, and cards in 3-column grid layout.

**Attributes:**
- `id="123"` — Donation post ID (default: current post)
- `button_text="GIVE NOW"` — Custom button text
- `show_id="1"` — Show donation ID (default: 0)
- `id_label="Campaign:"` — Label for ID display

**Example:**
```
[donation_buttons id="42"]
[donation_buttons button_text="Support Us"]
```

### `[quickdonation]`
Horizontal donation form (single-row layout) for sidebars or inline content.

**Attributes:**
Same as `[donation_buttons]`

**Example:**
```
[quickdonation id="42" show_id="1" id_label="ID:"]
```

### `[donation_goal]`
Progress bar or goal widget (if the goal module is enabled).

**Attributes:**
- `id="123"` — Donation post ID

## 🏗️ Plugin Architecture

### New Class Structure

**`includes/class-donations-cpt.php`**
- Registers the Donation post type
- Handles AJAX add-to-cart requests
- Provides utility methods

**`includes/class-metabox-handler.php`**
- Admin metabox UI for donation amounts
- Saves and validates donation rows

**`includes/class-settings-handler.php`**
- Admin settings page
- Manages plugin options
- Product selection

**`includes/class-shortcodes.php`**
- Consolidates all shortcode logic
- Handles post ID resolution
- Manages asset enqueueing

### JavaScript Modules

**`assets/js/metabox.js`**
- Admin metabox functionality
- Add/remove donation rows
- Number input formatting

**`assets/js/donations.js`**
- Frontend donation form interactions
- Card selection and custom amount handling
- AJAX submission with proper error handling
- Cart fragment management

## 🔒 Security Features

- ✅ Nonce verification on all AJAX requests
- ✅ Capability checks (manage_options)
- ✅ Data sanitization & validation
- ✅ HTML escaping on output
- ✅ WP-Rest API optional exposure

## 🎨 Styling

Donation forms are styled with Bootstrap 5 classes:
- `.donation-wrapper` — Main form container
- `.donation-card` — Individual amount option
- `.donation-card.active` — Selected state
- `.donation-custom-wrap` — Custom amount input
- `.donation-submit` — Submit button
- `.donation-msg` — Status messages

Customize colors via **Donations → Settings** or override CSS in your theme.

## 🚀 Hooks & Filters

### Actions

**`charity_plugin_loaded`**
Fires after plugin initialization.
```php
add_action( 'charity_plugin_loaded', function() {
    // Your code here
});
```

**`charity_donation_saved`**
Fires when a donation post is saved.
```php
add_action( 'charity_donation_saved', function( $post_id, $rows ) {
    // $rows = array of donation amounts
}, 10, 2 );
```

**`charity_settings_saved`**
Fires when settings are updated.
```php
add_action( 'charity_settings_saved', function( $settings ) {
    // $settings = array of all settings
});
```

### Filters

**`charity_donation_cart_data`**
Modify cart item metadata before adding to cart.
```php
add_filter( 'charity_donation_cart_data', function( $data, $post_id, $amount ) {
    $data['custom_field'] = 'value';
    return $data;
}, 10, 3 );
```

**`charity_donation_add_response`**
Modify AJAX response after successful add-to-cart.
```php
add_filter( 'charity_donation_add_response', function( $response, $product_id, $amount ) {
    $response['custom'] = 'value';
    return $response;
}, 10, 3 );
```

**`charity_donation_before_button`**
Output HTML before the donate button.
```php
add_filter( 'charity_donation_before_button', function( $html, $post_id, $data ) {
    return $html . '<p>Special message</p>';
}, 10, 3 );
```

**`charity_donation_after_button`**
Output HTML after the donate button.

## 🧪 Testing

### Manual Testing Checklist

- [ ] Create a donation post with 3+ amounts
- [ ] Enable custom amount option
- [ ] Test card selection and highlighting
- [ ] Test custom amount input validation
- [ ] Test Enter key submitting form
- [ ] Test AJAX submission with mini-cart
- [ ] Test cart fragment refresh
- [ ] Test error handling (missing product, etc.)
- [ ] Verify accessibility with keyboard navigation
- [ ] Test on mobile devices

### Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 📋 Refactoring Summary

**Version 2.6.0** includes major refactoring:

✅ **Object-Oriented Architecture** — Replaced procedural code with class-based structure  
✅ **Module Pattern JS** — Encapsulated frontend code with proper namespacing  
✅ **Consolidated Shortcuts** — Unified `[donation_buttons]` and `[quickdonation]`  
✅ **Enhanced Security** — Strict nonce verification, escaping, and sanitization  
✅ **Better Error Handling** — AJAX error messages and logging  
✅ **Extensibility** — Action/filter hooks for developers  
✅ **Code Standards** — Followed WordPress coding standards (WPCS)  
✅ **Documentation** — Comprehensive README and inline comments  

## 🐛 Troubleshooting

### Donation button not appearing
- Check that the post has at least one donation amount configured
- Verify the shortcode post ID is correct
- Ensure CSS is enqueued (check browser console for errors)

### Products dropdown empty
- Make sure WooCommerce is active
- Go to **Donations → Settings** and create/select a product
- Simple or Virtual products work best

### Cart not updating
- Check that `wc-cart-fragments` is loaded
- Verify WooCommerce mini-cart widget or code exists on page
- Clear browser cache and hard refresh

### JavaScript errors
- Open browser console (F12) for specific error messages
- Ensure jQuery is loaded before plugin JS
- Check for conflicting plugins

### "Invalid donation post." error

- This message appears inside the donation form area when the AJAX add-to-cart
  request cannot verify that the supplied `post_id` corresponds to a valid
  donation campaign.  It is **not** generated by WooCommerce.

  **Typical causes:**
  1. The shortcode is rendered on a normal page without an explicit `id` attr,
      so the page ID (which isn't a donation) is sent.
  2. A caching layer or custom code has stripped `data-post-id` from the form
      wrapper, causing the request to send `post_id=0`.
  3. A mistyped `[donation_buttons id="…"]` value points at a non-donation
      post.

  **Solution:** place the shortcode on a donation post or supply a valid
  donation ID; the plugin now also verifies the post type and will reject other
  post types with the same message.

## 📚 Additional Resources

- [WordPress Plugin Documentation](https://developer.wordpress.org/plugins/)
- [WooCommerce Integration](https://woocommerce.com/)
- [Bootstrap 5 Documentation](https://getbootstrap.com/)

## 📄 License

GPL-2.0-or-later — See LICENSE file for details

## 👨‍💻 Author

Design IT Wise — [designitwise.com](https://designitwise.com)