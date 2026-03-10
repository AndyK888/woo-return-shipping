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
}
