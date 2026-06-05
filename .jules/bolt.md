## 2024-06-05 - Admin Refund JS Optimization
**Learning:** In high-frequency event handlers like `updateButtonLabels` and `replaceAmountInLabel` (which are triggered by `input`, `keyup`, and `change` events on refund inputs), there is unnecessary repeated fetching of currency formats and re-compilation of complex regular expressions on every single keystroke.
**Action:** Memoize the currency format and regular expressions to improve performance during rapid input changes.
