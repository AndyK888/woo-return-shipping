<?php
/**
 * WRS Refund Handler
 *
 * Core logic for adding return shipping fee to refunds.
 *
 * @package WooReturnShipping
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
		// Hook into refund creation.
		add_action( 'woocommerce_create_refund', array( __CLASS__, 'process_return_fee' ), 10, 2 );

		// Hook into AJAX refund request to capture our custom field.
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'after_refund_created' ), 10, 2 );
	}

	/**
	 * Process return shipping fee during refund creation.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @param array           $args   Refund arguments.
	 */
	public static function process_return_fee( WC_Order_Refund $refund, array $args ): void {
		// Check if fee was submitted and not exempted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		if ( empty( $_POST['wrs_return_shipping_fee'] ) ) {
			return;
		}

		// Check for exemption.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['wrs_exempt_fee'] ) ) {
			return;
		}

		// Sanitize the fee amount.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fee_amount = floatval( sanitize_text_field( wp_unslash( $_POST['wrs_return_shipping_fee'] ) ) );

		if ( $fee_amount <= 0 ) {
			return;
		}

		// Get the refund total (negative value).
		$refund_total = abs( $refund->get_total() );

		// Validate fee doesn't exceed refund amount.
		if ( $fee_amount > $refund_total ) {
			// Store error for display.
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
		// In WooCommerce refund math: Refund items are negative, fees are positive.
		// A positive fee reduces the total refund amount.
		$fee_item->set_name( $fee_label );
		$fee_item->set_amount( $fee_amount );
		$fee_item->set_total( $fee_amount ); // Positive value reduces refund.
		$fee_item->set_tax_status( $tax_status );

		if ( 'taxable' === $tax_status && ! empty( $tax_class ) ) {
			$fee_item->set_tax_class( $tax_class );
		}

		// Add fee to refund.
		$refund->add_item( $fee_item );

		// Store the fee reason if provided.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['wrs_fee_reason'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$reason = sanitize_text_field( wp_unslash( $_POST['wrs_fee_reason'] ) );
			$refund->add_meta_data( '_wrs_fee_reason', $reason, true );
		}

		// Store fee amount for reference.
		$refund->add_meta_data( '_wrs_return_fee', $fee_amount, true );

		// Recalculate totals to reflect the fee.
		$refund->calculate_totals( false );
		$refund->save();
	}

	/**
	 * After refund is created, ensure gateway receives correct amount.
	 *
	 * Note: The gateway amount is handled by WooCommerce based on refund total.
	 * Since we add a positive fee to the refund, the total is already adjusted.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function after_refund_created( int $order_id, int $refund_id ): void {
		$refund = wc_get_order( $refund_id );

		if ( ! $refund ) {
			return;
		}

		// Log the refund details for debugging.
		$fee_amount = $refund->get_meta( '_wrs_return_fee' );

		if ( $fee_amount ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: fee amount, 2: net refund amount */
						__( 'Return shipping fee of %1$s deducted. Net refund: %2$s', 'woo-return-shipping' ),
						wc_price( $fee_amount ),
						wc_price( abs( $refund->get_total() ) )
					)
				);
			}
		}
	}

	/**
	 * Get the net refund amount (refund total minus fee).
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @return float
	 */
	public static function get_net_refund_amount( WC_Order_Refund $refund ): float {
		// The refund total already includes the fee deduction.
		return abs( $refund->get_total() );
	}
}
