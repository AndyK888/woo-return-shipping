<?php
/**
 * WRS Admin Handler
 *
 * Adds the return shipping fee as a line item in the order items table.
 *
 * @package WooReturnShipping
 * @version 1.3.0
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
		
		// Add the return shipping fee row in the order items table.
		add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'add_return_shipping_row' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
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

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			'1.3.0'
		);

		wp_enqueue_script(
			'wrs-admin-refund',
			WRS_PLUGIN_URL . 'assets/js/admin-refund.js',
			array( 'jquery' ),
			'1.3.0',
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
	 * Add the return shipping fee row after the shipping items in the order.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function add_return_shipping_row( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Don't show on refund orders.
		if ( $order->get_type() === 'shop_order_refund' ) {
			return;
		}

		$default_fee = floatval( get_option( 'wrs_default_fee', '10.00' ) );
		$fee_label   = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		?>
		<script type="text/html" id="tmpl-wrs-return-shipping-row">
			<tr class="wrs-return-shipping-item" data-order_item_id="wrs_return_shipping" style="display: none;">
				<td class="thumb">
					<div></div>
				</td>
				<td class="name">
					<div class="wrs-fee-wrapper" style="display: flex; align-items: center; gap: 10px;">
						<input type="checkbox" 
							id="wrs_apply_fee" 
							name="wrs_apply_fee" 
							value="1" 
							checked 
							style="width: 18px; height: 18px; margin: 0;"
						/>
						<label for="wrs_apply_fee" style="font-weight: 600; cursor: pointer;">
							<?php echo esc_html( $fee_label ); ?>
						</label>
						<span class="wrs-help" style="color: #666; font-size: 12px;">
							(deducted from refund)
						</span>
					</div>
				</td>
				<td class="item_cost" width="1%">&nbsp;</td>
				<td class="quantity" width="1%">&nbsp;</td>
				<td class="line_cost" width="1%">
					<div class="view">-</div>
					<div class="edit" style="display: block;">
						<div style="display: flex; align-items: center; justify-content: flex-end;">
							<span style="color: #c00; margin-right: 3px;">−</span>
							<input type="number" 
								id="wrs_return_shipping_fee" 
								name="wrs_return_shipping_fee"
								step="0.01" 
								min="0" 
								value="<?php echo esc_attr( number_format( $default_fee, 2, '.', '' ) ); ?>"
								class="wrs-fee-input"
								style="width: 70px; text-align: right;"
							/>
						</div>
					</div>
				</td>
				<td class="wc-order-edit-line-item" width="1%">&nbsp;</td>
			</tr>
		</script>
		<script type="text/html" id="tmpl-wrs-net-refund-summary">
			<div class="wrs-net-summary" style="margin: 10px 0; padding: 10px 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; display: none;">
				<div style="display: flex; justify-content: space-between; align-items: center;">
					<span style="font-weight: 600; color: #155724;">Net Refund to Customer:</span>
					<strong id="wrs_net_refund" style="font-size: 16px; color: #155724;">$0.00</strong>
				</div>
				<div style="font-size: 11px; color: #155724; margin-top: 5px;">
					This amount will be sent to the payment gateway.
				</div>
			</div>
		</script>
		<?php
	}
}
