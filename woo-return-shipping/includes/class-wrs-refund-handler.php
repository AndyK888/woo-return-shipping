<?php
/**
 * WRS Refund Handler
 *
 * Core logic - modifies refund amount BEFORE gateway processes it.
 *
 * @package WooReturnShipping
 * @version 1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Refund_Handler
 *
 * Handles the return shipping fee deduction from refunds.
 *
 * KEY INSIGHT from WooCommerce source:
 * - `woocommerce_create_refund` action fires BEFORE refund->save() and BEFORE wc_refund_payment()
 * - wc_refund_payment() calls $gateway->process_refund($order_id, $refund->get_amount(), $reason)
 * - So we can modify $refund->set_amount() in this action to change what the gateway receives!
 */
class WRS_Refund_Handler {

	/**
	 * Initialize refund hooks.
	 */
	public static function init(): void {
		// This is THE KEY HOOK - fires before save and before gateway!
		add_action( 'woocommerce_create_refund', array( __CLASS__, 'modify_refund_amount' ), 5, 2 );

		// Add order note after refund completes.
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'add_order_note' ), 10, 2 );
	}

	/**
	 * Modify the refund amount to subtract the return shipping fee.
	 *
	 * This happens BEFORE the refund is saved and BEFORE the gateway processes it.
	 * The gateway will receive the REDUCED amount.
	 *
	 * @param WC_Order_Refund $refund The refund object.
	 * @param array           $args   Refund arguments.
	 */
	public static function modify_refund_amount( WC_Order_Refund $refund, array $args ): void {
		// Check if our fee should be applied.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		$apply_fee = isset( $_POST['wrs_apply_fee'] ) && '1' === $_POST['wrs_apply_fee'];

		if ( ! $apply_fee ) {
			return;
		}

		// Get fee amount from POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['wrs_return_shipping_fee'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fee_amount = floatval( sanitize_text_field( wp_unslash( $_POST['wrs_return_shipping_fee'] ) ) );

		if ( $fee_amount <= 0 ) {
			return;
		}

		// Get the original refund amount (positive number).
		$original_amount = abs( $refund->get_amount() );

		// Validate fee doesn't exceed refund.
		if ( $fee_amount > $original_amount ) {
			return;
		}

		// Calculate the NET refund amount (what customer actually gets).
		$net_amount = $original_amount - $fee_amount;

		// MODIFY THE REFUND AMOUNT - this is what the gateway will receive!
		$refund->set_amount( $net_amount );

		// Also update the total (negative value for refunds).
		$refund->set_total( $net_amount * -1 );

		// Store metadata for record-keeping.
		$refund->add_meta_data( '_wrs_return_fee', $fee_amount, true );
		$refund->add_meta_data( '_wrs_original_amount', $original_amount, true );
		$refund->add_meta_data( '_wrs_net_amount', $net_amount, true );

		// Add the fee as a line item for visibility in the refund.
		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( $fee_label );
		$fee_item->set_amount( $fee_amount );
		$fee_item->set_total( $fee_amount ); // Positive = deduction from refund.
		$fee_item->set_tax_status( get_option( 'wrs_tax_status', 'none' ) );

		$refund->add_item( $fee_item );
	}

	/**
	 * Add order note after refund is complete.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function add_order_note( int $order_id, int $refund_id ): void {
		$refund = wc_get_order( $refund_id );

		if ( ! $refund ) {
			return;
		}

		$fee_amount      = $refund->get_meta( '_wrs_return_fee' );
		$original_amount = $refund->get_meta( '_wrs_original_amount' );
		$net_amount      = $refund->get_meta( '_wrs_net_amount' );

		if ( ! $fee_amount ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: original amount, 2: fee amount, 3: net refund */
				__( 'Return shipping fee applied: Original refund %1$s - Fee %2$s = Net refund %3$s (sent to gateway)', 'woo-return-shipping' ),
				wc_price( $original_amount ),
				wc_price( $fee_amount ),
				wc_price( $net_amount )
			)
		);
	}
}
