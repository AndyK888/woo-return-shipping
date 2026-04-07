<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Fee_Factory_Test extends TestCase {

	public function test_create_refund_fee_item_sets_fee_type_metadata(): void {
		$fee_item = WRS_Fee_Factory::create_refund_fee_item( 'Retail Box Damage', 5.0, 'retail_box_damage' );

		$this->assertSame( 'Retail Box Damage', $fee_item->get_name() );
		$this->assertSame( -5.0, $fee_item->get_total() );
		$this->assertSame( 'retail_box_damage', $fee_item->get_meta( '_wrs_fee_type' ) );
		$this->assertSame( 'yes', $fee_item->get_meta( '_wrs_fee' ) );
	}

	public function test_create_linked_refund_fee_item_sets_refunded_item_id(): void {
		$order_fee_item = WRS_Fee_Factory::create_hidden_fee_item( 'Return Shipping', 'return_shipping' );
		$order_fee_item->set_id( 91 );

		$fee_item = WRS_Fee_Factory::create_linked_refund_fee_item( $order_fee_item, 10.0 );

		$this->assertSame( 'Return Shipping', $fee_item->get_name() );
		$this->assertSame( -10.0, $fee_item->get_total() );
		$this->assertSame( 91, $fee_item->get_meta( '_refunded_item_id' ) );
		$this->assertSame( 'return_shipping', $fee_item->get_meta( '_wrs_fee_type' ) );
	}
}
