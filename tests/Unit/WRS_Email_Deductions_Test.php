<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Email_Deductions_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wrs_test_options'] = array(
			'wrs_fee_label'              => 'Return Shipping',
			'wrs_box_damage_label'       => 'Retail Box Damage',
			'wrs_email_note'             => 'Return shipping note',
			'wrs_box_damage_email_note'  => 'Retail box damage note',
		);
	}

	public function test_collect_returns_return_shipping_and_box_damage_separately(): void {
		$refund = new WC_Order_Refund( 30.0 );
		$refund->add_meta_data( '_wrs_return_fee', 10.0, true );
		$refund->add_meta_data( '_wrs_box_damage_fee', 5.0, true );

		$deductions = WRS_Email_Deductions::collect( $refund );

		$this->assertCount( 2, $deductions );
		$this->assertSame( 'Return Shipping', $deductions[0]['label'] );
		$this->assertSame( 10.0, $deductions[0]['amount'] );
		$this->assertSame( 'Retail Box Damage', $deductions[1]['label'] );
		$this->assertSame( 5.0, $deductions[1]['amount'] );
	}

	public function test_resolve_amount_does_not_throw_with_empty_label_or_fee_name(): void {
		$GLOBALS['wrs_test_options']['wrs_fee_label'] = '';

		$refund = new WC_Order_Refund( 30.0 );
		$refund->add_meta_data( '_wrs_return_fee', '', true );
		$refund->add_meta_data( '_wrs_box_damage_fee', '', true );

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( '' );
		$fee_item->set_total( 12.0 );
		$fee_item->set_amount( 12.0 );
		$fee_item->add_meta_data( '_wrs_fee_type', 'other_fee', true );
		$refund->add_item( $fee_item );

		$deductions = WRS_Email_Deductions::collect( $refund );

		$this->assertSame( array(), $deductions );
	}

	public function test_resolve_amount_uses_fee_type_when_label_is_empty(): void {
		$GLOBALS['wrs_test_options']['wrs_fee_label'] = '';

		$refund = new WC_Order_Refund( 30.0 );
		$refund->add_meta_data( '_wrs_return_fee', '', true );
		$refund->add_meta_data( '_wrs_box_damage_fee', '', true );

		$fee_item = new WC_Order_Item_Fee();
		$fee_item->set_name( 'unused' );
		$fee_item->set_total( 12.0 );
		$fee_item->set_amount( 12.0 );
		$fee_item->add_meta_data( '_wrs_fee_type', 'return_shipping', true );
		$refund->add_item( $fee_item );

		$deductions = WRS_Email_Deductions::collect( $refund );

		$this->assertCount( 1, $deductions );
		$this->assertSame( 'Return Shipping', $deductions[0]['label'] );
		$this->assertSame( 12.0, $deductions[0]['amount'] );
	}
}
