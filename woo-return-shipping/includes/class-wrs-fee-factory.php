<?php
/**
 * Fee item construction helpers.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

final class WRS_Fee_Factory {

	/**
	 * Create a hidden $0 order fee item used to anchor refund deductions.
	 *
	 * @param string $label    Fee label.
	 * @param string $fee_type Managed fee type.
	 * @return WC_Order_Item_Fee
	 */
	public static function create_hidden_fee_item( string $label, string $fee_type ): WC_Order_Item_Fee {
		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( $label );
		$fee_item->set_amount( 0.0 );
		$fee_item->set_total( 0.0 );
		$fee_item->set_tax_status( 'none' );
		$fee_item->add_meta_data( '_wrs_fee', 'yes', true );
		$fee_item->add_meta_data( '_wrs_fee_type', $fee_type, true );

		return $fee_item;
	}

	/**
	 * Create a refund fee item representing an applied deduction.
	 *
	 * @param string $label    Fee label.
	 * @param float  $amount   Deduction amount.
	 * @param string $fee_type Managed fee type.
	 * @return WC_Order_Item_Fee
	 */
	public static function create_refund_fee_item( string $label, float $amount, string $fee_type ): WC_Order_Item_Fee {
		$fee_item = new WC_Order_Item_Fee();
		$refund_total = $amount * -1;
		$fee_item->set_name( $label );
		$fee_item->set_amount( $refund_total );
		$fee_item->set_total( $refund_total );
		$fee_item->set_tax_status( get_option( 'wrs_tax_status', 'none' ) );
		$fee_item->add_meta_data( '_wrs_fee', 'yes', true );
		$fee_item->add_meta_data( '_wrs_fee_type', $fee_type, true );

		return $fee_item;
	}

	/**
	 * Create a refund fee item linked to an original order fee row.
	 *
	 * Linking `_refunded_item_id` lets WooCommerce render the refunded amount
	 * against the existing hidden fee row in admin order screens.
	 *
	 * @param WC_Order_Item_Fee $order_fee_item Original order fee item.
	 * @param float             $amount         Deduction amount.
	 * @return WC_Order_Item_Fee
	 */
	public static function create_linked_refund_fee_item( WC_Order_Item_Fee $order_fee_item, float $amount ): WC_Order_Item_Fee {
		$fee_type = (string) $order_fee_item->get_meta( '_wrs_fee_type' );
		$fee_item = self::create_refund_fee_item( $order_fee_item->get_name(), $amount, $fee_type );

		$fee_item->set_tax_status( $order_fee_item->get_tax_status() );
		$fee_item->add_meta_data( '_refunded_item_id', $order_fee_item->get_id(), true );

		return $fee_item;
	}
}
