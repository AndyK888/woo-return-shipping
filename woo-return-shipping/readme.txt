=== WooCommerce Return Shipping Deduction ===
Contributors: yourname
Tags: woocommerce, refund, return shipping, shipping fee, order management
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
WC requires at least: 8.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deduct return shipping fees from WooCommerce refunds. The fee appears only on the refund receipt, not the original order.

== Description ==

**WooCommerce Return Shipping Deduction** allows store admins to deduct a return shipping fee from refunds. The fee:

* **Does NOT appear** on the original order
* **DOES appear** as a line item on the refund
* **Is correctly deducted** from the amount sent to payment gateways
* **Shows in customer emails** with optional explanatory text

= Key Features =

* **Simple Admin UI** - Fee input field appears in the native WooCommerce refund modal
* **Configurable Defaults** - Set default fee amount, label, and tax status
* **Exempt Option** - Skip the fee for customers who pre-paid return shipping
* **Email Integration** - Fee appears in customer refund emails with optional note
* **Gateway Compatible** - Works with Stripe, PayPal, and other WooCommerce payment gateways
* **HPOS Compatible** - Full support for WooCommerce High-Performance Order Storage

= How It Works =

1. Process a refund as normal in WooCommerce
2. Enter the return shipping fee amount (or use the default)
3. The fee is added as a positive line item to the refund
4. Customer receives refund minus the fee
5. Payment gateway processes the net amount

= Example =

* Product refund: $179.95
* Return shipping fee: $10.00
* Customer receives: $169.95

== Installation ==

1. Upload the `woo-return-shipping` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → Settings → Advanced → Return Shipping to configure
4. Process refunds as normal - the fee input will appear in the refund modal

== Frequently Asked Questions ==

= Does the fee appear on the original order? =

No. The fee only exists on the refund object. The original order remains unchanged.

= Is it compatible with my payment gateway? =

The plugin works with any WooCommerce-compatible gateway that supports refunds. It has been specifically tested with Stripe and PayPal official plugins.

= Can I exempt certain refunds from the fee? =

Yes. Each refund has an "Exempt from return fee" checkbox for customers who pre-paid their return shipping.

= Is it compatible with HPOS? =

Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage.

= Can I customize the fee label in emails? =

Yes. Go to WooCommerce → Settings → Advanced → Return Shipping to customize the fee label and email note.

== Screenshots ==

1. Refund modal with return shipping fee input
2. Settings page configuration
3. Refund order showing fee line item
4. Customer refund email with fee

== Changelog ==

= 1.0.0 =
* Initial release
* Refund modal integration
* Settings page with configurable options
* Email integration
* HPOS compatibility
* Stripe and PayPal compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce Return Shipping Deduction.
