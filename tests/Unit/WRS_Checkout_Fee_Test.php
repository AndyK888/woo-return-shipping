<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Checkout_Fee_Test extends TestCase {

	public function test_is_our_fee_recognizes_both_fee_types(): void {
		$item = WRS_Fee_Factory::create_hidden_fee_item( 'Retail Box Damage', 'retail_box_damage' );

		$this->assertTrue( WRS_Checkout_Fee::is_our_fee( $item ) );
	}
}
