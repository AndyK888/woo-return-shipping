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
		add_action( 'woocommerce_admin_order_items_after_refunds', array( __CLASS__, 'render_fee_input' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		$screen = get_current_screen();

		// Only load on order edit pages.
		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WRS_VERSION
		);

		wp_enqueue_script(
			'wrs-admin-refund',
			WRS_PLUGIN_URL . 'assets/js/admin-refund.js',
			array( 'jquery', 'wc-admin-order-meta-boxes' ),
			WRS_VERSION,
			true
		);

		wp_localize_script(
			'wrs-admin-refund',
			'wrsAdmin',
			array(
				'defaultFee'      => floatval( get_option( 'wrs_default_fee', '10.00' ) ),
				'feeLabel'        => get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ),
				'showReasonField' => 'yes' === get_option( 'wrs_show_reason_field', 'no' ),
				'i18n'            => array(
					'feeLabel'        => __( 'Return Shipping Fee', 'woo-return-shipping' ),
					'exemptLabel'     => __( 'Exempt from return fee', 'woo-return-shipping' ),
					'exemptTooltip'   => __( 'Check if customer pre-paid for return shipping', 'woo-return-shipping' ),
					'reasonLabel'     => __( 'Return Fee Reason', 'woo-return-shipping' ),
					'reasonPlaceholder' => __( 'Optional reason for fee deduction', 'woo-return-shipping' ),
					'netRefund'       => __( 'Net Refund (after fee)', 'woo-return-shipping' ),
				),
			)
		);
	}

	/**
	 * Render the return fee input field in refund area.
	 *
	 * This hook fires in the order items meta box.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function render_fee_input( int $order_id ): void {
		$default_fee       = floatval( get_option( 'wrs_default_fee', '10.00' ) );
		$show_reason_field = 'yes' === get_option( 'wrs_show_reason_field', 'no' );
		?>
		<tr class="wrs-return-shipping-row" style="display: none;">
			<td class="label">
				<label for="wrs_return_shipping_fee">
					<?php esc_html_e( 'Return Shipping Fee', 'woo-return-shipping' ); ?>
				</label>
			</td>
			<td class="total">
				<input 
					type="number" 
					id="wrs_return_shipping_fee" 
					name="wrs_return_shipping_fee" 
					class="wc_input_price" 
					step="0.01" 
					min="0" 
					value="<?php echo esc_attr( $default_fee ); ?>"
					placeholder="0.00"
				/>
			</td>
		</tr>
		<tr class="wrs-exempt-row" style="display: none;">
			<td class="label">
				<label for="wrs_exempt_fee">
					<?php esc_html_e( 'Exempt from fee', 'woo-return-shipping' ); ?>
				</label>
			</td>
			<td class="total">
				<input 
					type="checkbox" 
					id="wrs_exempt_fee" 
					name="wrs_exempt_fee" 
					value="1"
				/>
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Check if customer pre-paid for return shipping', 'woo-return-shipping' ); ?>"></span>
			</td>
		</tr>
		<?php if ( $show_reason_field ) : ?>
		<tr class="wrs-reason-row" style="display: none;">
			<td class="label">
				<label for="wrs_fee_reason">
					<?php esc_html_e( 'Fee Reason', 'woo-return-shipping' ); ?>
				</label>
			</td>
			<td class="total">
				<input 
					type="text" 
					id="wrs_fee_reason" 
					name="wrs_fee_reason" 
					class="wrs-reason-input"
					placeholder="<?php esc_attr_e( 'Optional reason', 'woo-return-shipping' ); ?>"
				/>
			</td>
		</tr>
		<?php endif; ?>
		<tr class="wrs-net-refund-row" style="display: none;">
			<td class="label">
				<strong><?php esc_html_e( 'Net Refund (after fee)', 'woo-return-shipping' ); ?></strong>
			</td>
			<td class="total">
				<strong id="wrs_net_refund_display">-</strong>
			</td>
		</tr>
		<?php
	}
}
