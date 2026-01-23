<?php
/**
 * WRS Refund Handler
 *
 * Core logic for adding return shipping fee to refunds.
 *
 * @package WooReturnShipping
 * @version 1.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Refund_Handler
 *
 * Handles the creation of return shipping fee line items on refunds.
 */
class WRS_Refund_Handler {

	/**
	 * Initialize refund hooks.
	 */
	public static function init(): void {
		// Hook into refund creation - runs AFTER gateway processes.
		add_action( 'woocommerce_create_refund', array( __CLASS__, 'process_return_fee' ), 10, 2 );

		// Hook to add order note after refund.
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'after_refund_created' ), 10, 2 );
	}

	/**
	 * Process return shipping fee during refund creation.
	 *
	 * IMPORTANT: At this point, the gateway has already processed the refund
	 * with the NET amount (because we modified refund_amount in the AJAX).
	 * Now we add the fee line item for record-keeping.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @param array           $args   Refund arguments.
	 */
	public static function process_return_fee( WC_Order_Refund $refund, array $args ): void {
		// Check if apply_fee is set (checkbox was checked).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		$apply_fee = isset( $_POST['wrs_apply_fee'] ) && '1' === $_POST['wrs_apply_fee'];

		if ( ! $apply_fee ) {
			return;
		}

		// Check if fee amount was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['wrs_return_shipping_fee'] ) ) {
			return;
		}

		// Sanitize the fee amount.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fee_amount = floatval( sanitize_text_field( wp_unslash( $_POST['wrs_return_shipping_fee'] ) ) );

		if ( $fee_amount <= 0 ) {
			return;
		}

		// Get original refund amount (before our fee deduction).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$original_amount = isset( $_POST['wrs_original_refund_amount'] ) 
			? floatval( sanitize_text_field( wp_unslash( $_POST['wrs_original_refund_amount'] ) ) )
			: ( abs( $refund->get_total() ) + $fee_amount );

		// Validate fee doesn't exceed refund amount.
		if ( $fee_amount > $original_amount ) {
			$refund->add_meta_data( '_wrs_error', __( 'Return shipping fee cannot exceed refund amount.', 'woo-return-shipping' ), true );
			$refund->save();
			return;
		}

		// Create the fee line item.
		$fee_item = new WC_Order_Item_Fee();

		// Get settings.
		$fee_label  = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		$tax_status = get_option( 'wrs_tax_status', 'none' );
		$tax_class  = get_option( 'wrs_tax_class', '' );

		// Set fee properties.
		// Positive fee on a refund = reduces the refund total.
		$fee_item->set_name( $fee_label );
		$fee_item->set_amount( $fee_amount );
		$fee_item->set_total( $fee_amount );
		$fee_item->set_tax_status( $tax_status );

		if ( 'taxable' === $tax_status && ! empty( $tax_class ) ) {
			$fee_item->set_tax_class( $tax_class );
		}

		// Add fee to refund.
		$refund->add_item( $fee_item );

		// Store fee amount for reference.
		$refund->add_meta_data( '_wrs_return_fee', $fee_amount, true );
		$refund->add_meta_data( '_wrs_original_amount', $original_amount, true );

		// Recalculate totals.
		$refund->calculate_totals( false );
		$refund->save();

		error_log( 'WRS: Added return fee line item: ' . $fee_amount );
	}

	/**
	 * After refund is created, add order note.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function after_refund_created( int $order_id, int $refund_id ): void {
		$refund = wc_get_order( $refund_id );

		if ( ! $refund ) {
			return;
		}

		$fee_amount      = $refund->get_meta( '_wrs_return_fee' );
		$original_amount = $refund->get_meta( '_wrs_original_amount' );

		if ( $fee_amount ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$net_refund = $original_amount - $fee_amount;
				$order->add_order_note(
					sprintf(
						/* translators: 1: original amount, 2: fee amount, 3: net refund */
						__( 'Return shipping fee applied. Original: %1$s, Fee: %2$s, Net refund to customer: %3$s', 'woo-return-shipping' ),
						wc_price( $original_amount ),
						wc_price( $fee_amount ),
						wc_price( $net_refund )
					)
				);
			}
		}
	}
}
