## 2026-05-30 - Dynamic Refund Totals A11y
**Learning:** This WooCommerce plugin injects a custom refund fee block into the admin DOM using string concatenation. Because the net refund and total deductions update dynamically as inputs change, screen readers will miss the recalculations unless those regions use `aria-live`.
**Action:** Always wrap dynamically updating financial or summary areas in `aria-live="polite"` when working in legacy JS/jQuery apps that don't rely on modern reactive frameworks, and ensure validation errors use `role="alert"`.
