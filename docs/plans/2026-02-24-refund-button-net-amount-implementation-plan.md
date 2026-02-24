# Refund Button Net Amount Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Update WooCommerce admin refund button labels to show the net refund amount while leaving the native refund amount field unchanged.

**Architecture:** UI-only update in `admin-refund.js` computes net amount from gross refund minus fee and rewrites button labels, while backend refund deduction remains authoritative. Metadata updates align declared compatibility with latest WP/WC.

**Tech Stack:** PHP 8.x, WooCommerce admin JS (jQuery), WordPress plugin headers/readme.

---

### Task 1: Add Button Label Update Logic (Admin JS)

**Files:**
- Modify: `woo-return-shipping/assets/js/admin-refund.js`

**Step 1: Write a manual test scenario (expected to fail before changes)**
- Open an order refund screen.
- Apply a return shipping fee (e.g., $10.00).
- Observe that buttons still show gross refund amount.
- Expected (pre-change): buttons show gross amount.

**Step 2: Implement minimal JS helpers**
Add helper functions to:
- Get currency symbol (from `window.woocommerce_admin_meta_boxes.currency_format_symbol` if available, fallback to `$`).
- Format money with two decimals.
- Replace the amount in button labels, preserving original label text.

**Step 3: Update `update()` to rewrite labels**
- Call a new `updateButtonLabels(gross, net, applyFee, fee)` method inside `update()`.
- Store original labels in `data-wrs-original-label` for revert.
- If fee is unchecked or zero, restore original labels.

**Step 4: Manual verification**
- Fee checked: buttons show net amount.
- Fee unchecked: buttons show gross amount.
- Fee equals gross: buttons show $0.00.

**Step 5: Commit**
```bash
git add /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/refund-button-net/woo-return-shipping/assets/js/admin-refund.js
git commit -m "feat(admin): show net refund in action buttons" -m "Why: Admin refund buttons currently show gross amounts, which can mislead when return shipping fee is applied." -m "What: Added label rewrite to display net refund while preserving WooCommerce refund flow and restoring original labels when fee is not applied."
```

---

### Task 2: Update Plugin Metadata and Version

**Files:**
- Modify: `woo-return-shipping/woo-return-shipping.php`
- Modify: `woo-return-shipping/readme.txt`
- Modify: `README.md`

**Step 1: Update plugin header and constants**
- Bump version to `2.6.3` and update `WRS_VERSION`.
- Update `WC tested up to` to `10.5.2`.
- Update `Tested up to` in readme to `6.9.1`.

**Step 2: Update public docs**
- Update `readme.txt` stable tag to `2.6.3`.
- Add `2.6.3` changelog entry describing the button label change and compatibility update.
- Update README badge version to `2.6.3`.

**Step 3: Manual verification**
- Confirm metadata matches requested versions.

**Step 4: Commit**
```bash
git add /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/refund-button-net/woo-return-shipping/woo-return-shipping.php
git add /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/refund-button-net/woo-return-shipping/readme.txt
git add /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/refund-button-net/README.md
git commit -m "chore: bump version and compatibility metadata" -m "Why: Align declared compatibility with latest WordPress/WooCommerce releases and bump version to 2.6.3." -m "What: Updated plugin header, readme metadata, and README badge with new versions and changelog entry."
```

---

### Task 3: Final Manual Verification

**Files:**
- None

**Step 1: Manual validation checklist**
- Open an order refund screen.
- Apply a return shipping fee.
- Verify buttons show net amount.
- Process refund and confirm gateway receives net amount (existing backend logic).
- Verify fee unchecked restores gross labels.

**Step 2: Report results**
- Summarize what was verified and any limitations (no automated tests).

**Step 3: Commit (optional)**
- No commit unless documentation updates are needed.
