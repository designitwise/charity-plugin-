// Load AJAX handlers for flyout billing form and mini billing details
require_once __DIR__ . '/includes/ajax-flyout-billing.php';
require_once __DIR__ . '/includes/ajax-mini-billing-details.php';
<?php
/**
 * Charity Donation Plugin
 *
 * @package  CharityPlugin
 * @version  2.6.0
 * @author   Design IT Wise
 * @license  GPL-2.0-or-later
 * @link     https://github.com/designitwise/charity-plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Charity Donation Plugin
 * Plugin URI:        https://github.com/designitwise/charity-plugin
 * Description:       Complete donation system for WordPress with WooCommerce integration
 * Version:           2.6.0
 * Author:            Design IT Wise
 * Author URI:        https://designitwise.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       charity-plugin
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires WP:       5.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'CHARITY_PLUGIN_VERSION', '2.6.0' );
define( 'CHARITY_PLUGIN_FILE', __FILE__ );
define( 'CHARITY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHARITY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Prevent double-loading
if ( defined( 'CHARITY_PLUGIN_LOADED' ) ) {
	return;
}
define( 'CHARITY_PLUGIN_LOADED', true );


/**
 * Bootstrap the plugin
 */
add_action( 'plugins_loaded', function() {
	// Load plugin text domain
	load_plugin_textdomain( 'charity-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Include core classes
	require_once CHARITY_PLUGIN_DIR . 'includes/class-donations-cpt.php';
	require_once CHARITY_PLUGIN_DIR . 'includes/class-metabox-handler.php';
	require_once CHARITY_PLUGIN_DIR . 'includes/class-settings-handler.php';
	require_once CHARITY_PLUGIN_DIR . 'includes/class-shortcodes.php';

	// Include flycart (if present)
	if ( file_exists( CHARITY_PLUGIN_DIR . 'dw-flycart.php' ) ) {
		require_once CHARITY_PLUGIN_DIR . 'dw-flycart.php';
	}

	// Include display controls
	require_once CHARITY_PLUGIN_DIR . 'display-controls.php';

	// Initialize core features
	Charity_Donations_CPT::init();
	Charity_Metabox_Handler::init();
	Charity_Settings_Handler::init();
	Charity_Shortcodes::init();

	// Fire action for extensions
	do_action( 'charity_plugin_loaded' );
});

// Enqueue admin styles & scripts
add_action( 'admin_enqueue_scripts', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'donation' ) {
		return;
	}

	wp_enqueue_script(
		'charity-admin-js',
		CHARITY_PLUGIN_URL . 'assets/js/admin.js',
		[ 'jquery' ],
		CHARITY_PLUGIN_VERSION,
		true
	);
});

// Enqueue frontend assets
add_action( 'wp_enqueue_scripts', function() {
	$css_path = CHARITY_PLUGIN_DIR . 'assets/css/donations.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'charity-frontend',
			CHARITY_PLUGIN_URL . 'assets/css/donations.css',
			[],
			filemtime( $css_path )
		);
	}

	$js_path = CHARITY_PLUGIN_DIR . 'assets/js/donations.js';
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'charity-frontend',
			CHARITY_PLUGIN_URL . 'assets/js/donations.js',
			[ 'jquery' ],
			filemtime( $js_path ),
			true
		);

		// Localize for AJAX
		$settings = Charity_Settings_Handler::get_all_settings();
		wp_localize_script( 'charity-frontend', 'CharityDonations', [
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'charity_donation_nonce' ),
				'extras'      => $settings['extras'],
				'show_extras' => (int) $settings['show_extras'],
		]);
	}
});

// Register activation & deactivation hooks
register_activation_hook( __FILE__, function() {
	// Clear any cached options
	wp_cache_flush();
});

register_deactivation_hook( __FILE__, function() {
	wp_cache_flush();
});
