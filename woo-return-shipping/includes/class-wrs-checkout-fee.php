<?php
/**
 * WRS Checkout Fee Handler
 *
 * Adds a $0 "Return Shipping" fee to orders at checkout.
 * This fee then appears in the refund table as a native line item.
 *
 * @package WooReturnShipping
 * @version 2.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Checkout_Fee
 *
 * Adds a zero-value fee at checkout that can be used during refunds.
 */
class WRS_Checkout_Fee {

	/**
	 * Initialize checkout hooks.
	 */
	public static function init(): void {
		// Add zero fee to cart.
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'add_checkout_fee' ), 20 );
	}

	/**
	 * Add a zero-value return shipping fee to the cart.
	 *
	 * This fee will:
	 * - Appear on the order as $0.00 (no charge to customer)
	 * - Show in the admin refund table as a line item
	 * - Allow admin to enter a POSITIVE refund amount to deduct from total refund
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public static function add_checkout_fee( WC_Cart $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Only on checkout.
		if ( ! is_checkout() ) {
			return;
		}

		// Check if enabled in settings.
		$enabled = get_option( 'wrs_add_checkout_fee', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping (if applicable)', 'woo-return-shipping' ) );

		// Add a $0 fee.
		$cart->add_fee( $fee_label, 0, false );
	}
}
