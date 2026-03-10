# Retail Box Damage Deduction Design

## Overview

Add `Retail Box Damage` as a second built-in deduction that works alongside `Return Shipping`. The new deduction follows the same core pattern as the existing feature: add a hidden `$0` fee line at order placement, expose a dedicated refund control in admin, subtract the amount from the refund before the gateway processes it, and show the deduction as a separate line in the refund email.

## Goals

- Keep `Retail Box Damage` separate from existing order and refund lines.
- Preserve the current hidden `$0` order line pattern as the only supported implementation model.
- Allow `Return Shipping` and `Retail Box Damage` to be applied together on the same refund.
- Show both deductions separately in admin, refund records, order notes, and customer emails.
- Keep refund button labels aligned with the actual customer refund amount after all deductions.

## Non-Goals

- Generalizing the plugin into an arbitrary deduction-type framework.
- Reusing other WooCommerce order lines as deduction sources.
- Changing the current refund gateway integration pattern.

## Admin and Order Behavior

- Add a second hidden `$0` fee line for `Retail Box Damage` when new orders are created.
- Keep the existing hidden `$0` fee line for `Return Shipping`.
- Show two dedicated controls in the refund panel:
- `Return Shipping`
- `Retail Box Damage`
- Each control includes its own checkbox and amount input.
- Both controls can be applied simultaneously.
- The refund summary shows:
- `Gross Refund`
- `Total Deductions`
- `Net to Customer`
- Refund action buttons display the final customer refund amount after one or both deductions.

## Refund Processing

- Read two deduction inputs from the refund request.
- Calculate total deductions as the sum of both enabled charges.
- Validate that combined deductions do not exceed the gross refund amount.
- Set the refund amount sent to the payment gateway to `gross - total deductions`.
- Add separate refund fee items for each applied deduction.
- Store separate metadata for each deduction plus shared original/net refund metadata.
- Add an order note that lists each applied deduction and the resulting net refund.

## Settings and Email

- Add dedicated settings for `Retail Box Damage`:
- default amount
- label
- email note
- Reuse the existing global hidden-order-line toggle so both hidden `$0` fee lines are added together.
- Refund emails render `Retail Box Damage` separately from `Return Shipping`, using its own label and note.
- When both deductions exist, the email shows both as distinct blocks.

## Compatibility

- Existing orders must continue to support refunds even if they were created before the new hidden `Retail Box Damage` line existed.
- Recommended behavior is to always show both refund controls, regardless of whether the original order contains the hidden line.
- The gateway amount remains authoritative from the `woocommerce_create_refund` hook, which already works for the current deduction flow.

## Risks

- Extending a single-fee path into a two-fee path can accidentally break the existing return-shipping flow if the total deduction math is not centralized.
- Email handling currently assumes a single deduction, so it must be made explicitly multi-line.
- Refund button label logic must account for the combined deduction amount, not just one fee.

## Verification

- Return shipping only.
- Retail box damage only.
- Both deductions together.
- Neither deduction applied.
- Full refund and partial refund.
- Older order created before the box-damage hidden line existed.
- Refund buttons showing the correct net refund after one or both deductions.
