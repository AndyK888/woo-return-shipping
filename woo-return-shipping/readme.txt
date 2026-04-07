=== WooCommerce Return Shipping Deduction ===
Contributors: Andrii Kaprii
Tags: woocommerce, refund, return shipping, shipping fee, order management
Requires at least: 6.0
Tested up to: 6.9.1
Requires PHP: 8.0
WC requires at least: 8.0
WC tested up to: 10.5.1
Stable tag: 2.7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deduct return shipping and retail box damage fees from WooCommerce refunds. Deductions appear only on the refund receipt, not the original order.

== Description ==

**WooCommerce Return Shipping Deduction** allows store admins to deduct return shipping and retail box damage fees from refunds. The deductions:

* **Does NOT appear** on the original order
* **DOES appear** against dedicated hidden fee rows on the refund
* **Is correctly deducted** from the amount sent to payment gateways
* **Shows in customer emails** with optional explanatory text

= Key Features =

* **Simple Admin UI** - Separate deduction fields appear in the native WooCommerce refund modal
* **Configurable Defaults** - Set default amounts, labels, and email notes
* **Separate Deduction Types** - Return shipping and retail box damage stay independent on refunds
* **Validation Guardrails** - Invalid combined deductions are blocked before refund submission and again on the server
* **Email Integration** - Each deduction appears separately in customer refund emails with its own note
* **Gateway Compatible** - Works with Stripe, PayPal, and other WooCommerce payment gateways
* **HPOS Compatible** - Full support for WooCommerce High-Performance Order Storage

= How It Works =

1. Process a refund as normal in WooCommerce
2. Enter the return shipping fee amount, retail box damage fee, or both
3. Each deduction is recorded against its matching hidden fee row on the refund
4. Customer receives refund minus the combined deductions
5. Payment gateway processes the net amount

= Example =

* Product refund: $179.95
* Return shipping fee: $10.00
* Retail box damage fee: $5.00
* Customer receives: $164.95

== Installation ==

1. Upload the `woo-return-shipping` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → Settings → Advanced → Return Shipping to configure
4. Process refunds as normal - the deduction inputs will appear in the refund modal

== Frequently Asked Questions ==

= Does the fee appear on the original order? =

No. The fee only exists on the refund object. The original order remains unchanged.

= Is it compatible with my payment gateway? =

The plugin works with any WooCommerce-compatible gateway that supports refunds. It has been specifically tested with Stripe and PayPal official plugins.

= Can I apply more than one deduction on the same refund? =

Yes. Return shipping and retail box damage can both be applied on the same refund and are shown separately.

= What happens if the deductions are higher than the refund amount? =

The refund is blocked. The admin refund panel shows an inline validation error, and the server rejects the refund if an invalid request still gets through.

= Is it compatible with HPOS? =

Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage.

= Can I customize the deduction labels in emails? =

Yes. Go to WooCommerce → Settings → Advanced → Return Shipping to customize both labels and email notes.

== Screenshots ==

1. Refund modal with return shipping fee input
2. Settings page configuration
3. Refund order showing fee line item
4. Customer refund email with fee

== Changelog ==

= 2.7.1 =
* Add PHPUnit and Playwright regression coverage for refund deduction logic.
* Block invalid combined deductions in the admin refund panel with inline errors.
* Return WooCommerce-friendly refund errors when deductions exceed the refund amount.
* Validate compatibility metadata against WordPress 6.9.1 and WooCommerce 10.5.1.

= 2.7.0 =
* Add Retail Box Damage as a second refund deduction.
* Add a second hidden $0 order line for Retail Box Damage.
* Show Return Shipping and Retail Box Damage separately in refund emails.

= 2.6.4 =
* Fix admin refund buttons showing stale $0.00 labels during full refunds.
* Keep button labels synced with WooCommerce refund amount recalculations.

= 2.6.3 =
* Update admin refund button labels to show net refund amount.
* Update WordPress and WooCommerce tested versions.

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
