<?php
/**
 * WRS Email Handler
 *
 * Handles email modifications for refund notifications.
 *
 * @package WooReturnShipping
 * @version 2.5.0
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
		// Add note to refund emails - use a late priority to ensure we run.
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_refund_note' ), 99, 4 );

		// Also try a different hook that fires for refund emails.
		add_action( 'woocommerce_email_order_details', array( __CLASS__, 'debug_email_details' ), 5, 4 );

		error_log( 'WRS Email: Hooks registered' );
	}

	/**
	 * Debug hook to see what's being sent.
	 */
	public static function debug_email_details( $order, $sent_to_admin, $plain_text, $email ): void {
		$email_id = $email ? $email->id : 'null';
		$order_type = $order ? get_class( $order ) : 'null';
		$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : 'unknown';

		error_log( "WRS Email Debug: email_id={$email_id}, order_type={$order_type}, order_id={$order_id}" );
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
		$email_id = $email ? $email->id : 'null';
		error_log( "WRS Email: add_refund_note called, email_id={$email_id}" );

		// Handle both customer_refunded_order and customer_partially_refunded_order.
		if ( ! $email ) {
			error_log( 'WRS Email: No email object' );
			return;
		}

		$valid_emails = array( 'customer_refunded_order', 'customer_partially_refunded_order' );
		if ( ! in_array( $email->id, $valid_emails, true ) ) {
			error_log( "WRS Email: Email ID {$email->id} not in valid list" );
			return;
		}

		error_log( 'WRS Email: Valid refund email detected' );

		// Get the refund.
		$refund = self::get_latest_refund( $order );

		if ( ! $refund ) {
			error_log( 'WRS Email: No refund found for order' );
			return;
		}

		error_log( 'WRS Email: Refund found, ID=' . $refund->get_id() );

		// Check if refund has our fee.
		$fee_amount = self::get_return_fee_from_refund( $refund );

		error_log( "WRS Email: Fee amount detected = {$fee_amount}" );

		if ( $fee_amount <= 0 ) {
			error_log( 'WRS Email: No fee found in refund' );
			return;
		}

		$email_note = get_option( 'wrs_email_note', '' );

		if ( empty( $email_note ) ) {
			$email_note = __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' );
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		error_log( "WRS Email: Outputting fee - Label: {$fee_label}, Amount: {$fee_amount}" );

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

		error_log( 'WRS Email: Fee section output complete' );
	}

	/**
	 * Get the latest refund for an order.
	 *
	 * @param WC_Order|WC_Order_Refund $order Order object.
	 * @return WC_Order_Refund|null
	 */
	private static function get_latest_refund( $order ): ?WC_Order_Refund {
		if ( $order instanceof WC_Order_Refund ) {
			error_log( 'WRS Email: Order is already a refund' );
			return $order;
		}

		if ( ! method_exists( $order, 'get_refunds' ) ) {
			error_log( 'WRS Email: Order has no get_refunds method' );
			return null;
		}

		$refunds = $order->get_refunds();
		error_log( 'WRS Email: Found ' . count( $refunds ) . ' refunds' );

		if ( empty( $refunds ) ) {
			return null;
		}

		return $refunds[0]; // Most recent refund.
	}

	/**
	 * Get return shipping fee amount from a refund.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @return float Fee amount.
	 */
	private static function get_return_fee_from_refund( WC_Order_Refund $refund ): float {
		// First check our custom metadata.
		$fee_amount = $refund->get_meta( '_wrs_return_fee' );
		if ( ! empty( $fee_amount ) ) {
			error_log( "WRS Email: Found fee via _wrs_return_fee meta: {$fee_amount}" );
			return floatval( $fee_amount );
		}

		// Get configured fee label.
		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		error_log( "WRS Email: Looking for fee with label: {$fee_label}" );

		// Check fee line items.
		$fee_items = $refund->get_items( 'fee' );
		error_log( 'WRS Email: Found ' . count( $fee_items ) . ' fee items in refund' );

		foreach ( $fee_items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			$item_name = $item->get_name();
			$item_total = $item->get_total();
			error_log( "WRS Email: Fee item - Name: {$item_name}, Total: {$item_total}" );

			// Check by our meta or by label match.
			$is_our_fee = 'yes' === $item->get_meta( '_wrs_fee' );

			// Flexible label matching.
			$name_match = stripos( $item_name, $fee_label ) !== false;
			$name_match_reverse = stripos( $fee_label, $item_name ) !== false;

			error_log( "WRS Email: is_our_fee={$is_our_fee}, name_match={$name_match}, name_match_reverse={$name_match_reverse}" );

			if ( $is_our_fee || $name_match || $name_match_reverse ) {
				$total = abs( floatval( $item_total ) );
				if ( $total > 0 ) {
					error_log( "WRS Email: Found matching fee: {$total}" );
					return $total;
				}
			}
		}

		error_log( 'WRS Email: No matching fee found' );
		return 0.0;
	}
}
