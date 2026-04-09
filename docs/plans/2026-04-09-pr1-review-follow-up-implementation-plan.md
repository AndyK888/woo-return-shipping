# PR #1 Review Follow-Up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve the valid review feedback on PR #1, document the rejected sign-change suggestion with source-backed reasoning, and align the repository docs with the latest compatibility assessment.

**Architecture:** Keep the existing linked refund-fee behavior intact because it matches current WooCommerce refund internals. Apply only the safe PHP type-safety improvement requested in review, then update versioned documentation to distinguish verified source compatibility from runtime validation status.

**Tech Stack:** PHP 8.x, WooCommerce refund hooks, PHPUnit, WordPress plugin metadata and docs.

---

### Task 1: Re-verify the Review Findings Against WooCommerce Core

**Files:**
- Modify: `docs/plans/2026-04-09-pr1-review-follow-up-implementation-plan.md`

- [x] **Step 1: Check current PR review requests**

Run:
```bash
git -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence log --oneline --decorate -1
```

Expected: branch head is the PR #1 commit under review.

- [x] **Step 2: Verify WooCommerce refund item semantics from official source**

Run:
```bash
composer -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence test
```

Expected: the current regression suite passes before any follow-up change, confirming the linked refund-fee behavior is already covered.

- [x] **Step 3: Record outcome**

- Negative refund fee totals stay in scope because current WooCommerce core stores refund-linked line items as negative values and derives the displayed refunded amount from them.
- The only actionable code-review request is the missing `?WC_Order` return type on `get_refund_order()`.

### Task 2: Apply the Valid Review Fix

**Files:**
- Modify: `woo-return-shipping/includes/class-wrs-refund-handler.php`

- [x] **Step 1: Write the failing verification checklist**

- `get_refund_order()` currently returns `WC_Order|null` but does not declare it in the signature.
- Expected before the edit: the method docblock and implementation imply nullable `WC_Order`, but the PHP signature does not.

- [x] **Step 2: Write minimal implementation**

- Update `get_refund_order()` to declare `: ?WC_Order`.

- [x] **Step 3: Run verification**

Run:
```bash
php -l /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/includes/class-wrs-refund-handler.php
composer -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence test
```

Expected: syntax check passes and PHPUnit stays green.

### Task 3: Align Repository Documentation With the Compatibility Assessment

**Files:**
- Modify: `README.md`
- Modify: `woo-return-shipping/readme.txt`
- Modify: `woo-return-shipping/woo-return-shipping.php`

- [x] **Step 1: Write the failing verification checklist**

- Repository metadata currently declares validation against WordPress `6.9.1` and WooCommerce `10.5.1`.
- Expected before the edit: docs do not mention the newer official stable versions reviewed during this task.

- [x] **Step 2: Write minimal implementation**

- Update the plugin version and repository docs for this follow-up change.
- Update compatibility language to reflect the latest official stable WordPress and WooCommerce versions reviewed in this task, while being explicit about source-level verification versus live-store runtime testing.

- [x] **Step 3: Run verification**

Run:
```bash
rg -n "2\\.7\\.2|6\\.9\\.4|10\\.6\\.2|source-compatible|live-store" /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/README.md /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/readme.txt /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/woo-return-shipping.php
```

Expected: version and compatibility references are synchronized across plugin metadata and docs.

### Task 4: Final Verification and Delivery

**Files:**
- Modify: `docs/plans/2026-04-09-pr1-review-follow-up-implementation-plan.md`

- [x] **Step 1: Run the full verification set**

Run:
```bash
composer -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence test
npm -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence run test:e2e
php -l /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/includes/class-wrs-refund-handler.php
php -l /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/woo-return-shipping.php
git -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence diff --check
```

Expected: all commands succeed with no syntax or whitespace errors.

- [x] **Step 2: Mark plan status complete**

- Update all completed checkboxes in this plan.

- [x] **Step 3: Commit**

```bash
git -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence add \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/docs/plans/2026-04-09-pr1-review-follow-up-implementation-plan.md \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/README.md \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/includes/class-wrs-fee-factory.php \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/readme.txt \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/woo-return-shipping.php \
  /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence/woo-return-shipping/includes/class-wrs-refund-handler.php
git -C /Volumes/Documents_SSDR0/apps/woo-return-shipping/.worktrees/codex-refund-fee-persistence commit -m "chore: address PR #1 review follow-up" -m "Why: PR #1 received follow-up review feedback and the repository compatibility notes lag the latest official WordPress and WooCommerce stable versions." -m "What: Added the missing nullable WC_Order return type, documented the verified refund-item behavior, and aligned plugin metadata and docs with the current compatibility assessment."
```
