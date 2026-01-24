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
 * @version 2.6.0
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
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'add_fee_to_order' ), 10, 3 );
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'hide_fee_in_totals' ), 10, 3 );
		add_filter( 'woocommerce_order_get_items', array( __CLASS__, 'filter_order_items' ), 10, 3 );
	}

	/**
	 * Add the return shipping fee to the order AFTER checkout is processed.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order object.
	 */
	public static function add_fee_to_order( int $order_id, array $posted_data, WC_Order $order ): void {
		$enabled = get_option( 'wrs_add_checkout_fee', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( $fee_label );
		$fee_item->set_amount( 0 );
		$fee_item->set_total( 0 );
		$fee_item->set_tax_status( 'none' );
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
		if ( is_admin() ) {
			return $total_rows;
		}

		if ( did_action( 'woocommerce_email_order_details' ) && method_exists( $order, 'get_type' ) && 'refund' === $order->get_type() ) {
			return $total_rows;
		}

		$fee_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );

		foreach ( $total_rows as $key => $row ) {
			if ( strpos( $key, 'fee' ) !== false && isset( $row['label'] ) ) {
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
		if ( is_admin() ) {
			return $items;
		}

		if ( is_a( $order, 'WC_Order_Refund' ) || ( method_exists( $order, 'get_type' ) && 'shop_order_refund' === $order->get_type() ) ) {
			return $items;
		}

		if ( did_action( 'woocommerce_email_order_details' ) ) {
			global $wp_current_filter;
			foreach ( $wp_current_filter as $filter ) {
				if ( strpos( $filter, 'refund' ) !== false ) {
					return $items;
				}
			}
		}

		foreach ( $items as $item_id => $item ) {
			if ( $item instanceof WC_Order_Item_Fee ) {
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
