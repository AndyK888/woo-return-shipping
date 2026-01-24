<?php
/**
 * WRS Admin Handler
 *
 * Uses official WooCommerce hook for the order items metabox.
 *
 * @package WooReturnShipping
 * @version 2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Admin
 */
class WRS_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_admin_order_items_after_refunds', array( __CLASS__, 'render_fee_input' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on order screens.
	 */
	public static function enqueue_assets(): void {
		$screen = get_current_screen();
		
		if ( ! $screen ) {
			return;
		}

		$valid = false;
		
		if ( 'shop_order' === $screen->id ) {
			$valid = true;
		}
		
		if ( strpos( $screen->id, 'wc-orders' ) !== false ) {
			$valid = true;
		}
		
		if ( 'post' === $screen->base && 'shop_order' === $screen->post_type ) {
			$valid = true;
		}

		if ( ! $valid ) {
			return;
		}

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WRS_VERSION
		);

		wp_enqueue_script(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/js/admin-refund.js',
			array( 'jquery' ),
			WRS_VERSION,
			true
		);

		wp_localize_script(
			'wrs-admin',
			'wrsConfig',
			array(
				'defaultFee' => floatval( get_option( 'wrs_default_fee', '10.00' ) ),
				'feeLabel'   => get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ),
			)
		);
	}

	/**
	 * Render the fee input field using the official WooCommerce hook.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function render_fee_input( $order_id ): void {
		$order = wc_get_order( $order_id );
		
		if ( ! $order || 'shop_order_refund' === $order->get_type() ) {
			return;
		}

		$default_fee = floatval( get_option( 'wrs_default_fee', '10.00' ) );
		$fee_label   = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		?>
		<tr class="wrs-fee-row" style="display: none; background: #fffbeb;">
			<td colspan="100%" style="padding: 15px !important;">
				<div class="wrs-fee-container" style="border: 2px solid #f0d866; border-radius: 6px; padding: 15px; background: #fffbeb;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
						<label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer;">
							<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked style="width: 18px; height: 18px;">
							<?php echo esc_html( $fee_label ); ?>
						</label>
						<div style="display: flex; align-items: center;">
							<span style="color: #c00; font-weight: bold; margin-right: 4px;">−$</span>
							<input type="number" 
								id="wrs_return_shipping_fee" 
								name="wrs_return_shipping_fee" 
								value="<?php echo esc_attr( number_format( $default_fee, 2, '.', '' ) ); ?>" 
								step="0.01" 
								min="0"
								style="width: 80px; text-align: right; padding: 5px;">
						</div>
					</div>
					<div style="border-top: 1px solid #e0d48d; padding-top: 10px;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
							<span style="color: #666;">Gross Refund:</span>
							<span id="wrs_gross">$0.00</span>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<strong style="color: #155724;">Net to Customer:</strong>
							<strong id="wrs_net" style="color: #155724; font-size: 16px;">$0.00</strong>
						</div>
					</div>
					<div style="margin-top: 8px; font-size: 11px; color: #666;">
						<?php esc_html_e( 'The net amount will be sent to the payment gateway.', 'woo-return-shipping' ); ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}
}
