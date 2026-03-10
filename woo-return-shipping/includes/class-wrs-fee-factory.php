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
		$fee_item->set_name( $label );
		$fee_item->set_amount( $amount );
		$fee_item->set_total( $amount );
		$fee_item->set_tax_status( get_option( 'wrs_tax_status', 'none' ) );
		$fee_item->add_meta_data( '_wrs_fee', 'yes', true );
		$fee_item->add_meta_data( '_wrs_fee_type', $fee_type, true );

		return $fee_item;
	}
}
