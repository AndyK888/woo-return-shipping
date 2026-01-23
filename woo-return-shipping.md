# WooCommerce Return Shipping Deduction Plugin

> **Project Type:** WordPress/WooCommerce Plugin  
> **Codename:** "The Anti-Gravity Fee"

## Overview

Allow store admins to deduct a return shipping fee from refunds. The fee appears **only on the refund receipt**, never on the original order. The net refund amount is correctly passed to payment gateways.

### Success Criteria

| Criteria | Measurement |
|----------|-------------|
| Fee invisible on original order | Order detail shows no extra line items |
| Fee visible on refund | Refund object contains `fee_line` with positive value |
| Correct gateway amount | Gateway receives `(refund_total - fee)` |
| Settings configurable | Admin can set defaults, labels, tax, email text |
| Gateway compatible | Works with Stripe Official, PayPal Official |
| HPOS compatible | Uses WooCommerce CRUD methods, no direct DB queries |

---

## Tech Stack

| Component | Technology | Rationale |
|-----------|------------|-----------|
| Language | PHP 8.0+ | Modern PHP, WC 8.x requirement |
| Framework | WordPress Plugin API | Native integration |
| Data Storage | WooCommerce Order Meta | HPOS-compatible via CRUD |
| Admin UI | Native WC + Vanilla JS | No external dependencies |
| Settings | WooCommerce Settings API | Native integration |

---

## File Structure

```
woo-return-shipping/
├── woo-return-shipping.php          # Main plugin file
├── includes/
│   ├── class-wrs-settings.php       # Settings page handler
│   ├── class-wrs-admin.php          # Admin UI (refund modal)
│   ├── class-wrs-refund-handler.php # Core refund logic
│   ├── class-wrs-email.php          # Email template modifications
│   └── class-wrs-gateway-compat.php # Gateway compatibility layer
├── assets/
│   ├── js/
│   │   └── admin-refund.js          # Refund modal JS
│   └── css/
│       └── admin.css                # Admin styles
├── languages/
│   └── woo-return-shipping.pot      # Translation template
└── readme.txt                       # WordPress.org readme
```

---

## Task Breakdown

### Phase 1: Foundation

#### Task 1.1: Plugin Bootstrap
- **Agent:** `backend-specialist`
- **INPUT:** Plugin requirements, WC version constraints
- **OUTPUT:** `woo-return-shipping.php` with:
  - Plugin header (Name, Version, WC requires, HPOS declaration)
  - Activation/deactivation hooks
  - Autoloader for `includes/` classes
  - WooCommerce dependency check
- **VERIFY:** Plugin activates without errors, appears in WP Plugins list

#### Task 1.2: HPOS Compatibility Declaration
- **Agent:** `backend-specialist`
- **INPUT:** WooCommerce HPOS documentation
- **OUTPUT:** `before_woocommerce_init` hook declaring HPOS compatibility
- **VERIFY:** No HPOS compatibility warnings in WC Status

---

### Phase 2: Settings

#### Task 2.1: Settings Page
- **Agent:** `backend-specialist`
- **INPUT:** User requirements for configurable options
- **OUTPUT:** `class-wrs-settings.php` with WooCommerce Settings API integration
- **SETTINGS:**

| Setting | Type | Default |
|---------|------|---------|
| `wrs_default_fee` | number | 10.00 |
| `wrs_fee_label` | text | "Return Shipping" |
| `wrs_tax_status` | select | none / taxable |
| `wrs_tax_class` | select | (WC tax classes) |
| `wrs_email_note` | textarea | "A return shipping fee has been deducted." |
| `wrs_show_reason_field` | checkbox | false |

- **VERIFY:** Settings save/load correctly, appear under WooCommerce → Settings → Advanced

---

### Phase 3: Admin UI

#### Task 3.1: Refund Modal Enhancement
- **Agent:** `frontend-specialist`
- **INPUT:** WooCommerce refund modal structure
- **OUTPUT:** `admin-refund.js` that:
  - Injects number input after refund items (pre-filled with default)
  - Adds "Exempt from return fee" checkbox
  - Updates refund total display dynamically
  - Passes `return_shipping_fee` in AJAX payload
- **VERIFY:** Input visible in refund modal, total updates on input change

#### Task 3.2: Admin Styles
- **Agent:** `frontend-specialist`
- **INPUT:** WooCommerce admin design patterns
- **OUTPUT:** `admin.css` with minimal, native-looking styles
- **VERIFY:** UI matches WooCommerce admin aesthetic

---

### Phase 4: Backend Refund Logic (Core)

#### Task 4.1: Refund Handler
- **Agent:** `backend-specialist`
- **INPUT:** WooCommerce refund flow, `WC_Order_Refund` API
- **OUTPUT:** `class-wrs-refund-handler.php` with:

```php
// Hooks:
add_action('woocommerce_create_refund', [$this, 'process_return_fee'], 10, 2);
add_filter('woocommerce_refund_amount', [$this, 'adjust_gateway_amount'], 10, 2);
```

- **LOGIC:**
  1. Check `$_POST['return_shipping_fee']` exists and > 0
  2. Check "exempt" checkbox not set
  3. Validate fee doesn't exceed refund total
  4. Create `WC_Order_Item_Fee` with positive amount
  5. Add to refund: `$refund->add_item($fee_item)`
  6. Recalculate: `$refund->calculate_totals()`
  7. Store fee in refund meta for reference

- **VERIFY:** 
  - Fee appears on refund order
  - `$refund->get_total()` reflects deduction
  - Original order unchanged

#### Task 4.2: Gateway Amount Filter
- **Agent:** `backend-specialist`
- **INPUT:** WooCommerce payment gateway refund flow
- **OUTPUT:** Filter to ensure gateway receives net amount
- **VERIFY:** Stripe/PayPal receives correct reduced amount

---

### Phase 5: Gateway Compatibility

#### Task 5.1: Stripe Compatibility
- **Agent:** `backend-specialist`
- **INPUT:** Stripe Official Plugin refund flow
- **OUTPUT:** Compatibility checks/filters if needed
- **VERIFY:** Refund processes correctly, Stripe dashboard shows net amount

#### Task 5.2: PayPal Compatibility
- **Agent:** `backend-specialist`
- **INPUT:** PayPal Official Plugin refund flow
- **OUTPUT:** Compatibility checks/filters if needed
- **VERIFY:** Refund processes correctly, PayPal dashboard shows net amount

---

### Phase 6: Email Integration

#### Task 6.1: Refund Email Modification
- **Agent:** `backend-specialist`
- **INPUT:** WooCommerce email templates, refund email hooks
- **OUTPUT:** `class-wrs-email.php` that:
  - Ensures fee line item appears in refund email
  - Adds configurable explanation note
- **VERIFY:** Customer receives email with fee line item and note

---

### Phase 7: Polish

#### Task 7.1: Internationalization
- **Agent:** `backend-specialist`
- **INPUT:** All user-facing strings
- **OUTPUT:** `woo-return-shipping.pot`, all strings wrapped in `__()` / `esc_html__()`
- **VERIFY:** Strings extractable via WP-CLI

#### Task 7.2: Readme & Documentation
- **Agent:** `documentation-writer`
- **INPUT:** Feature list, installation steps
- **OUTPUT:** `readme.txt` (WordPress.org format), inline PHPDoc
- **VERIFY:** Readme validates at wordpress.org/plugins/developers/readme-validator/

---

## Phase X: Verification

### Automated Checks

```bash
# PHP Syntax
find . -name "*.php" -exec php -l {} \;

# WordPress Coding Standards
composer require --dev wp-coding-standards/wpcs
./vendor/bin/phpcs --standard=WordPress woo-return-shipping.php includes/

# Security Scan
python .agent/skills/vulnerability-scanner/scripts/security_scan.py .
```

### Manual Testing Checklist

- [ ] Create order with product ($179.95)
- [ ] Process full refund with $10 return fee
- [ ] Verify original order has NO fee line
- [ ] Verify refund shows fee line (+$10.00)
- [ ] Verify refund total is -$169.95
- [ ] Test with Stripe: gateway receives $169.95 refund
- [ ] Test with PayPal: gateway receives $169.95 refund
- [ ] Test "Exempt" checkbox: full refund, no fee
- [ ] Test fee > refund amount: error handling
- [ ] Test HPOS enabled store
- [ ] Test Block Checkout store
- [ ] Check refund email contains fee + note

### Completion Marker

```
## ✅ PHASE X COMPLETE
- PHPCS: ✅ Pass
- Security: ✅ No critical issues
- Manual Tests: ✅ All pass
- Date: [TBD]
```

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Gateway doesn't support partial | Validate refund amount before processing |
| HPOS incompatibility | Use only `$order->get_*()` / `$order->set_*()` methods |
| Fee exceeds refund | Validate with error message before processing |
| JS conflicts | Use namespaced IIFE, defer script loading |

---

## Notes

- **No external dependencies** - Pure WordPress/WooCommerce APIs
- **HPOS-first** - All order access via CRUD, no direct `wp_posts` queries
- **Defensive coding** - Check WC active, version, gateway support before operations
