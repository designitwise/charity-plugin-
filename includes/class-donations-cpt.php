<?php
/**
 * Donations CPT Registration & Setup
 *
 * @package CharityPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Charity_Donations_CPT {

	/**
	 * Initialize the CPT
	 */
	public static function init() {
		add_action( 'init', [ self::class, 'register_post_type' ] );
		add_action( 'init', [ self::class, 'register_ajax_handlers' ] );
		// Apply dynamic pricing for donation line items
		add_action( 'woocommerce_before_calculate_totals', [ self::class, 'apply_donation_price' ] );
		// Remove extras from cart when disabled in settings
		add_action( 'woocommerce_cart_loaded_from_session', [ self::class, 'maybe_clear_disabled_extras' ] );
		// Cart display & persistence
		add_filter( 'woocommerce_cart_item_thumbnail', [ self::class, 'cart_item_thumbnail' ], 10, 3 );
		add_filter( 'woocommerce_get_item_data', [ self::class, 'cart_item_display' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'persist_order_line_item' ], 10, 3 );
		add_filter( 'woocommerce_order_item_thumbnail', [ self::class, 'order_item_thumbnail' ], 10, 3 );
		// Checkout review section - display extras for guest/logged-in users
		add_action( 'woocommerce_review_order_after_order_total', [ self::class, 'render_checkout_extras' ], 5 );
		add_action( 'woocommerce_checkout_after_order_review', [ self::class, 'render_checkout_extras_fallback' ], 10 );
		// Debug: Try different WooCommerce hooks
		add_action( 'woocommerce_before_checkout_form', function() {
			echo '<!-- DEBUG: woocommerce_before_checkout_form fired -->';
		});
		add_action( 'woocommerce_checkout_before_order_review', function() {
			echo '<!-- DEBUG: woocommerce_checkout_before_order_review fired -->';
		});
		add_action( 'wp_footer', function() {
			if ( function_exists( 'is_checkout' ) && is_checkout() ) {
				echo '<!-- DEBUG: IS ON CHECKOUT PAGE -->';
			}
			echo '<!-- CHARITY PLUGIN LOADED ON PAGE -->';
		});
	}

	/**
	 * Register the Donation post type
	 */
	public static function register_post_type() {
		register_post_type( 'donation', [
			'label'         => esc_html__( 'Donations', 'charity-plugin' ),
			'public'        => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'supports'      => [ 'title', 'editor', 'thumbnail' ],
			'menu_icon'     => 'dashicons-heart',
			'has_archive'   => true,
			'rewrite'       => [ 'slug' => 'donations' ],
			'taxonomies'    => [ 'category', 'post_tag' ],
		]);
	}

	/**
	 * Register AJAX handlers for donation cart operations
	 */
	public static function register_ajax_handlers() {
		add_action( 'wp_ajax_donation_add_to_cart', [ self::class, 'handle_add_to_cart' ] );
		add_action( 'wp_ajax_nopriv_donation_add_to_cart', [ self::class, 'handle_add_to_cart' ] );
		add_action( 'wp_ajax_charity_update_checkout_extras', [ self::class, 'handle_checkout_extras_update' ] );
		add_action( 'wp_ajax_nopriv_charity_update_checkout_extras', [ self::class, 'handle_checkout_extras_update' ] );
	}

	/**
	 * Remove any donation extras items from the cart if extras are disabled in settings.
	 * Fires when cart is loaded from session to keep cart clean.
	 */
	public static function maybe_clear_disabled_extras( $cart ) {
		if ( ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		$show_extras = (int) get_option( 'charity_show_extras', 1 );
		if ( $show_extras ) {
			return; // nothing to do
		}

		// iterate and remove extras
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['donation_extra'] ) ) {
				$cart->remove_cart_item( $key );
			}
		}
	}

	/**
	 * Handle AJAX donation add-to-cart request
	 */
	public static function handle_add_to_cart() {
		// Verify nonce
		check_ajax_referer( 'charity_donation_nonce', 'nonce' );

		// Get and sanitize inputs
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '';
		$extras = isset( $_POST['extras'] ) ? json_decode( sanitize_text_field( $_POST['extras'] ), true ) : [];

		// Validate inputs
		if ( ! $post_id ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Invalid donation post.', 'charity-plugin' ),
			], 400 );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'donation' !== $post->post_type ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Invalid donation post.', 'charity-plugin' ),
			], 400 );
		}

		if ( $amount <= 0 ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Invalid donation amount.', 'charity-plugin' ),
			], 400 );
		}

		// Check WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'WooCommerce is not active.', 'charity-plugin' ),
			], 503 );
		}

		// Get or create donation product
		$product_id = self::get_donation_product();
		if ( ! $product_id ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Donation product not configured.', 'charity-plugin' ),
			], 500 );
		}

		// Add main donation to cart with custom metadata
		$cart_item_data = [
			'donation_post_id' => $post_id,
			'donation_amount'  => $amount,
			'donation_desc'    => $description,
		];

		// Allow filtering cart data
		$cart_item_data = apply_filters( 'charity_donation_cart_data', $cart_item_data, $post_id, $amount );

		$item_key = WC()->cart->add_to_cart(
			$product_id,
			1,
			[],
			[],
			$cart_item_data
		);

		if ( ! $item_key ) {
			wp_send_json_error( [
				'message' => esc_html__( 'Could not add to cart.', 'charity-plugin' ),
			], 500 );
		}

		// Add selected extras to cart
		if ( ! empty( $extras ) && is_array( $extras ) ) {
			foreach ( $extras as $extra ) {
				$extra_label = isset( $extra['label'] ) ? sanitize_text_field( $extra['label'] ) : '';
				$extra_amount = isset( $extra['amount'] ) ? floatval( $extra['amount'] ) : 0;

				if ( $extra_label && $extra_amount > 0 ) {
					// avoid duplicates in cart
					$exists = false;
					foreach ( WC()->cart->get_cart() as $ci ) {
						if ( ! empty( $ci['donation_extra'] ) &&
							sanitize_text_field( $ci['donation_desc'] ) === $extra_label &&
							(float) $ci['donation_amount'] === $extra_amount ) {
							$exists = true;
							break;
						}
					}
					if ( ! $exists ) {
						$extra_cart_data = [
							'donation_post_id' => $post_id,
							'donation_amount'  => $extra_amount,
							'donation_desc'    => $extra_label,
							'donation_extra'   => '1',
						];

						WC()->cart->add_to_cart(
							$product_id,
							1,
							[],
							[],
							$extra_cart_data
						);
					}
				}
			}
		}

		// Prepare response
		$response = [
			'message'  => esc_html__( 'Added to cart.', 'charity-plugin' ),
			'redirect' => wc_get_checkout_url(),
		];

		$response = apply_filters( 'charity_donation_add_response', $response, $product_id, $amount );

		wp_send_json_success( $response );
	}

	/**
	 * Get or retrieve the donation product
	 *
	 * @return int Product ID or 0
	 */
	public static function get_donation_product() {
		$product_id = (int) get_option( 'charity_donation_product_id', 0 );

		if ( $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				return $product_id;
			}
		}

		return 0;
	}

	/**
	 * Apply donation amount as the cart item price when present.
	 *
	 * @param WC_Cart $cart Cart object
	 */
	public static function apply_donation_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( empty( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		$donation_pid = (int) get_option( 'charity_donation_product_id', 0 );
		if ( ! $donation_pid ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => &$cart_item ) {
			if ( empty( $cart_item['donation_amount'] ) ) {
				continue;
			}

			// Only modify the configured donation product
			if ( intval( $cart_item['product_id'] ) !== $donation_pid ) {
				continue;
			}

			$price = (float) $cart_item['donation_amount'];
			if ( $price > 0 && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
				$cart_item['data']->set_price( $price );
			}
		}
	}

	/**
	 * Replace cart item thumbnail with the donation post's featured image when available.
	 *
	 * @param string $thumbnail_html Current thumbnail HTML
	 * @param array  $cart_item Cart item data
	 * @param string $cart_item_key Cart item key
	 * @return string
	 */
	public static function cart_item_thumbnail( $thumbnail_html, $cart_item, $cart_item_key ) {
		// Support both legacy and new keys
		$post_id = 0;
		if ( ! empty( $cart_item['donation_post_id'] ) ) {
			$post_id = (int) $cart_item['donation_post_id'];
		} elseif ( ! empty( $cart_item['donation_post'] ) ) {
			$post_id = (int) $cart_item['donation_post'];
		}
		if ( ! $post_id ) {
			return $thumbnail_html;
		}

		$img = get_the_post_thumbnail( $post_id, 'woocommerce_thumbnail', [ 'class' => 'donation-post-thumb', 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] );
		return $img ? $img : $thumbnail_html;
	}

	/**
	 * Show donation title/amount/description under cart item.
	 *
	 * @param array $item_data Existing item data lines
	 * @param array $cart_item Cart item array
	 * @return array
	 */
	public static function cart_item_display( $item_data, $cart_item ) {
		$lines = [];
		$post_id = ! empty( $cart_item['donation_post_id'] ) ? (int) $cart_item['donation_post_id'] : (! empty( $cart_item['donation_post'] ) ? (int) $cart_item['donation_post'] : 0);
		$is_extra = ! empty( $cart_item['donation_extra'] );

		// Show campaign title only for main donation (not for extras)
		if ( $post_id && ! $is_extra ) {
			$lines[] = esc_html( get_the_title( $post_id ) );
		}

		// Show amount
		if ( ! empty( $cart_item['donation_amount'] ) ) {
			$lines[] = function_exists( 'wc_price' ) ? wc_price( (float) $cart_item['donation_amount'] ) : esc_html( $cart_item['donation_amount'] );
		}

		// Show description (for extras, this is the extra label)
		if ( ! empty( $cart_item['donation_desc'] ) ) {
			$label = esc_html( $cart_item['donation_desc'] );
			if ( $is_extra ) {
				$label = '✨ ' . $label;
			}
			$lines[] = $label;
		}

		if ( $lines ) {
			$display = implode( '<br>', $lines );
			$item_data[] = [
				'key'     => '',
				'value'   => wp_kses_post( $display ),
				'display' => wp_kses_post( $display ),
			];
		}

		return $item_data;
	}

	/**
	 * Persist donation metadata to order line item on checkout.
	 *
	 * @param WC_Order_Item_Product $item Order line item
	 * @param string $cart_item_key
	 * @param array $values Cart item values
	 */
	public static function persist_order_line_item( $item, $cart_item_key, $values ) {
		$post_id = ! empty( $values['donation_post_id'] ) ? (int) $values['donation_post_id'] : ( ! empty( $values['donation_post'] ) ? (int) $values['donation_post'] : 0 );
		$is_extra = ! empty( $values['donation_extra'] );

		// Store the campaign title
		if ( $post_id ) {
			$item->add_meta_data( __( 'Donation Campaign', 'charity-plugin' ), get_the_title( $post_id ), true );
			$item->add_meta_data( 'donation_post', $post_id, true );
		}

		// Store extra flag if applicable
		if ( $is_extra ) {
			$item->add_meta_data( __( 'Type', 'charity-plugin' ), '✨ Extra', true );
			$item->add_meta_data( 'donation_extra', '1', true );
		}

		// Store description/label
		if ( isset( $values['donation_desc'] ) ) {
			$meta_label = $is_extra ? __( 'Extra Item', 'charity-plugin' ) : __( 'Description', 'charity-plugin' );
			$item->add_meta_data( $meta_label, sanitize_text_field( $values['donation_desc'] ), true );
		}

		// Store amount
		if ( isset( $values['donation_amount'] ) ) {
			$item->add_meta_data( __( 'Amount', 'charity-plugin' ), function_exists( 'wc_price' ) ? wc_price( (float) $values['donation_amount'] ) : $values['donation_amount'], true );
		}
	}

	/**
	 * Replace order item thumbnail (used on order details/emails) with donation featured image
	 * when the order item carries a `donation_post` meta.
	 *
	 * @param string $thumbnail_html Current thumbnail HTML
	 * @param WC_Order_Item_Product $item Order item
	 * @param string $item_id
	 * @return string
	 */
	public static function order_item_thumbnail( $thumbnail_html, $item, $item_id ) {
		$post_id = 0;
		if ( is_callable( [ $item, 'get_meta' ] ) ) {
			$post_id = (int) $item->get_meta( 'donation_post', true );
		}
		if ( ! $post_id ) {
			return $thumbnail_html;
		}
		$img = get_the_post_thumbnail( $post_id, 'woocommerce_thumbnail', [ 'class' => 'donation-post-thumb', 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ] );
		return $img ? $img : $thumbnail_html;
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get option value with fallback
	 *
	 * @param string $key Option key
	 * @param mixed  $default Default value
	 * @return mixed
	 */
	public static function get_option( $key, $default = '' ) {
		$value = get_option( $key, null );
		return $value !== null ? $value : $default;
	}

	/**
	 * Sanitize and validate hex color
	 *
	 * @param string $color Color code
	 * @param string $fallback Fallback color
	 * @return string
	 */
	public static function sanitize_hex_color( $color, $fallback = '#000000' ) {
		$color = trim( (string) $color );
		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ? $color : $fallback;
	}

	/**
	 * Display extras options on checkout review page
	 */
	public static function render_checkout_extras() {
		echo '<!-- DEBUG: render_checkout_extras called -->';
		
		// Get settings
		if ( ! class_exists( 'Charity_Settings_Handler' ) ) {
			echo '<!-- DEBUG: Charity_Settings_Handler class not found -->';
			return;
		}

		$settings = Charity_Settings_Handler::get_all_settings();
		
		// Debug: Check what we got
		error_log( 'CHARITY DEBUG: show_extras=' . intval( $settings['show_extras'] ) . ' extras count=' . count( $settings['extras'] ) );
		echo '<!-- DEBUG: show_extras=' . intval( $settings['show_extras'] ) . ' extras count=' . count( $settings['extras'] ) . ' -->';
		
		// Check if extras are enabled and exist
		if ( empty( $settings['show_extras'] ) || empty( $settings['extras'] ) || ! is_array( $settings['extras'] ) ) {
			echo '<!-- DEBUG: Returning early - show_extras or extras empty -->';
			return;
		}

		?>
		<tr id="charity-checkout-extras-row">
			<td colspan="2">
				<div id="charity-checkout-extras">
					<h3><?php esc_html_e( 'Support Us Further', 'charity-plugin' ); ?></h3>
					<p class="charity-checkout-extras-intro"><?php esc_html_e( 'Add optional extras to your donation:', 'charity-plugin' ); ?></p>
					<div class="charity-checkout-extras-list">
						<?php foreach ( $settings['extras'] as $extra ) : ?>
							<label class="charity-checkout-extra-item">
								<input type="checkbox" class="charity-checkout-extra-checkbox" 
								       data-label="<?php echo esc_attr( $extra['label'] ); ?>" 
								       data-amount="<?php echo esc_attr( $extra['amount'] ); ?>">
								<span class="charity-checkout-extra-label"><?php echo esc_html( $extra['label'] ); ?></span>
								<span class="charity-checkout-extra-price">
									+<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $extra['amount'] ) ) : esc_html( $extra['amount'] ); ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</td>
		</tr>
		<script>
		( function( $ ) {
			$( document ).on( 'change', '.charity-checkout-extra-checkbox', function() {
				const $checkbox = $( this );
				const label = $checkbox.data( 'label' );
				const amount = parseFloat( $checkbox.data( 'amount' ) );
				const isChecked = $checkbox.is( ':checked' );

				$.ajax( {
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					method: 'POST',
					dataType: 'json',
					data: {
						action: 'charity_update_checkout_extras',
						nonce: '<?php echo esc_attr( wp_create_nonce( 'charity_donation_nonce' ) ); ?>',
						label: label,
						amount: amount,
						add: isChecked ? 1 : 0,
					},
					success: function( response ) {
						if ( response.success ) {
							$( document.body ).trigger( 'update_checkout' );
						}
					},
					error: function() {
						$checkbox.prop( 'checked', ! isChecked );
						alert( '<?php esc_attr_e( 'Failed to update extras. Please try again.', 'charity-plugin' ); ?>' );
					},
				} );
			} );
		} )( jQuery );
		</script>
		<style>
		#charity-checkout-extras-row {
			background: #f9f9f9;
		}
		#charity-checkout-extras {
			padding: 15px;
		}
		#charity-checkout-extras h3 {
			margin: 0 0 10px 0;
			color: #333;
			font-size: 1.1em;
		}
		.charity-checkout-extras-intro {
			margin: 0 0 15px 0;
			color: #666;
			font-size: 0.9em;
		}
		.charity-checkout-extra-item {
			display: flex;
			align-items: center;
			padding: 8px 0;
			cursor: pointer;
		}
		.charity-checkout-extra-item input[type="checkbox"] {
			margin-right: 10px;
			margin-top: 2px;
			cursor: pointer;
			width: 18px;
			height: 18px;
			flex-shrink: 0;
		}
		.charity-checkout-extra-label {
			flex: 1;
			color: #333;
		}
		.charity-checkout-extra-price {
			color: #27ae60;
			font-weight: bold;
			margin-left: 10px;
			white-space: nowrap;
		}
		</style>
		<?php
	}

	/**
	 * Handle AJAX request to add/remove extras on checkout
	 */
	public static function handle_checkout_extras_update() {
		check_ajax_referer( 'charity_donation_nonce', 'nonce' );

		$label = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
		$add = isset( $_POST['add'] ) ? intval( $_POST['add'] ) : 0;

		if ( ! $label || $amount <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid extra item.' ] );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( [ 'message' => 'WooCommerce not active.' ] );
		}

		$product_id = (int) get_option( 'charity_donation_product_id', 0 );
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => 'Donation product not configured.' ] );
		}

		if ( $add ) {
			// Add extra to cart, but avoid duplicates
			$already = false;
			foreach ( WC()->cart->get_cart() as $ci ) {
				if ( ! empty( $ci['donation_extra'] ) &&
					sanitize_text_field( $ci['donation_desc'] ) === $label &&
					(float) $ci['donation_amount'] === $amount ) {
					$already = true;
					break;
				}
			}
			if ( ! $already ) {
				$cart_item_data = [
					'donation_post_id' => 0, // No specific post for checkout extras
					'donation_amount'  => $amount,
					'donation_desc'    => $label,
					'donation_extra'   => '1',
				];
				WC()->cart->add_to_cart( $product_id, 1, [], [], $cart_item_data );
			}
			wp_send_json_success( [ 'message' => 'Item added.' ] );
		} else {
			// Remove extra from cart
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( ! empty( $cart_item['donation_extra'] ) && 
					 ! empty( $cart_item['donation_desc'] ) && 
					 $cart_item['donation_desc'] === $label &&
					 (float) $cart_item['donation_amount'] === $amount ) {
					WC()->cart->remove_cart_item( $cart_item_key );
					wp_send_json_success( [ 'message' => 'Item removed.' ] );
				}
			}
			wp_send_json_error( [ 'message' => 'Item not found in cart.' ] );
		}
	}

	/**
	 * Fallback function to display extras using alternative hook
	 */
	public static function render_checkout_extras_fallback() {
		echo '<!-- DEBUG: render_checkout_extras_fallback called -->';
		
		// Get settings
		if ( ! class_exists( 'Charity_Settings_Handler' ) ) {
			echo '<!-- DEBUG FALLBACK: Charity_Settings_Handler class not found -->';
			return;
		}

		$settings = Charity_Settings_Handler::get_all_settings();
		
		// Debug: Check what we got
		error_log( 'CHARITY DEBUG FALLBACK: show_extras=' . intval( $settings['show_extras'] ) . ' extras count=' . count( $settings['extras'] ) );
		echo '<!-- DEBUG FALLBACK: show_extras=' . intval( $settings['show_extras'] ) . ' extras count=' . count( $settings['extras'] ) . ' -->';
		
		// Check if extras are enabled and exist
		if ( empty( $settings['show_extras'] ) || empty( $settings['extras'] ) || ! is_array( $settings['extras'] ) ) {
			echo '<!-- DEBUG FALLBACK: Returning early - show_extras or extras empty -->';
			return;
		}

		?>
		<div id="charity-checkout-extras-fallback" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<h3><?php esc_html_e( 'Support Us Further', 'charity-plugin' ); ?></h3>
			<p style="margin: 0 0 15px 0; color: #666; font-size: 0.9em;"><?php esc_html_e( 'Add optional extras to your donation:', 'charity-plugin' ); ?></p>
			<div class="charity-checkout-extras-list">
				<?php foreach ( $settings['extras'] as $extra ) : ?>
					<label class="charity-checkout-extra-item" style="display: flex; align-items: center; padding: 8px 0; cursor: pointer;">
						<input type="checkbox" class="charity-checkout-extra-checkbox" 
						       data-label="<?php echo esc_attr( $extra['label'] ); ?>" 
						       data-amount="<?php echo esc_attr( $extra['amount'] ); ?>"
						       style="margin-right: 10px; cursor: pointer; width: 18px; height: 18px;">
						<span style="flex: 1; color: #333;"><?php echo esc_html( $extra['label'] ); ?></span>
						<span style="color: #27ae60; font-weight: bold; margin-left: 10px; white-space: nowrap;">
							+<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $extra['amount'] ) ) : esc_html( $extra['amount'] ); ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		( function( $ ) {
			$( document ).on( 'change', '.charity-checkout-extra-checkbox', function() {
				const $checkbox = $( this );
				const label = $checkbox.data( 'label' );
				const amount = parseFloat( $checkbox.data( 'amount' ) );
				const isChecked = $checkbox.is( ':checked' );

				$.ajax( {
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					method: 'POST',
					dataType: 'json',
					data: {
						action: 'charity_update_checkout_extras',
						nonce: '<?php echo esc_attr( wp_create_nonce( 'charity_donation_nonce' ) ); ?>',
						label: label,
						amount: amount,
						add: isChecked ? 1 : 0,
					},
					success: function( response ) {
						if ( response.success ) {
							$( document.body ).trigger( 'update_checkout' );
						}
					},
					error: function() {
						$checkbox.prop( 'checked', ! isChecked );
						alert( '<?php esc_attr_e( 'Failed to update extras. Please try again.', 'charity-plugin' ); ?>' );
					},
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}
}
