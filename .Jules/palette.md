## 2025-05-28 - [Accessibility] Dynamic eCommerce Refund Totals
**Learning:** Dynamically updated eCommerce refund totals should use `aria-live="polite"` so screen readers announce recalculations naturally. Inline validation error messages should use `role="alert"` for immediate feedback.
**Action:** Always wrap recalculating financial totals in `aria-live="polite"` regions and apply `role="alert"` to dynamically shown validation containers.
