## 2024-05-31 - Memoizing regular expression compilation and global object lookups
**Learning:** In high-frequency event handlers (`input`, `keyup`, `change`), compiling complex regular expressions repeatedly or parsing global objects (`window.woocommerce_admin_meta_boxes`) can cause unnecessary CPU usage and garbage collection pressure, especially when values do not change during the lifecycle of the admin page.
**Action:** Safely memoize dynamically constructed regular expressions and global object lookups to avoid recompilation and repeated parsing during user interaction.
