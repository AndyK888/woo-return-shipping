<?php
/**
 * WRS Refund Handler
 *
 * Core logic - modifies refund amount BEFORE gateway processes it.
 *
 * @package WooReturnShipping
 * @version 1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Refund_Handler
 *
 * Handles refund deductions applied before gateway processing.
 *
 * KEY INSIGHT from WooCommerce source:
 * - `woocommerce_create_refund` action fires BEFORE refund->save() and BEFORE wc_refund_payment()
 * - wc_refund_payment() calls $gateway->process_refund($order_id, $refund->get_amount(), $reason)
 * - So we can modify $refund->set_amount() in this action to change what the gateway receives!
 */
class WRS_Refund_Handler {

	/**
	 * Initialize refund hooks.
	 */
	public static function init(): void {
		// This is THE KEY HOOK - fires before save and before gateway!
		add_action( 'woocommerce_create_refund', array( __CLASS__, 'modify_refund_amount' ), 5, 2 );

		// Add order note after refund completes.
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'add_order_note' ), 10, 2 );
	}

	/**
	 * Modify the refund amount to subtract configured deduction fees.
	 *
	 * This happens BEFORE the refund is saved and BEFORE the gateway processes it.
	 * The gateway will receive the REDUCED amount.
	 *
	 * @param WC_Order_Refund $refund The refund object.
	 * @param array           $args   Refund arguments.
	 */
	public static function modify_refund_amount( WC_Order_Refund $refund, array $args ): void {
		$return_shipping_label = get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
		$box_damage_label      = get_option( 'wrs_box_damage_label', __( 'Retail Box Damage', 'woo-return-shipping' ) );
		$return_shipping_fee   = self::get_posted_fee_amount( 'wrs_apply_fee', 'wrs_return_shipping_fee', $return_shipping_label );
		$box_damage_fee        = self::get_posted_fee_amount( 'wrs_apply_box_damage_fee', 'wrs_box_damage_fee', $box_damage_label );
		$order                 = self::get_refund_order( $refund, $args );

		if ( $return_shipping_fee <= 0 && $box_damage_fee <= 0 ) {
			return;
		}

		// Get the original refund amount (positive number).
		$original_amount = abs( $refund->get_amount() );
		$validation      = WRS_Deduction_Validator::validate( $original_amount, $return_shipping_fee, $box_damage_fee );

		if ( empty( $validation['is_valid'] ) ) {
			throw new Exception( self::get_validation_error_message( (string) $validation['error_code'] ) );
		}

		// Calculate the NET refund amount (what customer actually gets).
		$net_amount = (float) $validation['net_refund'];

		// MODIFY THE REFUND AMOUNT - this is what the gateway will receive!
		$refund->set_amount( $net_amount );

		// Also update the total (negative value for refunds).
		$refund->set_total( $net_amount * -1 );

		// Store metadata for record-keeping.
		$refund->add_meta_data( '_wrs_return_fee', $return_shipping_fee, true );
		$refund->add_meta_data( '_wrs_box_damage_fee', $box_damage_fee, true );
		$refund->add_meta_data( '_wrs_original_amount', $original_amount, true );
		$refund->add_meta_data( '_wrs_net_amount', $net_amount, true );

		if ( $return_shipping_fee > 0 ) {
			$refund->add_item(
				self::create_refund_fee_item(
					$order,
					$return_shipping_label,
					$return_shipping_fee,
					'return_shipping'
				)
			);
		}

		if ( $box_damage_fee > 0 ) {
			$refund->add_item(
				self::create_refund_fee_item(
					$order,
					$box_damage_label,
					$box_damage_fee,
					'retail_box_damage'
				)
			);
		}
	}

	/**
	 * Add order note after refund is complete.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function add_order_note( int $order_id, int $refund_id ): void {
		$refund = wc_get_order( $refund_id );

		if ( ! $refund ) {
			return;
		}

		$return_shipping_fee = floatval( $refund->get_meta( '_wrs_return_fee' ) );
		$box_damage_fee      = floatval( $refund->get_meta( '_wrs_box_damage_fee' ) );
		$original_amount = $refund->get_meta( '_wrs_original_amount' );
		$net_amount      = $refund->get_meta( '_wrs_net_amount' );

		if ( $return_shipping_fee <= 0 && $box_damage_fee <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$applied_fees = array();

		if ( $return_shipping_fee > 0 ) {
			$applied_fees[] = sprintf(
				/* translators: 1: fee label, 2: fee amount */
				__( '%1$s %2$s', 'woo-return-shipping' ),
				get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ),
				wc_price( $return_shipping_fee )
			);
		}

		if ( $box_damage_fee > 0 ) {
			$applied_fees[] = sprintf(
				/* translators: 1: fee label, 2: fee amount */
				__( '%1$s %2$s', 'woo-return-shipping' ),
				get_option( 'wrs_box_damage_label', __( 'Retail Box Damage', 'woo-return-shipping' ) ),
				wc_price( $box_damage_fee )
			);
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: applied fee list, 2: original amount, 3: net refund */
				__( 'Refund deductions applied: %1$s. Original refund %2$s = Net refund %3$s (sent to gateway)', 'woo-return-shipping' ),
				implode( ', ', $applied_fees ),
				wc_price( $original_amount ),
				wc_price( $net_amount )
			)
		);
	}

	/**
	 * Get a posted deduction amount when its checkbox is enabled.
	 *
	 * @param string $apply_key  Checkbox field key.
	 * @param string $amount_key Amount field key.
	 * @param string $label      Deduction label for error messages.
	 * @return float
	 */
	private static function get_posted_fee_amount( string $apply_key, string $amount_key, string $label ): float {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		$is_enabled = isset( $_POST[ $apply_key ] ) && '1' === $_POST[ $apply_key ];
		if ( ! $is_enabled ) {
			return 0.0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		if ( empty( $_POST[ $amount_key ] ) ) {
			return 0.0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce.
		$raw_amount = sanitize_text_field( wp_unslash( $_POST[ $amount_key ] ) );

		if ( ! is_numeric( $raw_amount ) || (float) $raw_amount < 0 ) {
			throw new Exception(
				sprintf(
					/* translators: %s: deduction label */
					__( '%s amount must be a valid non-negative number.', 'woo-return-shipping' ),
					$label
				)
			);
		}

		return (float) $raw_amount;
	}

	/**
	 * Convert a validator error code into an admin-facing message.
	 *
	 * @param string $error_code Validator error code.
	 * @return string
	 */
	private static function get_validation_error_message( string $error_code ): string {
		switch ( $error_code ) {
			case 'combined_deductions_exceed_refund':
				return __( 'Combined refund deductions cannot exceed the refund amount.', 'woo-return-shipping' );
			case 'negative_deduction':
				return __( 'Refund deductions must be valid non-negative amounts.', 'woo-return-shipping' );
			default:
				return __( 'Invalid refund deductions.', 'woo-return-shipping' );
		}
	}

	/**
	 * Resolve the parent order for the current refund operation.
	 *
	 * @param WC_Order_Refund $refund Refund object.
	 * @param array           $args   Refund arguments.
	 * @return WC_Order|null
	 */
	private static function get_refund_order( WC_Order_Refund $refund, array $args ) {
		$order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;

		if ( 0 === $order_id && method_exists( $refund, 'get_parent_id' ) ) {
			$order_id = (int) $refund->get_parent_id();
		}

		if ( $order_id <= 0 ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Create a refund fee item, linking it to the original hidden fee row when possible.
	 *
	 * @param WC_Order|null $order    Parent order.
	 * @param string        $label    Fee label.
	 * @param float         $amount   Deduction amount.
	 * @param string        $fee_type Managed fee type.
	 * @return WC_Order_Item_Fee
	 */
	private static function create_refund_fee_item( ?WC_Order $order, string $label, float $amount, string $fee_type ): WC_Order_Item_Fee {
		if ( $order instanceof WC_Order ) {
			$order_fee_item = self::find_order_fee_item( $order, $label, $fee_type );

			if ( $order_fee_item instanceof WC_Order_Item_Fee && $order_fee_item->get_id() > 0 ) {
				return WRS_Fee_Factory::create_linked_refund_fee_item( $order_fee_item, $amount );
			}
		}

		return WRS_Fee_Factory::create_refund_fee_item( $label, $amount, $fee_type );
	}

	/**
	 * Find the hidden order fee row backing a managed deduction type.
	 *
	 * @param WC_Order $order    Parent order.
	 * @param string   $label    Current configured label.
	 * @param string   $fee_type Managed fee type.
	 * @return WC_Order_Item_Fee|null
	 */
	private static function find_order_fee_item( WC_Order $order, string $label, string $fee_type ): ?WC_Order_Item_Fee {
		foreach ( $order->get_items( 'fee' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			if ( $fee_type === $item->get_meta( '_wrs_fee_type' ) ) {
				return $item;
			}

			if ( 'yes' !== $item->get_meta( '_wrs_fee' ) ) {
				continue;
			}

			$item_name = $item->get_name();
			if ( '' !== $item_name && ( false !== stripos( $item_name, $label ) || false !== stripos( $label, $item_name ) ) ) {
				return $item;
			}
		}

		return null;
	}
}
