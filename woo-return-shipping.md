# WooCommerce Return Shipping Deduction

## Overview

This plugin deducts policy-based charges from WooCommerce refunds without exposing those charges on the original customer order. It currently supports two independent deduction types:

- `Return Shipping`
- `Retail Box Damage`

The customer sees the net refund amount. WooCommerce payment gateways receive the net refund amount. Each deduction is shown separately on the refund and in refund emails.

## Current Behavior

### Order placement

- New orders receive two hidden `$0.00` fee items:
- `Return Shipping`
- `Retail Box Damage`
- These fee items are hidden from storefront checkout, customer order totals, and standard order emails.
- The hidden fee items remain available in admin order/refund flows as anchors for later deductions.

### Refund processing

- The admin refund panel shows separate controls for both deduction types.
- Admins can apply either deduction alone or both together.
- The refund summary shows:
- gross refund
- total deductions
- net to customer
- Refund action buttons display the net amount when deductions are valid.
- If combined deductions exceed the gross refund amount, the refund is blocked:
- client-side with an inline error
- server-side with a WooCommerce-compatible error response

### Refund persistence

- During `woocommerce_create_refund`, the plugin validates posted deduction values.
- The plugin reduces the refund object amount before WooCommerce sends the refund to the gateway.
- Separate refund fee line items are added for each applied deduction.
- Refund metadata stores:
- original refund amount
- return shipping fee
- retail box damage fee
- net refund amount

### Email behavior

- Refund emails render `Return Shipping` and `Retail Box Damage` separately.
- Each deduction uses its own label and configurable note.
- Deduction amounts are resolved from refund meta first, then from refund fee items if needed.

## Architecture

### Main entrypoint

- `woo-return-shipping/woo-return-shipping.php`
- Loads plugin classes
- Declares HPOS compatibility
- Registers activation defaults
- Defines plugin constants

### Admin UI

- `woo-return-shipping/includes/class-wrs-admin.php`
- Enqueues refund admin assets
- Localizes deduction defaults, labels, and validation messages

- `woo-return-shipping/assets/js/admin-refund.js`
- Injects deduction UI into the WooCommerce refund panel
- Recalculates gross/deduction/net state
- Rewrites refund button labels to net amount
- Prevents invalid refund AJAX submissions

### Refund logic

- `woo-return-shipping/includes/class-wrs-refund-handler.php`
- Reads posted deduction values
- Validates values and combined totals
- Mutates refund amount before gateway processing
- Adds refund fee line items
- Writes order notes after refund creation

- `woo-return-shipping/includes/class-wrs-deduction-validator.php`
- Pure validation helper for deduction math

- `woo-return-shipping/includes/class-wrs-fee-factory.php`
- Creates hidden order fee items and refund fee items with plugin metadata

### Checkout fee handling

- `woo-return-shipping/includes/class-wrs-checkout-fee.php`
- Adds hidden `$0.00` fee items to new orders
- Hides plugin-managed fee items in customer-facing contexts

### Email handling

- `woo-return-shipping/includes/class-wrs-email.php`
- Hooks into refund emails only

- `woo-return-shipping/includes/class-wrs-email-deductions.php`
- Collects deduction data for email rendering

### Settings

- `woo-return-shipping/includes/class-wrs-settings.php`
- Adds WooCommerce Advanced settings for:
- default amounts
- labels
- email notes
- tax status/class
- hidden order line creation

## Compatibility

- WordPress: `6.0+`
- PHP: `8.0+`
- WooCommerce: `8.0+`
- Validated metadata target: WordPress `6.9.1`, WooCommerce `10.5.1`
- HPOS: enabled

The refund integration still uses WooCommerce core’s `woocommerce_create_refund` hook, which remains on the active refund creation path in WooCommerce `10.5.1`.

## Automated Verification

### PHP unit coverage

- `tests/Unit/WRS_Deduction_Validator_Test.php`
- `tests/Unit/WRS_Fee_Factory_Test.php`
- `tests/Unit/WRS_Checkout_Fee_Test.php`
- `tests/Unit/WRS_Email_Deductions_Test.php`
- `tests/Unit/WRS_Refund_Handler_Test.php`

Run:

```bash
composer test
```

### Browser regression coverage

- `tests/e2e/admin-refund.spec.js`

Covers:

- net refund button labels for valid deductions
- blocking invalid combined deductions with inline error

Run:

```bash
npm run test:e2e
```

## Release Notes

### 2.7.1

- Added PHPUnit regression coverage for deduction helpers and refund amount mutation.
- Added Playwright regression coverage for refund admin behavior.
- Added explicit invalid-deduction blocking in admin JS and server-side refund processing.
- Updated compatibility metadata to WordPress `6.9.1` and WooCommerce `10.5.1`.
