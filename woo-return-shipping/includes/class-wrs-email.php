<?php
/**
 * WRS Email Handler
 *
 * Handles email modifications for refund notifications.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Email
 *
 * Modifies refund emails to include return shipping fee information.
 */
class WRS_Email {

	/**
	 * Initialize email hooks.
	 */
	public static function init(): void {
		// Add note to refund emails.
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_refund_note' ), 10, 4 );

		// Ensure fee line items are displayed in email.
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'add_fee_to_totals' ), 10, 3 );
	}

	/**
	 * Add return shipping fee note to refund emails.
	 *
	 * @param WC_Order $order         Order object.
	 * @param bool     $sent_to_admin Whether email is for admin.
	 * @param bool     $plain_text    Whether email is plain text.
	 * @param WC_Email $email         Email object.
	 */
	public static function add_refund_note( $order, bool $sent_to_admin, bool $plain_text, $email ): void {
		// Only add note to customer refund emails.
		if ( ! $email || 'customer_refunded_order' !== $email->id ) {
			return;
		}

		// Check if this is a refund order.
		if ( ! $order instanceof WC_Order_Refund ) {
			return;
		}

		// Check if return fee was applied.
		$fee_amount = $order->get_meta( '_wrs_return_fee' );

		if ( empty( $fee_amount ) ) {
			return;
		}

		$email_note = get_option( 'wrs_email_note', '' );

		if ( empty( $email_note ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html( $email_note ) . "\n";
		} else {
			?>
			<div class="wrs-refund-note" style="margin: 16px 0; padding: 12px; background-color: #f8f8f8; border-left: 4px solid #96588a;">
				<p style="margin: 0;">
					<?php echo esc_html( $email_note ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Ensure fee line item is displayed in order totals.
	 *
	 * WooCommerce should handle this automatically since we add the fee
	 * as a proper WC_Order_Item_Fee, but this filter ensures it's visible.
	 *
	 * @param array    $total_rows Order total rows.
	 * @param WC_Order $order      Order object.
	 * @param mixed    $tax_display Tax display mode.
	 * @return array
	 */
	public static function add_fee_to_totals( array $total_rows, $order, $tax_display ): array {
		// Only modify for refund orders.
		if ( ! $order instanceof WC_Order_Refund ) {
			return $total_rows;
		}

		// Check if we have a return fee.
		$fee_amount = $order->get_meta( '_wrs_return_fee' );

		if ( empty( $fee_amount ) ) {
			return $total_rows;
		}

		// The fee should already be in total_rows via the WC_Order_Item_Fee.
		// This filter is a safety net in case it's not displayed.
		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		$has_fee   = false;

		foreach ( $total_rows as $key => $row ) {
			if ( strpos( $key, 'fee' ) !== false ) {
				$has_fee = true;
				break;
			}
		}

		// If fee not found, add it before the order total.
		if ( ! $has_fee ) {
			$new_total_rows = array();

			foreach ( $total_rows as $key => $row ) {
				if ( 'order_total' === $key ) {
					$new_total_rows['wrs_return_fee'] = array(
						'label' => $fee_label . ':',
						'value' => wc_price( $fee_amount ),
					);
				}
				$new_total_rows[ $key ] = $row;
			}

			return $new_total_rows;
		}

		return $total_rows;
	}
}
