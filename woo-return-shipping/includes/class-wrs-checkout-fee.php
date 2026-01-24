<?php
/**
 * WRS Checkout Fee Handler
 *
 * Adds a hidden "Return Shipping" fee to orders.
 * The fee is:
 * - HIDDEN: checkout, order confirmation, customer emails, My Account
 * - VISIBLE: admin order page (for refunds), refund confirmation emails
 *
 * @package WooReturnShipping
 * @version 2.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Checkout_Fee
 */
class WRS_Checkout_Fee {

	/**
	 * The fee name identifier (used to detect our fee).
	 */
	const FEE_IDENTIFIER = 'wrs_return_shipping_fee';

	/**
	 * Initialize checkout hooks.
	 */
	public static function init(): void {
		// Add fee to completed orders (not visible during checkout).
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'add_fee_to_order' ), 10, 3 );

		// Hide fee in customer-facing areas.
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'hide_fee_in_totals' ), 10, 3 );
		add_filter( 'woocommerce_order_get_items', array( __CLASS__, 'filter_order_items' ), 10, 3 );

		// Add debug console log on cart/checkout for developers.
		add_action( 'wp_footer', array( __CLASS__, 'add_debug_console_log' ) );
	}

	/**
	 * Add console log on cart and checkout pages for debugging.
	 */
	public static function add_debug_console_log(): void {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		$enabled = get_option( 'wrs_add_checkout_fee', 'yes' );
		$page    = is_cart() ? 'cart' : 'checkout';
		?>
		<script type="text/javascript">
			console.log('WRS: Return Shipping plugin active on <?php echo esc_js( $page ); ?> page');
			console.log('WRS: Fee will be added to order after checkout (enabled: <?php echo esc_js( $enabled ); ?>)');
		</script>
		<?php
	}

	/**
	 * Add the return shipping fee to the order AFTER checkout is processed.
	 * This way it doesn't appear during checkout at all.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order object.
	 */
	public static function add_fee_to_order( int $order_id, array $posted_data, WC_Order $order ): void {
		// Check if enabled in settings.
		$enabled = get_option( 'wrs_add_checkout_fee', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		// Create a $0 fee item.
		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( $fee_label );
		$fee_item->set_amount( 0 );
		$fee_item->set_total( 0 );
		$fee_item->set_tax_status( 'none' );

		// Add our identifier so we can detect it later.
		$fee_item->add_meta_data( '_wrs_fee', 'yes', true );

		$order->add_item( $fee_item );
		$order->save();
	}

	/**
	 * Hide our fee from order totals in customer-facing views.
	 *
	 * @param array                         $total_rows  Order total rows.
	 * @param WC_Order|WC_Order_Refund|mixed $order       Order object.
	 * @param string                         $tax_display Tax display mode.
	 * @return array
	 */
	public static function hide_fee_in_totals( array $total_rows, $order, string $tax_display ): array {
		// Don't hide in admin.
		if ( is_admin() ) {
			return $total_rows;
		}

		// Don't hide in refund emails.
		if ( did_action( 'woocommerce_email_order_details' ) && 'refund' === $order->get_type() ) {
			return $total_rows;
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		// Remove our fee from the totals display.
		foreach ( $total_rows as $key => $row ) {
			if ( strpos( $key, 'fee' ) !== false && isset( $row['label'] ) ) {
				// Check if this is our fee by label.
				if ( strpos( $row['label'], $fee_label ) !== false || strpos( $row['value'], '$0.00' ) !== false ) {
					unset( $total_rows[ $key ] );
				}
			}
		}

		return $total_rows;
	}

	/**
	 * Filter order items to hide our fee in customer-facing areas.
	 *
	 * @param array                         $items Order items.
	 * @param WC_Order|WC_Order_Refund|mixed $order Order object.
	 * @param array                         $types Item types.
	 * @return array
	 */
	public static function filter_order_items( array $items, $order, array $types ): array {
		// Don't filter in admin.
		if ( is_admin() ) {
			return $items;
		}

		// Don't filter for refund orders (show fee on refund emails).
		if ( is_a( $order, 'WC_Order_Refund' ) || ( method_exists( $order, 'get_type' ) && 'shop_order_refund' === $order->get_type() ) ) {
			return $items;
		}

		// Check if we're in a refund email context.
		if ( did_action( 'woocommerce_email_order_details' ) ) {
			// Get email currently being sent.
			global $wp_current_filter;
			foreach ( $wp_current_filter as $filter ) {
				if ( strpos( $filter, 'refund' ) !== false ) {
					return $items; // Don't hide in refund emails.
				}
			}
		}

		// Remove our fee items from customer view.
		foreach ( $items as $item_id => $item ) {
			if ( $item instanceof WC_Order_Item_Fee ) {
				// Check our meta.
				if ( 'yes' === $item->get_meta( '_wrs_fee' ) ) {
					unset( $items[ $item_id ] );
				}
			}
		}

		return $items;
	}

	/**
	 * Check if a fee item is our return shipping fee.
	 *
	 * @param WC_Order_Item_Fee $item Fee item.
	 * @return bool
	 */
	public static function is_our_fee( WC_Order_Item_Fee $item ): bool {
		return 'yes' === $item->get_meta( '_wrs_fee' );
	}
}
