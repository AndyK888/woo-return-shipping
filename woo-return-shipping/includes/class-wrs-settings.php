<?php
/**
 * WRS Settings Handler
 *
 * Adds plugin settings to WooCommerce → Settings → Advanced tab.
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Settings
 *
 * Handles plugin settings via WooCommerce Settings API.
 */
class WRS_Settings {

	/**
	 * Initialize settings.
	 */
	public static function init(): void {
		add_filter( 'woocommerce_get_sections_advanced', array( __CLASS__, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_advanced', array( __CLASS__, 'get_settings' ), 10, 2 );
	}

	/**
	 * Add settings section to Advanced tab.
	 *
	 * @param array $sections Existing sections.
	 * @return array
	 */
	public static function add_section( array $sections ): array {
		$sections['wrs_return_shipping'] = __( 'Return Shipping', 'woo-return-shipping' );
		return $sections;
	}

	/**
	 * Get settings fields.
	 *
	 * @param array  $settings        Existing settings.
	 * @param string $current_section Current section ID.
	 * @return array
	 */
	public static function get_settings( array $settings, string $current_section ): array {
		if ( 'wrs_return_shipping' !== $current_section ) {
			return $settings;
		}

		$wrs_settings = array(
			array(
				'title' => __( 'Return Shipping Fee Settings', 'woo-return-shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the default return shipping fee that can be deducted from refunds.', 'woo-return-shipping' ),
				'id'    => 'wrs_settings_section',
			),

			array(
				'title'             => __( 'Default Fee Amount', 'woo-return-shipping' ),
				'desc'              => __( 'The default return shipping fee to deduct from refunds.', 'woo-return-shipping' ),
				'id'                => 'wrs_default_fee',
				'type'              => 'number',
				'default'           => '10.00',
				'css'               => 'width: 100px;',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),

			array(
				'title'   => __( 'Fee Label', 'woo-return-shipping' ),
				'desc'    => __( 'The label shown on refund receipts and emails.', 'woo-return-shipping' ),
				'id'      => 'wrs_fee_label',
				'type'    => 'text',
				'default' => __( 'Return Shipping', 'woo-return-shipping' ),
				'css'     => 'width: 250px;',
			),

			array(
				'title'             => __( 'Retail Box Damage Default Fee', 'woo-return-shipping' ),
				'desc'              => __( 'The default retail box damage fee to deduct from refunds.', 'woo-return-shipping' ),
				'id'                => 'wrs_box_damage_default_fee',
				'type'              => 'number',
				'default'           => '0.00',
				'css'               => 'width: 100px;',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			),

			array(
				'title'   => __( 'Retail Box Damage Label', 'woo-return-shipping' ),
				'desc'    => __( 'The label shown on refund receipts and emails for the retail box damage fee.', 'woo-return-shipping' ),
				'id'      => 'wrs_box_damage_label',
				'type'    => 'text',
				'default' => __( 'Retail Box Damage', 'woo-return-shipping' ),
				'css'     => 'width: 250px;',
			),

			array(
				'title'   => __( 'Tax Status', 'woo-return-shipping' ),
				'desc'    => __( 'Whether the return shipping fee is taxable.', 'woo-return-shipping' ),
				'id'      => 'wrs_tax_status',
				'type'    => 'select',
				'default' => 'none',
				'options' => array(
					'none'    => __( 'Not taxable', 'woo-return-shipping' ),
					'taxable' => __( 'Taxable', 'woo-return-shipping' ),
				),
			),

			array(
				'title'   => __( 'Tax Class', 'woo-return-shipping' ),
				'desc'    => __( 'If taxable, which tax class to use.', 'woo-return-shipping' ),
				'id'      => 'wrs_tax_class',
				'type'    => 'select',
				'default' => '',
				'options' => self::get_tax_class_options(),
			),

			array(
				'title'   => __( 'Add Fee Line to Orders', 'woo-return-shipping' ),
				'desc'    => __( 'Add a $0 "Return Shipping" line to new orders. This appears in the refund table, making it easy to enter the fee amount.', 'woo-return-shipping' ),
				'id'      => 'wrs_add_checkout_fee',
				'type'    => 'checkbox',
				'default' => 'yes',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wrs_settings_section',
			),

			array(
				'title' => __( 'Customer Communication', 'woo-return-shipping' ),
				'type'  => 'title',
				'desc'  => __( 'Configure how the return shipping fee is communicated to customers.', 'woo-return-shipping' ),
				'id'    => 'wrs_communication_section',
			),

			array(
				'title'   => __( 'Email Note', 'woo-return-shipping' ),
				'desc'    => __( 'Optional note to include in refund emails explaining the fee deduction.', 'woo-return-shipping' ),
				'id'      => 'wrs_email_note',
				'type'    => 'textarea',
				'default' => __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' ),
				'css'     => 'width: 400px; height: 80px;',
			),

			array(
				'title'   => __( 'Retail Box Damage Email Note', 'woo-return-shipping' ),
				'desc'    => __( 'Optional note to include in refund emails explaining the retail box damage deduction.', 'woo-return-shipping' ),
				'id'      => 'wrs_box_damage_email_note',
				'type'    => 'textarea',
				'default' => __( 'A retail box damage fee has been deducted from your refund.', 'woo-return-shipping' ),
				'css'     => 'width: 400px; height: 80px;',
			),

			array(
				'title'   => __( 'Show Reason Field', 'woo-return-shipping' ),
				'desc'    => __( 'Show a reason field in the admin refund modal', 'woo-return-shipping' ),
				'id'      => 'wrs_show_reason_field',
				'type'    => 'checkbox',
				'default' => 'no',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wrs_communication_section',
			),
		);

		return $wrs_settings;
	}

	/**
	 * Get tax class options for select field.
	 *
	 * @return array
	 */
	private static function get_tax_class_options(): array {
		$tax_classes = WC_Tax::get_tax_classes();
		$options     = array(
			'' => __( 'Standard', 'woo-return-shipping' ),
		);

		foreach ( $tax_classes as $tax_class ) {
			$options[ sanitize_title( $tax_class ) ] = $tax_class;
		}

		return $options;
	}
}
