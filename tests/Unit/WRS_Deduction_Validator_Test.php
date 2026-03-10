<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WRS_Deduction_Validator_Test extends TestCase {

	public function test_validate_returns_net_amount_for_valid_deductions(): void {
		$result = WRS_Deduction_Validator::validate( 40.0, 10.0, 5.0 );

		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 15.0, $result['total_deductions'] );
		$this->assertSame( 25.0, $result['net_refund'] );
	}

	public function test_when_combined_deductions_exceed_refund_returns_error(): void {
		$result = WRS_Deduction_Validator::validate( 20.0, 15.0, 10.0 );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'combined_deductions_exceed_refund', $result['error_code'] );
	}

	public function test_when_any_deduction_is_negative_returns_error(): void {
		$result = WRS_Deduction_Validator::validate( 20.0, -1.0, 0.0 );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'negative_deduction', $result['error_code'] );
	}
}
