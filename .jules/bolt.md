## 2025-05-25 - Regex Memoization in High-Frequency DOM Events
**Learning:** `replaceAmountInLabel` was dynamically recompiling three separate regexes on every keystroke in the refund panel because it handles user input typing. This was creating unnecessary GC pressure and CPU cycles.
**Action:** Always check functions invoked inside loop bodies or event listeners (`input`, `keyup`, `change`) for dynamically constructed regular expressions or object lookups that can be safely memoized using closures or prototype properties.
