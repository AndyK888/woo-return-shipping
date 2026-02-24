# WooCommerce Return Shipping Deduction

![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-violet)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/License-GPL--2.0-green)
![Version](https://img.shields.io/badge/Version-2.6.3-orange)

Deduct return shipping fees from WooCommerce refunds. Specifically designed to handle "restocking fees" or "return shipping labels" that need to be deducted from the refund amount but should not appear on the original order.

## 🌟 Key Features

*   **Native Fee Approach** - Adds a hidden $0 fee to orders that becomes visible during refunds.
*   **Simple Admin UI** - Standard WooCommerce refund interface usage.
*   **Configurable** - Set default fees, custom labels, and email notes.
*   **Gateway Compatible** - Net refund amount is calculated natively and sent to Stripe, PayPal, etc.
*   **Hidden from Customers** - Fee does not appear during checkout, on invoices, or in My Account.
*   **Visible on Refunds** - Fee appears clearly on refund receipts and emails.
*   **HPOS Compatible** - Full support for High-Performance Order Storage.

## 🚀 How It Works

1.  **Order Placement**: A hidden "Return Shipping" fee ($0) is added to every new order.
2.  **Refund Process**: When processing a refund in Admin:
    *   Locate the "Return Shipping" fee line item.
    *   Enter the deduction amount (e.g., $10.00).
    *   WooCommerce automatically subtracts this from the refund total.
3.  **Customer Experience**: The customer receives the net refund. The refund email shows the deduction line item.

## 🛠️ Installation

1.  Download the latest `woo-return-shipping.zip` from Releases.
2.  Go to **WordPress Admin → Plugins → Add New → Upload Plugin**.
3.  Upload and activate the plugin.
4.  Go to **WooCommerce → Settings → Advanced → Return Shipping** to configure labels and defaults.

## ⚙️ Configuration

*   **Default Fee**: The amount pre-filled in the refund field (default: 10.00).
*   **Fee Label**: The text shown for the fee (default: "Return Shipping").
*   **Email Note**: Custom text added to refund emails (e.g., "A return shipping fee was deducted").
*   **Tax Status**: Whether the fee is taxable.

## 📦 Compatibility

*   **WooCommerce**: 8.0+
*   **PHP**: 8.0+
*   **Checkouts**: Classic and Block Checkout compatible.
*   **Gateways**: Stripe, PayPal, and standard gateways.

## 📝 Changelog

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
