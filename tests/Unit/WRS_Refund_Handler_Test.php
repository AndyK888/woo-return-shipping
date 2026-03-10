<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Refund_Handler_Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wrs_test_options'] = array(
			'wrs_fee_label'        => 'Return Shipping',
			'wrs_box_damage_label' => 'Retail Box Damage',
			'wrs_tax_status'       => 'none',
		);
		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	public function test_modify_refund_amount_applies_both_deductions_and_adds_fee_items(): void {
		$refund = new WC_Order_Refund( 40.0 );

		$_POST['wrs_apply_fee']            = '1';
		$_POST['wrs_return_shipping_fee']  = '10.00';
		$_POST['wrs_apply_box_damage_fee'] = '1';
		$_POST['wrs_box_damage_fee']       = '5.00';

		WRS_Refund_Handler::modify_refund_amount( $refund, array() );

		$this->assertSame( 25.0, $refund->get_amount() );
		$this->assertSame( -25.0, $refund->get_total() );
		$this->assertSame( 10.0, $refund->get_meta( '_wrs_return_fee' ) );
		$this->assertSame( 5.0, $refund->get_meta( '_wrs_box_damage_fee' ) );
		$this->assertCount( 2, $refund->get_items( 'fee' ) );
	}

	public function test_modify_refund_amount_throws_when_combined_deductions_exceed_refund(): void {
		$refund = new WC_Order_Refund( 10.0 );

		$_POST['wrs_apply_fee']            = '1';
		$_POST['wrs_return_shipping_fee']  = '7.00';
		$_POST['wrs_apply_box_damage_fee'] = '1';
		$_POST['wrs_box_damage_fee']       = '5.00';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Combined refund deductions cannot exceed the refund amount.' );

		WRS_Refund_Handler::modify_refund_amount( $refund, array() );
	}

	public function test_modify_refund_amount_throws_when_posted_deduction_is_negative(): void {
		$refund = new WC_Order_Refund( 10.0 );

		$_POST['wrs_apply_fee']           = '1';
		$_POST['wrs_return_shipping_fee'] = '-1.00';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Return Shipping amount must be a valid non-negative number.' );

		WRS_Refund_Handler::modify_refund_amount( $refund, array() );
	}
}
