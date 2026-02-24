# Refund Button Net Amount Design

## Overview
Update the WooCommerce admin refund buttons to display the net refund amount (gross refund minus return shipping fee) without changing WooCommerce's internal refund amount input or processing. This avoids misleading UI while keeping refund logic native and stable.

## Goals
- Show the net refund amount on the admin refund action buttons.
- Preserve WooCommerce's native refund flow (gross refund stays in `#refund_amount`).
- Keep backend deduction logic unchanged and authoritative.
- Update plugin metadata for latest WP/WC compatibility and bump version to 2.6.3.

## Non-Goals
- Modifying WooCommerce refund calculations or the `#refund_amount` field.
- Changing gateway refund behavior (already handled server-side).
- Reworking the admin refund UI layout.

## Current Behavior
- `#refund_amount` reflects the gross refund.
- The plugin UI shows gross and net amounts for admin clarity.
- Buttons are rendered by WooCommerce and display the gross amount.
- Server-side refund handler subtracts the fee before gateway processing.

## Proposed UI Behavior
- When the return shipping fee is applied, refund action button labels display the net amount.
- When fee is unchecked or zero, labels revert to their original text.
- Button labels keep their original suffixes (e.g., "via Stripe", "manually").

## Data Flow
1. Admin changes refund amount or fee inputs.
2. JS computes `net = max(0, gross - fee)`.
3. JS rewrites button labels to show net amount while preserving original label text.
4. Refund creation proceeds as normal; server-side handler enforces the net amount.

## Compatibility
- No changes to WooCommerce API usage.
- Only UI label updates in admin panel.
- Update metadata for WP 6.9.1 and WooCommerce 10.5.2.

## Testing (Manual)
- Fee checked: buttons show net amount; gateway receives net.
- Fee unchecked: buttons show gross amount.
- Fee equals gross: buttons show $0.00.

## Files to Touch
- `woo-return-shipping/assets/js/admin-refund.js`
- `woo-return-shipping/woo-return-shipping.php`
- `woo-return-shipping/readme.txt`
- `README.md`
