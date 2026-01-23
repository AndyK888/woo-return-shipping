<?php
/**
 * WRS Admin Handler
 *
 * @package WooReturnShipping
 * @version 1.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Admin
 */
class WRS_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_scripts( string $hook_suffix ): void {
		$screen = get_current_screen();
		
		$valid_screens = array( 
			'shop_order', 
			'woocommerce_page_wc-orders',
		);
		
		if ( ! $screen ) {
			return;
		}

		$is_order_screen = in_array( $screen->id, $valid_screens, true ) 
			|| ( 'post' === $screen->base && 'shop_order' === $screen->post_type );

		if ( ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			'1.5.0'
		);

		wp_enqueue_script(
			'wrs-admin-refund',
			WRS_PLUGIN_URL . 'assets/js/admin-refund.js',
			array( 'jquery' ),
			'1.5.0',
			true
		);

		wp_localize_script(
			'wrs-admin-refund',
			'wrsAdmin',
			array(
				'defaultFee' => floatval( get_option( 'wrs_default_fee', '10.00' ) ),
				'feeLabel'   => get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ),
			)
		);
	}
}
