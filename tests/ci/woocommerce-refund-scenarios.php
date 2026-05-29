<?php
/**
 * WooCommerce integration scenarios for CI.
 *
 * Verifies refund math, refund fee items, and customer email body content.
 */

declare( strict_types = 1 );

$root = $argv[1] ?? getenv( 'WP_ROOT' );

if ( ! $root ) {
	$root = getenv( 'GITHUB_WORKSPACE' ) ?: __DIR__ . '/../..';
}

if ( ! is_dir( $root ) ) {
	fwrite( STDERR, "WP root not found at {$root}\n" );
	exit( 1 );
}

$required_file = rtrim( $root, '/' ) . '/wp-load.php';
if ( ! file_exists( $required_file ) ) {
	fwrite( STDERR, "Missing wp-load.php at {$required_file}\n" );
	exit( 1 );
}

require_once $required_file;

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce not available in this environment\n" );
	exit( 1 );
}

if ( ! class_exists( 'WC_Product_Simple' ) || ! function_exists( 'wc_create_order' ) || ! function_exists( 'wc_create_refund' ) ) {
	fwrite( STDERR, "WooCommerce API classes not available\n" );
	exit( 1 );
}

$gross_amount    = 40.0;
$refund_gross    = 40.0;
$scenarios = array(
	array(
		'name'         => 'without_fee',
		'return_fee'   => 0.0,
		'box_fee'      => 0.0,
		'labels'       => array(),
	),
	array(
		'name'         => 'only_return_shipping',
		'return_fee'   => 10.0,
		'box_fee'      => 0.0,
		'labels'       => array( 'Return Shipping' ),
	),
	array(
		'name'         => 'only_box_damage',
		'return_fee'   => 0.0,
		'box_fee'      => 7.0,
		'labels'       => array( 'Retail Box Damage' ),
	),
	array(
		'name'         => 'all_fees',
		'return_fee'   => 10.0,
		'box_fee'      => 7.0,
		'labels'       => array( 'Return Shipping', 'Retail Box Damage' ),
	),
);

$results = array();

function wrs_assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "[refund-scenarios] FAIL: {$message}\n" );
		exit( 1 );
	}
}

function wrs_render_email_body( int $order_id, bool $plain_text ): string {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return '';
	}

	$email = (object) array( 'id' => 'customer_refunded_order' );
	ob_start();
	WRS_Email::add_refund_note( $order, false, $plain_text, $email );
	return (string) ob_get_clean();
}

foreach ( $scenarios as $scenario ) {
	$scenario_name = $scenario['name'];
	$return_fee    = $scenario['return_fee'];
	$box_fee       = $scenario['box_fee'];
	$expected_net  = max( 0.0, $refund_gross - $return_fee - $box_fee );

	$product = new WC_Product_Simple();
	$product->set_name( "CI Refund Product {$scenario_name}" );
	$product->set_regular_price( (string) $gross_amount );
	$product->set_price( (string) $gross_amount );
	$product->set_sku( 'wrs-ci-' . $scenario_name );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'hidden' );
	$product->save();

	$order = wc_create_order();
	wrs_assert_true( false === is_wp_error( $order ), 'Failed to create order object.' );

	$order->add_product( $product );
	$order->set_billing_email( 'customer@example.com' );
	$order->set_billing_first_name( 'Integration' );
	$order->set_billing_last_name( 'Test' );
	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( 'Cash on Delivery' );
	$order->calculate_totals();
	$order->set_status( 'processing' );
	$order->save();

	WRS_Checkout_Fee::add_fee_to_order( (int) $order->get_id(), array(), $order );
	$order->calculate_totals();
	$order->save();

	$_POST = array();
	if ( $return_fee > 0 ) {
		$_POST['wrs_apply_fee']          = '1';
		$_POST['wrs_return_shipping_fee'] = (string) number_format( $return_fee, 2, '.', '' );
	}
	if ( $box_fee > 0 ) {
		$_POST['wrs_apply_box_damage_fee'] = '1';
		$_POST['wrs_box_damage_fee']       = (string) number_format( $box_fee, 2, '.', '' );
	}

	$refund = wc_create_refund(
		array(
			'amount'         => $refund_gross,
			'order_id'       => (int) $order->get_id(),
			'reason'         => 'CI Refund Scenario',
			'refund_payment' => false,
			'line_items'     => array(),
		)
	);

	$_POST = array();
	wrs_assert_true( ! is_wp_error( $refund ), 'WC refund creation failed for scenario: ' . $scenario_name );

	$refund_id = $refund->get_id();
	wrs_assert_true( 0 !== (int) $refund_id, 'Refund ID missing for scenario: ' . $scenario_name );

	$actual_refund_amount = (float) $refund->get_amount();
	$actual_refund_total  = (float) $refund->get_total();
	wrs_assert_true( abs( $actual_refund_amount - $expected_net ) < 0.0001, sprintf( 'Net refund mismatch for scenario %s: expected %0.4f got %0.4f', $scenario_name, $expected_net, $actual_refund_amount ) );
	wrs_assert_true( abs( $actual_refund_total + $expected_net ) < 0.0001, sprintf( 'Refund total mismatch for scenario %s: expected %0.4f got %0.4f', $scenario_name, -$expected_net, $actual_refund_total ) );

	$actual_return_fee = (float) $refund->get_meta( '_wrs_return_fee' );
	$actual_box_fee    = (float) $refund->get_meta( '_wrs_box_damage_fee' );
	wrs_assert_true( abs( $actual_return_fee - $return_fee ) < 0.0001, sprintf( 'Stored return fee mismatch for %s: expected %0.4f got %0.4f', $scenario_name, $return_fee, $actual_return_fee ) );
	wrs_assert_true( abs( $actual_box_fee - $box_fee ) < 0.0001, sprintf( 'Stored box fee mismatch for %s: expected %0.4f got %0.4f', $scenario_name, $box_fee, $actual_box_fee ) );

	$actual_fee_labels = array();
	foreach ( $refund->get_items( 'fee' ) as $item ) {
		$actual_fee_labels[] = (string) $item->get_name();
	}

	wrs_assert_true( count( $actual_fee_labels ) === count( $scenario['labels'] ), sprintf( 'Refund fee row count mismatch for %s: expected %d got %d', $scenario_name, count( $scenario['labels'] ), count( $actual_fee_labels ) ) );

	foreach ( $scenario['labels'] as $label ) {
		wrs_assert_true( in_array( $label, $actual_fee_labels, true ), sprintf( 'Expected label %s missing for scenario %s', $label, $scenario_name ) );
	}

	$plain_email = wrs_render_email_body( (int) $order->get_id(), true );
	$note_email  = wrs_render_email_body( (int) $order->get_id(), false );
	if ( 0 === count( $scenario['labels'] ) ) {
		wrs_assert_true( '' === trim( $plain_email ), 'Expected no email deduction body for scenario ' . $scenario_name );
		wrs_assert_true( '' === trim( $note_email ), 'Expected no html email deduction body for scenario ' . $scenario_name );
	} else {
		foreach ( $scenario['labels'] as $label ) {
			wrs_assert_true( strpos( $plain_email, $label . ':' ) !== false, sprintf( 'Expected label %s in plain email body for scenario %s', $label, $scenario_name ) );
			wrs_assert_true( strpos( $note_email, $label ) !== false, sprintf( 'Expected label %s in html email body for scenario %s', $label, $scenario_name ) );
		}
	}

	$results[] = array(
		'name'                 => $scenario_name,
		'refund_id'            => $refund_id,
		'order_id'             => (int) $order->get_id(),
		'net_refund_amount'    => $actual_refund_amount,
		'expected_net_amount'  => $expected_net,
		'refund_total'         => $actual_refund_total,
		'refund_fee_labels'    => $actual_fee_labels,
		'plain_email_present'  => trim( $plain_email ),
		'html_email_present'   => trim( $note_email ),
	);
}

echo json_encode( array( 'status' => 'passed', 'scenarios' => $results ), JSON_PRETTY_PRINT ) . "\n";
