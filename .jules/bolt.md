## 2024-05-18 - Memoizing Regex and Global Lookups in High-Frequency Handlers
**Learning:** Found a performance bottleneck where repeated evaluation of global objects (`window.woocommerce_admin_meta_boxes`) and regex compilations inside high-frequency event handlers (`input.wrs` / `keyup` / `change`) creates unnecessary garbage collection pressure and CPU usage, negatively impacting UI responsiveness.
**Action:** Always memoize dynamically constructed regular expressions and static configuration objects inside long-running client-side components to minimize repetitive evaluation costs.
