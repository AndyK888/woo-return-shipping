<?php
/**
 * Refund email deduction collection helpers.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

final class WRS_Email_Deductions {

	/**
	 * Collect configured deductions from a refund.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @return array<int, array{label: string, amount: float, note: string}>
	 */
	public static function collect( WC_Order_Refund $refund ): array {
		$deductions = array();

		foreach ( self::get_definitions() as $definition ) {
			$label  = get_option( $definition['label_option'], $definition['default_label'] );
			$amount = self::resolve_amount( $refund, $definition['meta_key'], $label, $definition['fee_type'] );

			if ( $amount <= 0 ) {
				continue;
			}

			$deductions[] = array(
				'label'  => $label,
				'amount' => $amount,
				'note'   => get_option( $definition['note_option'], $definition['default_note'] ),
			);
		}

		return $deductions;
	}

	/**
	 * Get configured deduction definitions.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function get_definitions(): array {
		return array(
			array(
				'meta_key'      => '_wrs_return_fee',
				'label_option'  => 'wrs_fee_label',
				'default_label' => __( 'Return Shipping', 'woo-return-shipping' ),
				'note_option'   => 'wrs_email_note',
				'default_note'  => __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' ),
				'fee_type'      => 'return_shipping',
			),
			array(
				'meta_key'      => '_wrs_box_damage_fee',
				'label_option'  => 'wrs_box_damage_label',
				'default_label' => __( 'Retail Box Damage', 'woo-return-shipping' ),
				'note_option'   => 'wrs_box_damage_email_note',
				'default_note'  => __( 'A retail box damage fee has been deducted from your refund.', 'woo-return-shipping' ),
				'fee_type'      => 'retail_box_damage',
			),
		);
	}

	/**
	 * Resolve a deduction amount from refund meta first, then fallback fee items.
	 *
	 * @param WC_Order_Refund $refund   Refund object.
	 * @param string          $meta_key Refund meta key.
	 * @param string          $label    Fee label.
	 * @param string          $fee_type Managed fee type.
	 * @return float
	 */
	private static function resolve_amount( WC_Order_Refund $refund, string $meta_key, string $label, string $fee_type ): float {
		$fee_amount = $refund->get_meta( $meta_key );

		if ( '' !== $fee_amount && null !== $fee_amount ) {
			return (float) $fee_amount;
		}

		foreach ( $refund->get_items( 'fee' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			$item_name           = $item->get_name();
			$matches_fee_type    = $fee_type === $item->get_meta( '_wrs_fee_type' );
			$matches_label       = false !== stripos( $item_name, $label );
			$matches_label_alias = false !== stripos( $label, $item_name );

			if ( $matches_fee_type || $matches_label || $matches_label_alias ) {
				$total = abs( (float) $item->get_total() );

				if ( $total > 0 ) {
					return $total;
				}
			}
		}

		return 0.0;
	}
}
