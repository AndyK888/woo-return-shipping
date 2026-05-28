## 2024-05-28 - Regex Memoization in Event Handlers
**Learning:** Found a performance anti-pattern in `admin-refund.js` where regular expressions and global object lookups were being recreated inside high-frequency event handlers (`replaceAmountInLabel` called during `input` events).
**Action:** Always memoize dynamically constructed regular expressions (`new RegExp(...)`) and expensive object parsing logic when they depend on static data (like `woocommerce_admin_meta_boxes`) and are used within frequent DOM event loops to reduce CPU overhead and GC pressure.
