<?php
/**
 * Settings Handler for Donation Plugin
 *
 * @package CharityPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Charity_Settings_Handler {

	/**
	 * Initialize settings
	 */
	public static function init() {
		add_action( 'admin_menu', [ self::class, 'register_settings_page' ] );
	}

	/**
	 * Register the settings page
	 */
	public static function register_settings_page() {
		add_submenu_page(
			'edit.php?post_type=donation',
			esc_html__( 'Donation Settings', 'charity-plugin' ),
			esc_html__( 'Settings', 'charity-plugin' ),
			'manage_options',
			'charity-settings',
			[ self::class, 'render_settings_page' ]
		);
	}

	/**
	 * Render the settings page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'charity-plugin' ) );
		}

		// Handle form submission
		if ( isset( $_POST['charity_settings_nonce'] ) ) {
			self::handle_settings_save();
		}

		// Get current settings
		$settings = self::get_all_settings();
		
		// Available products
		$products = self::get_products_list();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Donation Settings', 'charity-plugin' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'charity_settings_save', 'charity_settings_nonce' ); ?>

				<!-- General Section -->
				<h2><?php esc_html_e( 'General', 'charity-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="charity_product_id"><?php esc_html_e( 'Donation Product', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<?php if ( ! empty( $products ) ) { ?>
								<select name="charity_product_id" id="charity_product_id">
									<option value="0">— <?php esc_html_e( 'Select', 'charity-plugin' ); ?> —</option>
									<?php foreach ( $products as $pid => $title ) { ?>
										<option value="<?php echo esc_attr( $pid ); ?>" 
											<?php selected( $settings['product_id'], $pid ); ?>>
											<?php echo esc_html( $title ); ?> (ID: <?php echo esc_html( $pid ); ?>)
										</option>
									<?php } ?>
								</select>
							<?php } else { ?>
								<input type="number" name="charity_product_id" 
									   value="<?php echo esc_attr( $settings['product_id'] ); ?>"
									   min="0">
								<p class="description">
									<?php esc_html_e( 'WooCommerce not available. Enter product ID manually.', 'charity-plugin' ); ?>
								</p>
							<?php } ?>
							<p class="description">
								<?php esc_html_e( 'Use a simple, virtual WooCommerce product to collect donations.', 'charity-plugin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="charity_show_extras"><?php esc_html_e( 'Show Extras', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="charity_show_extras" id="charity_show_extras" value="1" 
									<?php checked( $settings['show_extras'], 1 ); ?>>
								<?php esc_html_e( 'Display donation extras as options on the donation form', 'charity-plugin' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, customers will see checkboxes for additional donation items to add to their donation.', 'charity-plugin' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- Appearance Section -->
				<h2><?php esc_html_e( 'Appearance', 'charity-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="charity_active_color"><?php esc_html_e( 'Active Card Color', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="charity_active_color" name="charity_active_color" 
								   value="<?php echo esc_attr( $settings['active_color'] ); ?>"
								   class="charity-color-picker" placeholder="#d8bd6a">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="charity_button_color"><?php esc_html_e( 'Button Color', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="charity_button_color" name="charity_button_color" 
								   value="<?php echo esc_attr( $settings['button_color'] ); ?>"
								   class="charity-color-picker" placeholder="#c8102e">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="charity_button_text"><?php esc_html_e( 'Button Text', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="charity_button_text" name="charity_button_text" 
								   value="<?php echo esc_attr( $settings['button_text'] ); ?>"
								   class="regular-text" placeholder="DONATE NOW">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="charity_custom_placeholder"><?php esc_html_e( 'Custom Amount Placeholder', 'charity-plugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="charity_custom_placeholder" name="charity_custom_placeholder" 
								   value="<?php echo esc_attr( $settings['custom_placeholder'] ); ?>"
								   class="regular-text" placeholder="Enter Custom Amount">
						</td>
					</tr>
				</table>

				<!-- Extras Section -->
				<h2><?php esc_html_e( 'Donation Extras', 'charity-plugin' ); ?></h2>
				<table class="form-table" role="presentation" id="charity-extras-table">
					<tr>
						<th><?php esc_html_e( 'Label', 'charity-plugin' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'charity-plugin' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'charity-plugin' ); ?></th>
					</tr>
					<?php
					if ( ! empty( $settings['extras'] ) && is_array( $settings['extras'] ) ) {
						foreach ( $settings['extras'] as $extra ) {
							?>
							<tr class="charity-extra-row">
								<td><input type="text" name="charity_extra_label[]" value="<?php echo esc_attr( $extra['label'] ); ?>" class="regular-text"></td>
								<td><input type="number" step="0.01" min="0" name="charity_extra_amount[]" value="<?php echo esc_attr( $extra['amount'] ); ?>"></td>
								<td><button type="button" class="button remove-extra">&times;</button></td>
							</tr>
							<?php
						}
					} else {
						// empty placeholder row
						?>
						<tr class="charity-extra-row">
							<td><input type="text" name="charity_extra_label[]" value="" class="regular-text"></td>
							<td><input type="number" step="0.01" min="0" name="charity_extra_amount[]" value=""></td>
							<td><button type="button" class="button remove-extra">&times;</button></td>
						</tr>
						<?php
					}
					?>
				</table>
				<p>
					<button type="button" class="button" id="add-charity-extra"><?php esc_html_e( 'Add Extra', 'charity-plugin' ); ?></button>
				</p>

				<script>
				( function( $ ) {
					function newRow() {
						return `<tr class="charity-extra-row">
								<td><input type="text" name="charity_extra_label[]" value="" class="regular-text"></td>
								<td><input type="number" step="0.01" min="0" name="charity_extra_amount[]" value=""></td>
								<td><button type="button" class="button remove-extra">&times;</button></td>
							</tr>`;
					}

					$( document ).on( 'click', '#add-charity-extra', function( e ) {
						e.preventDefault();
						$( '#charity-extras-table' ).append( newRow() );
					} );

					$( document ).on( 'click', '.remove-extra', function() {
						$( this ).closest( 'tr' ).remove();
					} );
				} )( jQuery );
				</script>

				<?php submit_button( esc_html__( 'Save Settings', 'charity-plugin' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle settings form submission
	 */
	private static function handle_settings_save() {
		// Verify nonce
		if ( ! isset( $_POST['charity_settings_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['charity_settings_nonce'], 'charity_settings_save' ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'charity-plugin' ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'charity-plugin' ) );
		}

		// Sanitize and save settings
		$product_id = isset( $_POST['charity_product_id'] ) ? intval( $_POST['charity_product_id'] ) : 0;
		$active_color = isset( $_POST['charity_active_color'] ) ? 
			Charity_Donations_CPT::sanitize_hex_color( $_POST['charity_active_color'], '#d8bd6a' ) : '#d8bd6a';
		$button_color = isset( $_POST['charity_button_color'] ) ? 
			Charity_Donations_CPT::sanitize_hex_color( $_POST['charity_button_color'], '#c8102e' ) : '#c8102e';
		$button_text = isset( $_POST['charity_button_text'] ) ? 
			sanitize_text_field( $_POST['charity_button_text'] ) : 'DONATE NOW';
		$custom_placeholder = isset( $_POST['charity_custom_placeholder'] ) ? 
			sanitize_text_field( $_POST['charity_custom_placeholder'] ) : 'Enter Custom Amount';
		$show_extras = isset( $_POST['charity_show_extras'] ) ? 1 : 0;

		// Extras (array of [label,amount])
		$extras = [];
		if ( isset( $_POST['charity_extra_label'] ) && is_array( $_POST['charity_extra_label'] ) ) {
			foreach ( $_POST['charity_extra_label'] as $idx => $lbl ) {
				$label = sanitize_text_field( $lbl );
				$amount = isset( $_POST['charity_extra_amount'][ $idx ] ) ? floatval( $_POST['charity_extra_amount'][ $idx ] ) : 0;
				if ( $label && $amount > 0 ) {
					$extras[] = [
						'label'  => $label,
						'amount' => $amount,
					];
				}
			}
		}

		// Update all options
		update_option( 'charity_donation_product_id', $product_id );
		update_option( 'charity_active_color', $active_color );
		update_option( 'charity_button_color', $button_color );
		update_option( 'charity_button_text', $button_text );
		update_option( 'charity_custom_placeholder', $custom_placeholder );
		update_option( 'charity_show_extras', $show_extras );
		update_option( 'charity_extra_items', $extras );

		// if extras feature is disabled, remove any existing extras from current cart/session
		if ( ! $show_extras && class_exists( 'WooCommerce' ) && WC()->cart ) {
			Charity_Donations_CPT::maybe_clear_disabled_extras( WC()->cart );
		}

		// Fire action for extensions
		do_action( 'charity_settings_saved', [
			'product_id'         => $product_id,
			'active_color'       => $active_color,
			'button_color'       => $button_color,
			'button_text'        => $button_text,
			'custom_placeholder' => $custom_placeholder,
			'show_extras'        => $show_extras,
			'extras'             => $extras,
		]);

		// Show success message
		add_settings_error(
			'charity_settings',
			'settings_updated',
			esc_html__( 'Settings saved successfully.', 'charity-plugin' ),
			'updated'
		);

		settings_errors( 'charity_settings' );
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public static function get_all_settings() {
		return [
			'product_id'         => (int) get_option( 'charity_donation_product_id', 0 ),
			'active_color'       => get_option( 'charity_active_color', '#d8bd6a' ),
			'button_color'       => get_option( 'charity_button_color', '#c8102e' ),
			'button_text'        => get_option( 'charity_button_text', 'DONATE NOW' ),
			'custom_placeholder' => get_option( 'charity_custom_placeholder', 'Enter Custom Amount' ),
			'show_extras'        => (int) get_option( 'charity_show_extras', 1 ),
			'extras'             => get_option( 'charity_extra_items', [] ),
		];
	}

	/**
	 * Get list of WooCommerce products
	 *
	 * @return array
	 */
	private static function get_products_list() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return [];
		}

		$products_raw = wc_get_products( [
			'status' => [ 'publish', 'private' ],
			'limit'  => -1,
			'return' => 'ids',
		]);

		$products = [];
		foreach ( $products_raw as $pid ) {
			$products[ $pid ] = get_the_title( $pid );
		}

		return $products;
	}
}
