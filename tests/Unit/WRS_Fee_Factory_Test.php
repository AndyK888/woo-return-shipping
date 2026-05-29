<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Fee_Factory_Test extends TestCase {

	public function test_create_refund_fee_item_sets_fee_type_metadata(): void {
		$fee_item = WRS_Fee_Factory::create_refund_fee_item( 'Retail Box Damage', 5.0, 'retail_box_damage' );

		$this->assertSame( 'Retail Box Damage', $fee_item->get_name() );
		$this->assertSame( 5.0, $fee_item->get_total() );
		$this->assertSame( 'retail_box_damage', $fee_item->get_meta( '_wrs_fee_type' ) );
		$this->assertSame( 'yes', $fee_item->get_meta( '_wrs_fee' ) );
	}

	public function test_create_refund_fee_item_sets_tax_status_and_class(): void {
		$GLOBALS['wrs_test_options']['wrs_tax_status'] = 'taxable';
		$GLOBALS['wrs_test_options']['wrs_tax_class']  = 'standard';

		$fee_item = WRS_Fee_Factory::create_refund_fee_item( 'Return Shipping', 10.0, 'return_shipping' );

		$this->assertSame( 'taxable', $fee_item->get_tax_status() );
		$this->assertSame( 'standard', $fee_item->get_tax_class() );

		// Clean up
		unset( $GLOBALS['wrs_test_options']['wrs_tax_status'] );
		unset( $GLOBALS['wrs_test_options']['wrs_tax_class'] );
	}
}
