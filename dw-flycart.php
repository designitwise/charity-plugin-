<?php
/**
 * DW Flycart Module - Floating Mini Cart for WooCommerce
 *
 * @package CharityPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-loading
if ( defined( 'DW_FLYCART_LOADED' ) ) {
	return;
}
define( 'DW_FLYCART_LOADED', '1.0.0' );

/**
 * DW Flycart - Floating cart drawer
 */
add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Register cart toggle shortcode & template tag
	if ( ! function_exists( 'dw_flycart_toggle' ) ) {
		function dw_flycart_toggle( $label = 'Cart', $echo = true ) {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return '';
			}

			$count = (int) WC()->cart->get_cart_contents_count();
			$html  = '<a href="#" class="dw-flycart-toggle" aria-controls="dw-flycart" aria-expanded="false">';
			$html .= '<span class="dw-cart-icon" aria-hidden="true">🛒</span>';
			$html .= '<span class="dw-cart-count">' . (int) $count . '</span>';
			$html .= '</a>';

			if ( $echo ) {
				echo wp_kses_post( $html );
				return;
			}
			return $html;
		}
	}

	add_shortcode( 'flycart_toggle', function( $atts ) {
		$a = shortcode_atts( [ 'label' => 'Cart' ], $atts, 'flycart_toggle' );
		return dw_flycart_toggle( $a['label'], false );
	} );

	// Register off-canvas panel (single location)
	add_action( 'wp_footer', function() {
		if ( is_admin() || ! function_exists( 'woocommerce_mini_cart' ) ) {
			return;
		}
		?>
		<div class="dw-flycart-overlay" tabindex="-1" hidden></div>
		<aside id="dw-flycart" class="dw-flycart" aria-hidden="true" aria-labelledby="dw-flycart-title">
			<header class="dw-flycart-header">
				<h3 id="dw-flycart-title"><?php esc_html_e( 'Your Basket', 'charity-plugin' ); ?></h3>
				<button type="button" class="dw-flycart-close" aria-label="<?php esc_attr_e( 'Close cart', 'charity-plugin' ); ?>">×</button>
			</header>
			<div class="dw-flycart-body">
				<div class="dw-mini-cart"></div>
			</div>
		</aside>
		<?php
	}, 9999 );

	// Enqueue assets
	add_action( 'wp_enqueue_scripts', function() {
		if ( is_admin() ) {
			return;
		}

		$base_url  = plugin_dir_url( CHARITY_PLUGIN_FILE );
		$base_path = plugin_dir_path( CHARITY_PLUGIN_FILE );

		$css_path = 'assets/css/dw-flycart.css';
		$js_path  = 'assets/js/dw-flycart.js';

		if ( file_exists( $base_path . $css_path ) ) {
			wp_enqueue_style(
				'dw-flycart-style',
				$base_url . $css_path,
				[],
				filemtime( $base_path . $css_path )
			);
		}

		if ( file_exists( $base_path . $js_path ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wc-cart-fragments' );

			wp_enqueue_script(
				'dw-flycart-js',
				$base_url . $js_path,
				[ 'jquery', 'wc-cart-fragments' ],
				filemtime( $base_path . $js_path ),
				true
			);
		}
	} );

	// Keep mini-cart fresh via fragments
	add_filter( 'woocommerce_add_to_cart_fragments', function( $frags ) {
		ob_start();
		woocommerce_mini_cart();
		$frags['div.dw-mini-cart'] = ob_get_clean();

		$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
		$frags['span.dw-cart-count'] = '<span class="dw-cart-count">' . (int) $count . '</span>';

		return $frags;
	} );
} );