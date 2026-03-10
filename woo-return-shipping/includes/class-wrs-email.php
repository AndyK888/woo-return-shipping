<?php
/**
 * WRS Email Handler
 *
 * Handles email modifications for refund notifications.
 *
 * @package WooReturnShipping
 * @version 2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Email
 *
 * Ensures refund deductions are visible in refund emails.
 */
class WRS_Email {

	/**
	 * Initialize email hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'add_refund_note' ), 99, 4 );
	}

	/**
	 * Add deduction notes to refund emails.
	 *
	 * @param WC_Order|WC_Order_Refund $order         Order object.
	 * @param bool                      $sent_to_admin Whether email is for admin.
	 * @param bool                      $plain_text    Whether email is plain text.
	 * @param WC_Email|null             $email         Email object.
	 */
	public static function add_refund_note( $order, bool $sent_to_admin, bool $plain_text, $email ): void {
		if ( ! $email ) {
			return;
		}

		$valid_emails = array( 'customer_refunded_order', 'customer_partially_refunded_order' );
		if ( ! in_array( $email->id, $valid_emails, true ) ) {
			return;
		}

		$refund = self::get_latest_refund( $order );

		if ( ! $refund ) {
			return;
		}

		$deductions = self::get_refund_deductions( $refund );
		if ( empty( $deductions ) ) {
			return;
		}

		foreach ( $deductions as $deduction ) {
			self::render_fee_note(
				$plain_text,
				$deduction['label'],
				$deduction['amount'],
				$deduction['note']
			);
		}
	}

	/**
	 * Get the latest refund for an order.
	 *
	 * @param WC_Order|WC_Order_Refund $order Order object.
	 * @return WC_Order_Refund|null
	 */
	private static function get_latest_refund( $order ): ?WC_Order_Refund {
		if ( $order instanceof WC_Order_Refund ) {
			return $order;
		}

		if ( ! method_exists( $order, 'get_refunds' ) ) {
			return null;
		}

		$refunds = $order->get_refunds();

		if ( empty( $refunds ) ) {
			return null;
		}

		return $refunds[0];
	}

	/**
	 * Get all supported refund deductions from a refund.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @return array<int, array{label: string, amount: float, note: string}>
	 */
	private static function get_refund_deductions( WC_Order_Refund $refund ): array {
		$deduction_definitions = array(
			array(
				'meta_key'     => '_wrs_return_fee',
				'label_option' => 'wrs_fee_label',
				'default'      => __( 'Return Shipping', 'woo-return-shipping' ),
				'note_option'  => 'wrs_email_note',
				'note_default' => __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' ),
				'fee_type'     => 'return_shipping',
			),
			array(
				'meta_key'     => '_wrs_box_damage_fee',
				'label_option' => 'wrs_box_damage_label',
				'default'      => __( 'Retail Box Damage', 'woo-return-shipping' ),
				'note_option'  => 'wrs_box_damage_email_note',
				'note_default' => __( 'A retail box damage fee has been deducted from your refund.', 'woo-return-shipping' ),
				'fee_type'     => 'retail_box_damage',
			),
		);

		$deductions = array();

		foreach ( $deduction_definitions as $definition ) {
			$label = get_option( $definition['label_option'], $definition['default'] );
			$amount = self::get_refund_fee_amount(
				$refund,
				$definition['meta_key'],
				$label,
				$definition['fee_type']
			);

			if ( $amount <= 0 ) {
				continue;
			}

			$deductions[] = array(
				'label'  => $label,
				'amount' => $amount,
				'note'   => get_option( $definition['note_option'], $definition['note_default'] ),
			);
		}

		return $deductions;
	}

	/**
	 * Get a refund fee amount from meta or fee items.
	 *
	 * @param WC_Order_Refund $refund   Refund object.
	 * @param string          $meta_key Refund meta key.
	 * @param string          $label    Fee label.
	 * @param string          $fee_type Managed fee type.
	 * @return float
	 */
	private static function get_refund_fee_amount( WC_Order_Refund $refund, string $meta_key, string $label, string $fee_type ): float {
		$fee_amount = $refund->get_meta( $meta_key );
		if ( ! empty( $fee_amount ) ) {
			return floatval( $fee_amount );
		}

		foreach ( $refund->get_items( 'fee' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			$item_name = $item->get_name();
			$is_our_fee = $fee_type === $item->get_meta( '_wrs_fee_type' );
			$name_match = stripos( $item_name, $label ) !== false;
			$name_match_reverse = stripos( $label, $item_name ) !== false;

			if ( $is_our_fee || $name_match || $name_match_reverse ) {
				$total = abs( floatval( $item->get_total() ) );
				if ( $total > 0 ) {
					return $total;
				}
			}
		}

		return 0.0;
	}

	/**
	 * Render a deduction note in the appropriate email format.
	 *
	 * @param bool   $plain_text Whether email is plain text.
	 * @param string $label      Deduction label.
	 * @param float  $amount     Deduction amount.
	 * @param string $note       Deduction note.
	 */
	private static function render_fee_note( bool $plain_text, string $label, float $amount, string $note ): void {
		if ( $plain_text ) {
			echo "\n" . esc_html( $label ) . ': ' . wp_strip_all_tags( wc_price( $amount ) ) . "\n";
			echo esc_html( $note ) . "\n";
			return;
		}
		?>
		<div class="wrs-refund-note" style="margin: 16px 0; padding: 12px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
			<p style="margin: 0 0 8px 0; font-weight: bold; color: #856404;">
				<?php echo esc_html( $label ); ?>: <?php echo wc_price( $amount ); ?>
			</p>
			<p style="margin: 0; color: #856404;">
				<?php echo esc_html( $note ); ?>
			</p>
		</div>
		<?php
	}
}
