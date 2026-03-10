<?php
/**
 * Deduction validation helper.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

final class WRS_Deduction_Validator {

	/**
	 * Validate configured deductions against the refund amount.
	 *
	 * @param float $gross_refund        Refund amount before deductions.
	 * @param float $return_shipping_fee Return shipping deduction.
	 * @param float $box_damage_fee      Retail box damage deduction.
	 * @return array<string, float|bool|string>
	 */
	public static function validate( float $gross_refund, float $return_shipping_fee, float $box_damage_fee ): array {
		if ( $return_shipping_fee < 0 || $box_damage_fee < 0 ) {
			return array(
				'is_valid'   => false,
				'error_code' => 'negative_deduction',
			);
		}

		$total_deductions = $return_shipping_fee + $box_damage_fee;

		if ( $total_deductions > $gross_refund ) {
			return array(
				'is_valid'   => false,
				'error_code' => 'combined_deductions_exceed_refund',
			);
		}

		return array(
			'is_valid'         => true,
			'total_deductions' => $total_deductions,
			'net_refund'       => $gross_refund - $total_deductions,
		);
	}
}
