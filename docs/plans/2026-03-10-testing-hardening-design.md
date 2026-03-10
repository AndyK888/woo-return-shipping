# Testing and Hardening Design

## Overview

Add a real automated test harness to the plugin, tighten refund validation so invalid combined deductions are blocked with a visible admin error, and align compatibility metadata with the latest stable WordPress and WooCommerce releases that we can validate against.

## Goals

- Add executable automated tests for the PHP refund logic and the admin refund UI.
- Block invalid refunds when combined deductions exceed the refund amount.
- Show a clear admin-facing error before and during refund processing.
- Refactor the current plugin code just enough to make the refund path testable without changing the core WooCommerce integration model.
- Align plugin metadata to WordPress `6.9.1` and WooCommerce `10.5.1`.

## Non-Goals

- Rewriting the plugin into a generic framework.
- Building a full WordPress integration suite for every plugin feature.
- Changing the existing hidden `$0` order-line model.

## Test Strategy

- Add a `composer.json`-based dev toolchain with `phpunit/phpunit`.
- Create PHP tests for:
- deduction parsing and validation
- combined deduction math
- refund fee item creation
- hidden fee detection for both fee types
- deduction extraction and rendering helpers for emails
- Add a browser smoke layer for the WooCommerce refund UI, focused on:
- both deduction controls rendering
- total deduction and net refund preview
- refund action button labels reflecting the net amount
- invalid combined deductions showing an inline error and blocking refund submission

## Error Handling

- Validate deductions in JavaScript before the refund AJAX request is sent.
- Validate the same rules again in PHP before mutating the refund object.
- Validation rules:
- each deduction must be numeric
- each deduction must be non-negative
- combined deductions must not exceed the gross refund amount
- If validation fails in the browser:
- show an inline error message inside the refund deduction box
- block the refund AJAX request
- If validation fails in PHP:
- stop refund creation with a WooCommerce-compatible error response so the admin UI can surface it

## Architecture Adjustments

- Extract the deduction math and validation into a small PHP helper class.
- Extract refund fee-item creation into a small reusable PHP helper.
- Extract deduction collection/render preparation for refund emails into a helper that is directly unit-testable.
- Keep existing WooCommerce entry points:
- `woocommerce_create_refund`
- checkout order processed hook for hidden `$0` fee lines
- WooCommerce refund email hooks

## Compatibility

- Update plugin metadata to:
- WordPress `6.9.1`
- WooCommerce `10.5.1`
- Keep HPOS compatibility declaration unchanged.
- Validate against current WooCommerce refund-screen behavior rather than assuming older admin DOM structures are sufficient.

## Verification

- Automated:
- `phpunit`
- browser smoke test
- syntax checks
- JS parse check
- Manual:
- one refund flow with valid combined deductions
- one refund flow with invalid combined deductions
- one refund email spot check

## Success Criteria

- Tests run locally in the repository.
- Both deductions can apply together and remain separate in UI, refund records, and email output.
- Invalid combined deductions are visibly blocked.
- Net refund amount is consistent across summary, button labels, refund object mutation, and gateway amount.
- Metadata reflects the actual current stable platform targets.
