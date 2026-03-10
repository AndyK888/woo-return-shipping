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

		$deductions = WRS_Email_Deductions::collect( $refund );
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
