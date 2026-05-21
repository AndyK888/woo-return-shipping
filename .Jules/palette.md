## 2024-05-21 - Role Alert for Validation
**Learning:** Dynamically injected validation error containers (like inline errors for WooCommerce refunds) require `role="alert"` and `aria-live="polite"` so screen readers can announce the error as soon as it is injected or made visible.
**Action:** Always append `role="alert"` and `aria-live="polite"` to dynamic validation error nodes within the admin interface so they become immediately accessible without requiring focus.
