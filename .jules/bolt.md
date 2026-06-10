## 2024-05-17 - [RegExp and Static Object Compilation in Keyup Handlers]
**Learning:** Recompiling Regular Expressions and parsing static DOM objects inside frequently-triggered UI events (like `keyup`) causes unnecessary memory churn and CPU overhead in jQuery applications.
**Action:** Always extract and memoize static RegExp objects and DOM configuration variables that don't change during the page lifecycle, instead of declaring them directly inside formatting or calculation functions called by event handlers.
