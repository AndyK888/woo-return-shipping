## 2023-10-27 - MutationObserver Bottleneck

**Learning:** jQuery's `:visible` pseudo-selector forces a synchronous layout calculation and is extremely slow. Using it inside a `MutationObserver` callback causes severe main thread blocking and layout thrashing, especially when the observer is attached to a rapidly changing DOM element like `#woocommerce-order-items`.

**Action:** Always replace jQuery's `:visible` inside `MutationObserver` callbacks with native DOM APIs. Use `document.getElementById` for fast existence checks, and check visibility using `offsetWidth > 0 || offsetHeight > 0` after ensuring the element exists.
