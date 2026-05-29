<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Checkout_Fee_Test extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['wrs_test_options'] = array(
			'wrs_fee_label'        => 'Return Shipping',
			'wrs_box_damage_label' => 'Retail Box Damage',
		);
	}

	public function test_hide_fee_in_totals_does_not_hide_when_hidden_labels_are_empty(): void {
		$GLOBALS['wrs_test_options'] = array(
			'wrs_fee_label'        => '',
			'wrs_box_damage_label' => '',
		);

		$total_rows = array(
			'fee-1' => array(
				'label' => 'Return Shipping',
			),
			'subtotal' => array(
				'label' => 'Subtotal',
			),
		);

		$result = WRS_Checkout_Fee::hide_fee_in_totals( $total_rows, null, 'incl' );

		$this->assertArrayHasKey( 'fee-1', $result );
		$this->assertArrayHasKey( 'subtotal', $result );
	}

	public function test_filter_order_items_removes_hidden_fees_when_types_are_empty_or_not_provided(): void {
		$items = array(
			'return_shipping_fee' => WRS_Fee_Factory::create_hidden_fee_item( 'Return Shipping', 'return_shipping' ),
			'product_fee'        => new WC_Order_Item_Fee(),
		);
		$items['product_fee']->set_name( 'Product' );
		$items['product_fee']->set_total( 8.00 );
		$items['product_fee']->set_amount( 8.00 );

		$result = WRS_Checkout_Fee::filter_order_items( $items, new stdClass() );

		$this->assertArrayNotHasKey( 'return_shipping_fee', $result );
		$this->assertArrayHasKey( 'product_fee', $result );
	}

	public function test_filter_order_items_short_circuits_when_types_do_not_include_fee(): void {
		$items = array(
			'return_shipping_fee' => WRS_Fee_Factory::create_hidden_fee_item( 'Return Shipping', 'return_shipping' ),
			'product_fee'        => new WC_Order_Item_Fee(),
		);
		$items['product_fee']->set_name( 'Product' );
		$items['product_fee']->set_total( 8.00 );
		$items['product_fee']->set_amount( 8.00 );

		$result = WRS_Checkout_Fee::filter_order_items( $items, new stdClass(), array( 'shipping' ) );

		$this->assertSame( $items, $result );
	}

	public function test_is_our_fee_recognizes_both_fee_types(): void {
		$item = WRS_Fee_Factory::create_hidden_fee_item( 'Retail Box Damage', 'retail_box_damage' );

		$this->assertTrue( WRS_Checkout_Fee::is_our_fee( $item ) );
	}
}
