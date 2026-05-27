## 2024-05-27 - [Event Handler Memoization]
**Learning:** When handling high-frequency UI events like `input` or `change` in jQuery/WooCommerce admin interfaces, dynamically accessing deep global variables (like `window.woocommerce_admin_meta_boxes`) and continually recompiling the exact same regular expressions causes unnecessary CPU burn.
**Action:** Cache stable objects (`_currencyCache`) and RegExp instantiations (`_regexCache`) on the parent component object. This prevents redundant object allocation and regex compilation during fast typing or sequential updates.
