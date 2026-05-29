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

		$tax_status = get_option( 'wrs_tax_status', 'none' );
		$fee_item->set_tax_status( $tax_status );

		if ( 'none' !== $tax_status ) {
			$tax_class = get_option( 'wrs_tax_class', '' );
			if ( '' !== $tax_class ) {
				$fee_item->set_tax_class( $tax_class );
			}
		}

		$fee_item->add_meta_data( '_wrs_fee', 'yes', true );
		$fee_item->add_meta_data( '_wrs_fee_type', $fee_type, true );

		return $fee_item;
	}

	/**
	 * Read a fee label option and ensure a non-empty fallback is used.
	 *
	 * @param string $option_name  Option key.
	 * @param string $default_label Default label text.
	 * @return string
	 */
	public static function get_fee_label( string $option_name, string $default_label ): string {
		$label = (string) get_option( $option_name, $default_label );

		if ( '' === $label ) {
			return $default_label;
		}

		return $label;
	}
}
