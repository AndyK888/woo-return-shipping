<?php
/**
 * WRS Email Handler
 *
 * Handles email modifications for refund notifications.
 *
 * @package WooReturnShipping
 * @version 2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Email
 *
 * Ensures the return shipping fee is visible in refund emails.
 */
class WRS_Email {

	/**
	 * Initialize email hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_refund_note' ), 99, 4 );
	}

	/**
	 * Add return shipping fee note to refund emails.
	 *
	 * @param WC_Order|WC_Order_Refund $order         Order object.
	 * @param bool                      $sent_to_admin Whether email is for admin.
	 * @param bool                      $plain_text    Whether email is plain text.
	 * @param WC_Email|null             $email         Email object.
	 */
	public static function add_refund_note( $order, bool $sent_to_admin, bool $plain_text, $email ): void {
		if ( ! $email ) {
			return;
		}

		$valid_emails = array( 'customer_refunded_order', 'customer_partially_refunded_order' );
		if ( ! in_array( $email->id, $valid_emails, true ) ) {
			return;
		}

		$refund = self::get_latest_refund( $order );

		if ( ! $refund ) {
			return;
		}

		$fee_amount = self::get_return_fee_from_refund( $refund );

		if ( $fee_amount <= 0 ) {
			return;
		}

		$email_note = get_option( 'wrs_email_note', '' );

		if ( empty( $email_note ) ) {
			$email_note = __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' );
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		if ( $plain_text ) {
			echo "\n" . esc_html( $fee_label ) . ': ' . wp_strip_all_tags( wc_price( $fee_amount ) ) . "\n";
			echo esc_html( $email_note ) . "\n";
		} else {
			?>
			<div class="wrs-refund-note" style="margin: 16px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
				<p style="margin: 0 0 8px 0; font-weight: bold; color: #856404;">
					<?php echo esc_html( $fee_label ); ?>: <?php echo wc_price( $fee_amount ); ?>
				</p>
				<p style="margin: 0; color: #856404;">
					<?php echo esc_html( $email_note ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get the latest refund for an order.
	 *
	 * @param WC_Order|WC_Order_Refund $order Order object.
	 * @return WC_Order_Refund|null
	 */
	private static function get_latest_refund( $order ): ?WC_Order_Refund {
		if ( $order instanceof WC_Order_Refund ) {
			return $order;
		}

		if ( ! method_exists( $order, 'get_refunds' ) ) {
			return null;
		}

		$refunds = $order->get_refunds();

		if ( empty( $refunds ) ) {
			return null;
		}

		return $refunds[0];
	}

	/**
	 * Get return shipping fee amount from a refund.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @return float Fee amount.
	 */
	private static function get_return_fee_from_refund( WC_Order_Refund $refund ): float {
		$fee_amount = $refund->get_meta( '_wrs_return_fee' );
		if ( ! empty( $fee_amount ) ) {
			return floatval( $fee_amount );
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		foreach ( $refund->get_items( 'fee' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			$item_name = $item->get_name();
			$is_our_fee = 'yes' === $item->get_meta( '_wrs_fee' );
			$name_match = stripos( $item_name, $fee_label ) !== false;
			$name_match_reverse = stripos( $fee_label, $item_name ) !== false;

			if ( $is_our_fee || $name_match || $name_match_reverse ) {
				$total = abs( floatval( $item->get_total() ) );
				if ( $total > 0 ) {
					return $total;
				}
			}
		}

		return 0.0;
	}
}
