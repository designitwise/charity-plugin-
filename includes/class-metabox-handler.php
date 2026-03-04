<?php
/**
 * Metabox Handler for Donation Post Type
 *
 * @package CharityPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Charity_Metabox_Handler {

	/**
	 * Initialize metabox
	 */
	public static function init() {
		add_action( 'add_meta_boxes', [ self::class, 'register_metabox' ] );
		add_action( 'save_post_donation', [ self::class, 'save_metabox' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_metabox_script' ] );
	}

	/**
	 * Register the metabox
	 */
	public static function register_metabox() {
		add_meta_box(
			'donation_amounts',
			esc_html__( 'Donation Options', 'charity-plugin' ),
			[ self::class, 'render_metabox' ],
			'donation',
			'normal',
			'default'
		);
	}

	/**
	 * Render the metabox UI
	 *
	 * @param WP_Post $post Post object
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'charity_donation_metabox', 'charity_metabox_nonce' );

		$rows = get_post_meta( $post->ID, '_donation_rows_arr', true );
		$allow_custom = get_post_meta( $post->ID, '_donation_allow_custom', true ) === '1';
		$custom_label = get_post_meta( $post->ID, '_donation_custom_label', true );

		if ( ! is_array( $rows ) ) {
			$rows = [ [ 'amount' => '', 'desc' => '' ] ];
		}

		?>
		<p><?php esc_html_e( 'Enter your donation rows below. Use "Add row" to insert more.', 'charity-plugin' ); ?></p>

		<table class="widefat striped charity-donation-table" id="donation-rows-table">
			<thead>
				<tr>
					<th class="column-amount"><?php esc_html_e( 'Amount', 'charity-plugin' ); ?></th>
					<th><?php esc_html_e( 'Description', 'charity-plugin' ); ?></th>
					<th class="column-action"><?php esc_html_e( 'Actions', 'charity-plugin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) {
					$amt = isset( $row['amount'] ) ? (string) $row['amount'] : '';
					$desc = isset( $row['desc'] ) ? (string) $row['desc'] : '';
					self::render_row( $amt, $desc );
				} ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3">
						<button type="button" class="button" id="donation-row-add">
							<?php esc_html_e( 'Add row', 'charity-plugin' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>

		<?php self::render_row_template(); ?>

		<p class="charity-metabox-options" style="margin-top: 20px;">
			<label>
				<input type="checkbox" name="donation_allow_custom" value="1" <?php checked( $allow_custom, true ); ?>>
				<?php esc_html_e( 'Allow custom amount', 'charity-plugin' ); ?>
			</label>
			&nbsp;&nbsp;
			<label>
				<?php esc_html_e( 'Custom amount label', 'charity-plugin' ); ?>:
				<input type="text" name="donation_custom_label"
					   value="<?php echo esc_attr( $custom_label ); ?>"
					   placeholder="<?php esc_attr_e( 'Enter amount', 'charity-plugin' ); ?>"
					   class="regular-text">
			</label>
		</p>
		<?php
	}

	/**
	 * Render a single donation row
	 *
	 * @param string $amount Amount value
	 * @param string $desc Description value
	 */
	private static function render_row( $amount = '', $desc = '' ) {
		?>
		<tr class="donation-row">
			<td>
				<input type="number" step="1" min="0" 
					   name="donation_rows_amount[]"
					   value="<?php echo esc_attr( $amount ); ?>"
					   placeholder="10"
					   inputmode="numeric"
					   pattern="^\d+$"
					   class="widefat charity-amount-input"
					   data-format="integer">
			</td>
			<td>
				<input type="text"
					   name="donation_rows_desc[]"
					   value="<?php echo esc_attr( $desc ); ?>"
					   placeholder="<?php esc_attr_e( 'What this supports', 'charity-plugin' ); ?>"
					   class="widefat">
			</td>
			<td>
				<button type="button" class="button donation-row-remove">
					<?php esc_html_e( 'Remove', 'charity-plugin' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render row template for JavaScript
	 */
	private static function render_row_template() {
		?>
		<script type="text/html" id="donation-row-template">
			<tr class="donation-row">
				<td>
					<input type="number" step="1" min="0" 
						   name="donation_rows_amount[]"
						   value=""
						   placeholder="10"
						   inputmode="numeric"
						   pattern="^\d+$"
						   class="widefat charity-amount-input"
						   data-format="integer">
				</td>
				<td>
					<input type="text"
						   name="donation_rows_desc[]"
						   value=""
						   placeholder="<?php esc_attr_e( 'What this supports', 'charity-plugin' ); ?>"
						   class="widefat">
				</td>
				<td>
					<button type="button" class="button donation-row-remove">
						<?php esc_html_e( 'Remove', 'charity-plugin' ); ?>
					</button>
				</td>
			</tr>
		</script>
		<?php
	}

	/**
	 * Enqueue admin metabox script
	 */
	public static function enqueue_metabox_script() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'donation' ) {
			return;
		}

		wp_enqueue_script(
			'charity-metabox',
			CHARITY_PLUGIN_URL . 'assets/js/metabox.js',
			[ 'jquery' ],
			CHARITY_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Save metabox data
	 *
	 * @param int $post_id Post ID
	 */
	public static function save_metabox( $post_id ) {
		// Verify nonce
		if ( ! isset( $_POST['charity_metabox_nonce'] ) || 
		     ! wp_verify_nonce( $_POST['charity_metabox_nonce'], 'charity_donation_metabox' ) ) {
			return;
		}

		// Check capabilities
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Sanitize and save rows
		$amounts = isset( $_POST['donation_rows_amount'] ) ? (array) $_POST['donation_rows_amount'] : [];
		$descs = isset( $_POST['donation_rows_desc'] ) ? (array) $_POST['donation_rows_desc'] : [];

		$rows = [];
		foreach ( $amounts as $i => $amt ) {
			$amt_raw = trim( (string) $amt );
			$desc = isset( $descs[ $i ] ) ? sanitize_text_field( $descs[ $i ] ) : '';

			if ( '' === $amt_raw || ! is_numeric( $amt_raw ) ) {
				continue;
			}

			$rows[] = [
				'amount' => number_format( (float) $amt_raw, 2, '.', '' ),
				'desc'   => $desc,
			];
		}

		update_post_meta( $post_id, '_donation_rows_arr', $rows );

		// Save custom amount settings
		$allow_custom = ! empty( $_POST['donation_allow_custom'] ) ? '1' : '0';
		$custom_label = isset( $_POST['donation_custom_label'] ) ? sanitize_text_field( $_POST['donation_custom_label'] ) : '';

		update_post_meta( $post_id, '_donation_allow_custom', $allow_custom );
		update_post_meta( $post_id, '_donation_custom_label', $custom_label );

		// Fire action for extensions
		do_action( 'charity_donation_saved', $post_id, $rows );
	}
}
