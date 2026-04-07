# WooCommerce Return Shipping Deduction

![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-violet)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/License-GPL--2.0-green)
![Version](https://img.shields.io/badge/Version-2.7.1-orange)

Deduct return shipping and retail box damage fees from WooCommerce refunds. Specifically designed for policy-based deductions that must reduce the refund amount without appearing on the original order.

## 🌟 Key Features

*   **Native Fee Approach** - Adds a hidden $0 fee to orders that becomes visible during refunds.
*   **Simple Admin UI** - Standard WooCommerce refund interface usage.
*   **Configurable** - Set default amounts, custom labels, and email notes.
*   **Separate Deduction Types** - Return shipping and retail box damage stay independent in admin and emails.
*   **Validation Guardrails** - Invalid combined deductions are blocked in the admin panel and on the server.
*   **Gateway Compatible** - Net refund amount is calculated natively and sent to Stripe, PayPal, etc.
*   **Hidden from Customers** - Fee does not appear during checkout, on invoices, or in My Account.
*   **Visible on Refunds** - Fee appears clearly on refund receipts and emails.
*   **HPOS Compatible** - Full support for High-Performance Order Storage.

## 🚀 How It Works

1.  **Order Placement**: Hidden `$0` lines for `Return Shipping` and `Retail Box Damage` are added to every new order.
2.  **Refund Process**: When processing a refund in Admin:
    *   Enter the return shipping fee amount, retail box damage fee, or both.
    *   The plugin subtracts the combined deductions from the gateway refund amount.
    *   Each deduction is recorded against its own hidden fee row in the refund record.
3.  **Customer Experience**: The customer receives the net refund. The refund email shows each deduction separately.

## 🛠️ Installation

1.  Download the latest `woo-return-shipping.zip` from Releases.
2.  Go to **WordPress Admin → Plugins → Add New → Upload Plugin**.
3.  Upload and activate the plugin.
4.  Go to **WooCommerce → Settings → Advanced → Return Shipping** to configure labels and defaults.

## ⚙️ Configuration

*   **Return Shipping Default Fee**: The amount pre-filled in the refund field for return shipping.
*   **Retail Box Damage Default Fee**: The amount pre-filled in the refund field for retail box damage.
*   **Labels and Email Notes**: Separate labels and notes for each deduction type.
*   **Tax Status**: Whether the fee is taxable.

## 📦 Compatibility

*   **WooCommerce**: 8.0+
*   **PHP**: 8.0+
*   **Validated Against**: WordPress 6.9.1 and WooCommerce 10.5.1.
*   **Checkouts**: Classic and Block Checkout compatible.
*   **Gateways**: Stripe, PayPal, and standard gateways.

## 🧪 Tests

*   **PHP**: `composer test`
*   **Browser**: `npm run test:e2e`
*   **Scope**: deduction validation, refund mutation, email deduction collection, and admin refund UI regression coverage

## 📝 Changelog

### 2.7.1
*   **Test**: Added PHPUnit and Playwright regression coverage for refund deduction flows.
*   **Fix**: Block invalid combined deductions in the admin refund panel with inline errors.
*   **Fix**: Surface WooCommerce-friendly refund errors when invalid deductions reach the server.
*   **Compatibility**: Validated metadata against WordPress 6.9.1 and WooCommerce 10.5.1.

### 2.7.0
*   **Feature**: Added Retail Box Damage as a second refund deduction.
*   **Feature**: Added a second hidden $0 order line for Retail Box Damage.
*   **Feature**: Refund emails now show each deduction separately.

### 2.6.2
*   **Maintenance**: Cleaned up repository (removed excluded files).

### 2.6.1
*   **Update**: Updated plugin author information.

### 2.5.0
*   **Fix**: Updated email handler to support partial refund emails.
*   **Debug**: Added explicit error logging (subsequently removed).

### 2.4.0
*   **Fix**: Email handler improved to detect fee line items by label matching.
*   **Fix**: Resolved PHP type error with `WC_Order_Refund`.

### 2.2.0
*   **Feature**: Switched to Native Fee approach (hidden $0 fee at checkout).
*   **Deprecated**: Removed JS-based UI injection in favor of native WooCommerce fields.
