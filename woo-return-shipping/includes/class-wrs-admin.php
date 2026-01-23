<?php
/**
 * WRS Admin Handler
 *
 * Adds the return shipping fee input to the refund modal.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Admin
 *
 * Handles admin UI modifications for the refund modal.
 */
class WRS_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		
		// Add our fields via JavaScript since PHP hooks don't work reliably in refund modal.
		add_action( 'admin_footer', array( __CLASS__, 'output_refund_fields_template' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		// Check if we're on an order page.
		$screen = get_current_screen();
		
		// Support both classic orders and HPOS.
		$valid_screens = array( 
			'shop_order', 
			'woocommerce_page_wc-orders',
			'edit-shop_order',
		);
		
		if ( ! $screen ) {
			return;
		}

		// Check screen ID or post type.
		$is_order_screen = in_array( $screen->id, $valid_screens, true ) 
			|| ( 'post' === $screen->base && 'shop_order' === $screen->post_type );

		if ( ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			'1.1.0'
		);

		wp_enqueue_script(
			'wrs-admin-refund',
			WRS_PLUGIN_URL . 'assets/js/admin-refund.js',
			array( 'jquery' ),
			'1.1.0',
			true
		);

		wp_localize_script(
			'wrs-admin-refund',
			'wrsAdmin',
			array(
				'defaultFee' => floatval( get_option( 'wrs_default_fee', '10.00' ) ),
				'feeLabel'   => get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ),
			)
		);
	}

	/**
	 * Output the refund fields template in admin footer.
	 * This ensures the HTML is available for JavaScript to move into place.
	 */
	public static function output_refund_fields_template(): void {
		$screen = get_current_screen();
		
		$valid_screens = array( 
			'shop_order', 
			'woocommerce_page_wc-orders',
		);
		
		if ( ! $screen ) {
			return;
		}

		$is_order_screen = in_array( $screen->id, $valid_screens, true ) 
			|| ( 'post' === $screen->base && 'shop_order' === $screen->post_type );

		if ( ! $is_order_screen ) {
			return;
		}

		$default_fee = floatval( get_option( 'wrs_default_fee', '10.00' ) );
		$fee_label   = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		?>
		<script type="text/html" id="tmpl-wrs-refund-fields">
			<div class="wrs-refund-fields" style="margin: 15px 0; padding: 12px 15px; background: #fffbeb; border: 1px solid #f0d866; border-radius: 4px;">
				<div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
					<label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #1d2327; cursor: pointer;">
						<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked style="width: 18px; height: 18px; margin: 0;" />
						<?php echo esc_html( $fee_label ); ?>
					</label>
					<div style="display: flex; align-items: center; gap: 5px;">
						<span>-</span>
						<input type="number" 
							id="wrs_return_shipping_fee" 
							name="wrs_return_shipping_fee" 
							step="0.01" 
							min="0" 
							value="<?php echo esc_attr( number_format( $default_fee, 2, '.', '' ) ); ?>"
							style="width: 80px; text-align: right; padding: 4px 8px;"
						/>
					</div>
				</div>
				<div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0d48d; display: flex; justify-content: space-between; align-items: center;">
					<span style="font-weight: 600;">Net Refund:</span>
					<strong id="wrs_net_refund" style="font-size: 14px; color: #2e7d32;">$0.00</strong>
				</div>
			</div>
		</script>
		<?php
	}
}
