<?php
/**
 * Plugin Name: WooCommerce Return Shipping Deduction
 * Plugin URI: https://github.com/your-repo/woo-return-shipping
 * Description: Deduct return shipping fees from refunds. The fee appears only on the refund receipt, not the original order.
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-return-shipping
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package WooReturnShipping
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WRS_VERSION', '1.2.0' );
define( 'WRS_PLUGIN_FILE', __FILE__ );
define( 'WRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WRS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wrs_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function wrs_woocommerce_missing_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( '%s requires WooCommerce to be installed and active.', 'woo-return-shipping' ),
				'<strong>WooCommerce Return Shipping Deduction</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function wrs_init(): void {
	// Check WooCommerce dependency.
	if ( ! wrs_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wrs_woocommerce_missing_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'woo-return-shipping', false, dirname( WRS_PLUGIN_BASENAME ) . '/languages' );

	// Include required files.
	require_once WRS_PLUGIN_DIR . 'includes/class-wrs-settings.php';
	require_once WRS_PLUGIN_DIR . 'includes/class-wrs-admin.php';
	require_once WRS_PLUGIN_DIR . 'includes/class-wrs-refund-handler.php';
	require_once WRS_PLUGIN_DIR . 'includes/class-wrs-email.php';

	// Initialize classes.
	WRS_Settings::init();
	WRS_Admin::init();
	WRS_Refund_Handler::init();
	WRS_Email::init();
}
add_action( 'plugins_loaded', 'wrs_init' );

/**
 * Plugin activation hook.
 */
function wrs_activate(): void {
	// Set default options if not already set.
	if ( false === get_option( 'wrs_default_fee' ) ) {
		add_option( 'wrs_default_fee', '10.00' );
	}
	if ( false === get_option( 'wrs_fee_label' ) ) {
		add_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) );
	}
	if ( false === get_option( 'wrs_tax_status' ) ) {
		add_option( 'wrs_tax_status', 'none' );
	}
	if ( false === get_option( 'wrs_email_note' ) ) {
		add_option( 'wrs_email_note', __( 'A return shipping fee has been deducted from your refund.', 'woo-return-shipping' ) );
	}
	if ( false === get_option( 'wrs_show_reason_field' ) ) {
		add_option( 'wrs_show_reason_field', 'no' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wrs_activate' );

/**
 * Plugin deactivation hook.
 */
function wrs_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wrs_deactivate' );

/**
 * Get plugin option with default fallback.
 *
 * @param string $key     Option key (without wrs_ prefix).
 * @param mixed  $default Default value.
 * @return mixed
 */
function wrs_get_option( string $key, $default = '' ) {
	return get_option( 'wrs_' . $key, $default );
}
