<?php
/**
 * Shortcodes Handler for Donation Plugin
 *
 * @package CharityPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Charity_Shortcodes {

	/**
	 * Initialize shortcodes
	 */
	public static function init() {
		add_action( 'init', [ self::class, 'register_shortcodes' ] );
	}

	/**
	 * Register all shortcodes
	 */
	public static function register_shortcodes() {
		add_shortcode( 'donation_buttons', [ self::class, 'donation_buttons_shortcode' ] );
		add_shortcode( 'quickdonation', [ self::class, 'quickdonation_shortcode' ] );
		add_shortcode( 'donation_goal', [ self::class, 'donation_goal_shortcode' ] );
	}

	/**
	 * Main donation buttons shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML
	 */
	public static function donation_buttons_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'id'           => 0,
			'button_text'  => '',
			'show_id'      => '0',
			'id_label'     => 'ID:',
		], $atts, 'donation_buttons' );

		// Resolve post ID
		$post_id = self::resolve_post_id( intval( $atts['id'] ) );
		if ( ! $post_id ) {
			return '';
		}

		// Load donation data
		$data = self::get_donation_data( $post_id );
		if ( empty( $data['rows'] ) ) {
			return '';
		}

		// Get settings
		$settings = Charity_Settings_Handler::get_all_settings();
		$button_text = ! empty( $atts['button_text'] ) ? 
			$atts['button_text'] : $settings['button_text'];

		// Enqueue frontend assets
		self::enqueue_frontend_assets();

		ob_start();
		?>
		<div class="donation-wrapper donation-buttons" data-post-id="<?php echo (int) $post_id; ?>">
			<?php
			// Show post thumbnail & title
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
				echo '<h2 class="donation-title"><a href="' . esc_url( $permalink ) . '">' . 
					esc_html( $title ) . '</a></h2>';
			}
			?>

			<div class="row donation-card-grid" role="listbox" aria-label="<?php esc_attr_e( 'Donation amounts', 'charity-plugin' ); ?>">
				<?php
				$settings = Charity_Settings_Handler::get_all_settings();
				foreach ( $data['rows'] as $idx => $row ) {
					$active = ( 0 === $idx ) ? ' active' : '';
					?>
					<button type="button" class="col-12 col-sm-6 col-md-4 form-widget donation-card<?php echo esc_attr( $active ); ?>"
						 role="option"
						 aria-selected="<?php echo ( 0 === $idx ? 'true' : 'false' ); ?>"
						 data-amount="<?php echo esc_attr( $row['amount'] ); ?>"
						 data-desc="<?php echo esc_attr( $row['desc'] ); ?>">
						<div class="donation-amount">
							<?php
							if ( function_exists( 'wc_price' ) ) {
								echo wp_kses_post( wc_price( $row['amount'] ) );
							} else {
								echo esc_html( $row['amount'] );
							}
							?>
						</div>
						<div class="donation-desc"><?php echo esc_html( $row['desc'] ); ?></div>
					</button>
					<?php
				}
				?>
			</div>

			<?php if ( $data['allow_custom'] ) { ?>
				<div class="donation-custom-wrap">
					<input type="number" step="1" min="1" 
						   class="donation-custom-input form-control"
						   inputmode="numeric"
						   placeholder="<?php echo esc_attr( $data['custom_label'] ?? $settings['custom_placeholder'] ); ?>"
						   aria-label="<?php esc_attr_e( 'Custom donation amount', 'charity-plugin' ); ?>">
				</div>
			<?php } ?>

					<?php if ( ! empty( $settings['extras'] ) && $settings['show_extras'] && ! is_singular( 'donation' ) ) : ?>
				<div class="donation-extras">
					<?php foreach ( $settings['extras'] as $extra ) : ?>
						<label class="donation-extra">
							<input type="checkbox" class="donation-extra-checkbox" 
							       data-label="<?php echo esc_attr( $extra['label'] ); ?>" 
							       data-amount="<?php echo esc_attr( $extra['amount'] ); ?>">
							<?php echo esc_html( $extra['label'] ); ?>
							(+<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $extra['amount'] ) ) : esc_html( $extra['amount'] ); ?>)
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="donation-action-bar">
				<?php echo apply_filters( 'charity_donation_before_button', '', $post_id, $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<button type="button" class="btn btn-primary donation-submit" aria-label="<?php echo esc_attr( $button_text ); ?>">
					<?php echo esc_html( $button_text ); ?>
				</button>
				<?php echo apply_filters( 'charity_donation_after_button', '', $post_id, $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p class="donation-msg"></p>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Quick donation shortcode (horizontal layout)
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML
	 */
	public static function quickdonation_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'id'           => 0,
			'button_text'  => '',
			'show_id'      => '0',
			'id_label'     => 'ID:',
		], $atts, 'quickdonation' );

		$post_id = self::resolve_post_id( intval( $atts['id'] ) );
		if ( ! $post_id ) {
			return '';
		}

		$data = self::get_donation_data( $post_id );
		if ( empty( $data['rows'] ) ) {
			return '';
		}

		$settings = Charity_Settings_Handler::get_all_settings();
		$button_text = ! empty( $atts['button_text'] ) ? 
			$atts['button_text'] : $settings['button_text'];

		self::enqueue_frontend_assets();

		ob_start();
		?>
		<div class="quickdonate donation-wrapper donation-quick" data-post-id="<?php echo (int) $post_id; ?>">
			<?php if ( $atts['show_id'] === '1' ) { ?>
				<div class="donation-id">
					<?php echo esc_html( $atts['id_label'] ); ?> 
					<span class="donation-id-number"><?php echo (int) $post_id; ?></span>
				</div>
			<?php } ?>

			<div class="donation-card-grid-inline" role="listbox">
				<?php foreach ( $data['rows'] as $idx => $row ) {
					$active = ( 0 === $idx ) ? ' active' : '';
					?>
						<button type="button" class="form-widget donation-card<?php echo esc_attr( $active ); ?>"
						 role="option"
						 aria-selected="<?php echo ( 0 === $idx ? 'true' : 'false' ); ?>"
						 data-amount="<?php echo esc_attr( $row['amount'] ); ?>"
						 data-desc="<?php echo esc_attr( $row['desc'] ); ?>">
							<div class="donation-amount">
								<?php
								if ( function_exists( 'wc_price' ) ) {
									echo wp_kses_post( wc_price( $row['amount'] ) );
								} else {
									echo esc_html( $row['amount'] );
								}
								?>
							</div>
							<div class="donation-desc"><?php echo esc_html( $row['desc'] ); ?></div>
						</button>
				<div class="donation-custom-wrap">
					<input type="number" step="1" min="1" 
						   class="donation-custom-input form-control"
						   inputmode="numeric"
						   placeholder="<?php echo esc_attr( $data['custom_label'] ?? $settings['custom_placeholder'] ); ?>">
				</div>
			<?php } ?>

				<?php if ( ! empty( $settings['extras'] ) && $settings['show_extras'] && ! is_singular( 'donation' ) ) : ?>
				<div class="donation-extras">
					<?php foreach ( $settings['extras'] as $extra ) : ?>
						<label class="donation-extra">
							<input type="checkbox" class="donation-extra-checkbox" 
							       data-label="<?php echo esc_attr( $extra['label'] ); ?>" 
							       data-amount="<?php echo esc_attr( $extra['amount'] ); ?>">
							<?php echo esc_html( $extra['label'] ); ?>
							(+<?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( $extra['amount'] ) ) : esc_html( $extra['amount'] ); ?>)
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="donation-action-bar">
				<button type="button" class="donation-submit button button-primary">
					<?php echo esc_html( $button_text ); ?>
				</button>
				<p class="donation-msg"></p>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Donation goal shortcode (if exists in includes)
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML
	 */
	public static function donation_goal_shortcode( $atts ) {
		// This shortcode is delegated to the included goal module
		if ( function_exists( 'dw_donation_goal_render' ) ) {
			return dw_donation_goal_render( $atts );
		}
		return '';
	}

	/**
	 * Resolve post ID from various sources
	 *
	 * @param int $given Explicit post ID
	 * @return int Post ID or 0
	 */
	private static function resolve_post_id( $given = 0 ) {
		if ( $given > 0 ) {
			return $given;
		}

		if ( function_exists( 'get_queried_object_id' ) ) {
			$qid = get_queried_object_id();
			if ( $qid > 0 ) {
				return $qid;
			}
		}

		$loop_id = get_the_ID();
		return $loop_id > 0 ? $loop_id : 0;
	}

	/**
	 * Get donation data for a post
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	private static function get_donation_data( $post_id ) {
		$rows = get_post_meta( $post_id, '_donation_rows_arr', true );
		if ( ! is_array( $rows ) ) {
			$rows = [];
		}

		$allow_custom = get_post_meta( $post_id, '_donation_allow_custom', true ) === '1';
		$custom_label = get_post_meta( $post_id, '_donation_custom_label', true );

		return [
			'rows'          => $rows,
			'allow_custom'  => $allow_custom,
			'custom_label'  => $custom_label,
		];
	}

	/**
	 * Enqueue frontend CSS and JS
	 */
	private static function enqueue_frontend_assets() {
		static $enqueued = false;

		if ( $enqueued ) {
			return;
		}

		wp_enqueue_script( 'charity-frontend' );
		wp_enqueue_style( 'charity-frontend' );

		$enqueued = true;
	}
}
