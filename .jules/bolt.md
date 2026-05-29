
## 2024-05-24 - Memoizing complex RegExp in WooCommerce Admin Refunds
**Learning:** `admin-refund.js` constructs multiple regular expressions to parse WooCommerce currency strings inside an `update()` loop triggered on high-frequency events (`input`, `keyup`). The global object lookup (`window.woocommerce_admin_meta_boxes`) was also performed redundantly.
**Action:** Always safely memoize dynamically constructed regular expressions and global object lookups in high-frequency event handlers to prevent CPU usage spikes and garbage collection pressure in jQuery admin scripts.
