# Retail Box Damage Deduction Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `Retail Box Damage` as a second dedicated refund deduction that uses the same hidden `$0` order-line model as `Return Shipping`, while allowing both deductions to apply together.

**Architecture:** Extend the plugin's existing single-fee path into a two-fee path without generalizing the whole system. The order-placement logic will add a second hidden fee line, the refund UI will collect two independent deductions, the refund handler will sum them before gateway processing, and the email layer will render each deduction separately.

**Tech Stack:** PHP 8.x, WordPress plugin API, WooCommerce CRUD/refund hooks, WooCommerce settings API, jQuery admin script.

---

### Task 1: Add Retail Box Damage Settings and Defaults

**Files:**
- Modify: `woo-return-shipping/woo-return-shipping.php`
- Modify: `woo-return-shipping/includes/class-wrs-settings.php`
- Modify: `woo-return-shipping/includes/class-wrs-admin.php`

**Step 1: Write the failing verification checklist**

- In WooCommerce settings, there is currently no field for retail box damage amount, label, or email note.
- Expected before code changes: no box-damage settings exist and no value is localized into `wrsConfig`.

**Step 2: Verify current absence**

Run:
```bash
rg -n "box damage|box_damage|Retail Box Damage" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping
```

Expected: no matches or only matches in the new plan docs.

**Step 3: Write minimal implementation**

- In `woo-return-shipping/woo-return-shipping.php`, add activation defaults:
```php
if ( false === get_option( 'wrs_box_damage_default_fee' ) ) {
	add_option( 'wrs_box_damage_default_fee', '0.00' );
}
if ( false === get_option( 'wrs_box_damage_label' ) ) {
	add_option( 'wrs_box_damage_label', __( 'Retail Box Damage', 'woo-return-shipping' ) );
}
if ( false === get_option( 'wrs_box_damage_email_note' ) ) {
	add_option( 'wrs_box_damage_email_note', __( 'A retail box damage fee has been deducted from your refund.', 'woo-return-shipping' ) );
}
```
- In `woo-return-shipping/includes/class-wrs-settings.php`, add three new settings fields:
```php
array(
	'title'   => __( 'Retail Box Damage Default Fee', 'woo-return-shipping' ),
	'id'      => 'wrs_box_damage_default_fee',
	'type'    => 'number',
	'default' => '0.00',
),
array(
	'title'   => __( 'Retail Box Damage Label', 'woo-return-shipping' ),
	'id'      => 'wrs_box_damage_label',
	'type'    => 'text',
	'default' => __( 'Retail Box Damage', 'woo-return-shipping' ),
),
array(
	'title'   => __( 'Retail Box Damage Email Note', 'woo-return-shipping' ),
	'id'      => 'wrs_box_damage_email_note',
	'type'    => 'textarea',
	'default' => __( 'A retail box damage fee has been deducted from your refund.', 'woo-return-shipping' ),
),
```
- In `woo-return-shipping/includes/class-wrs-admin.php`, extend `wrsConfig`:
```php
'boxDamageDefaultFee' => floatval( get_option( 'wrs_box_damage_default_fee', '0.00' ) ),
'boxDamageLabel'      => get_option( 'wrs_box_damage_label', __( 'Retail Box Damage', 'woo-return-shipping' ) ),
```

**Step 4: Run verification**

Run:
```bash
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/woo-return-shipping.php
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-settings.php
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-admin.php
```

Expected: `No syntax errors detected` for all three files.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/woo-return-shipping.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-settings.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-admin.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "feat(settings): add retail box damage options"
```

---

### Task 2: Add the Second Hidden `$0` Order Fee Line

**Files:**
- Modify: `woo-return-shipping/includes/class-wrs-checkout-fee.php`

**Step 1: Write the failing verification checklist**

- New orders currently receive only one hidden `$0` fee line for `Return Shipping`.
- Expected before code changes: there is no second hidden `Retail Box Damage` fee item added at checkout.

**Step 2: Verify current implementation shape**

Run:
```bash
rg -n "WC_Order_Item_Fee|_wrs_fee|add_fee_to_order" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-checkout-fee.php
```

Expected: only one fee-item creation path.

**Step 3: Write minimal implementation**

- Add a second fee item in `add_fee_to_order()`:
```php
$return_fee_item = new WC_Order_Item_Fee();
$return_fee_item->set_name( $return_label );
$return_fee_item->set_amount( 0 );
$return_fee_item->set_total( 0 );
$return_fee_item->set_tax_status( 'none' );
$return_fee_item->add_meta_data( '_wrs_fee_type', 'return_shipping', true );

$box_damage_item = new WC_Order_Item_Fee();
$box_damage_item->set_name( $box_damage_label );
$box_damage_item->set_amount( 0 );
$box_damage_item->set_total( 0 );
$box_damage_item->set_tax_status( 'none' );
$box_damage_item->add_meta_data( '_wrs_fee_type', 'retail_box_damage', true );
```
- Update hide/filter helpers to treat both fee types as plugin-managed hidden fees:
```php
return in_array( $item->get_meta( '_wrs_fee_type' ), array( 'return_shipping', 'retail_box_damage' ), true );
```

**Step 4: Run verification**

Run:
```bash
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-checkout-fee.php
```

Expected: `No syntax errors detected`.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-checkout-fee.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "feat(checkout): add hidden retail box damage fee line"
```

---

### Task 3: Extend the Refund Admin UI to Collect Two Deductions

**Files:**
- Modify: `woo-return-shipping/assets/js/admin-refund.js`
- Modify: `woo-return-shipping/includes/class-wrs-admin.php`

**Step 1: Write the failing manual test**

- Open an order refund screen.
- Expected before code changes: only one deduction control (`Return Shipping`) exists.

**Step 2: Verify current UI shape**

Run:
```bash
rg -n "wrs_apply_fee|wrs_return_shipping_fee|Net to Customer" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/assets/js/admin-refund.js
```

Expected: only one deduction input/checkbox pair.

**Step 3: Write minimal implementation**

- Extend `wrsConfig` usage in `admin-refund.js`:
```javascript
config: window.wrsConfig || {
  defaultFee: 10.00,
  feeLabel: 'Return Shipping',
  boxDamageDefaultFee: 0.00,
  boxDamageLabel: 'Retail Box Damage'
},
```
- Inject a second control block:
```javascript
'<input type="checkbox" id="wrs_apply_box_damage_fee" name="wrs_apply_box_damage_fee" value="1">' +
'<input type="number" id="wrs_box_damage_fee" name="wrs_box_damage_fee" value="' + boxDamageFee.toFixed(2) + '">'
```
- Update the summary math:
```javascript
var returnShippingFee = applyReturnShipping ? (parseFloat($('#wrs_return_shipping_fee').val()) || 0) : 0;
var boxDamageFee = applyBoxDamage ? (parseFloat($('#wrs_box_damage_fee').val()) || 0) : 0;
var totalDeductions = returnShippingFee + boxDamageFee;
var net = Math.max(0, gross - totalDeductions);
```
- Add a `Total Deductions` summary row and use `totalDeductions` when updating button labels.
- Append both values to the refund AJAX payload:
```javascript
settings.data += '&wrs_apply_box_damage_fee=' + applyBoxDamage;
settings.data += '&wrs_box_damage_fee=' + encodeURIComponent(boxDamageFee);
```

**Step 4: Run verification**

Run:
```bash
node --check /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/assets/js/admin-refund.js
```

Expected: exit code `0`.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/assets/js/admin-refund.js \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-admin.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "feat(admin): add retail box damage refund control"
```

---

### Task 4: Extend Refund Processing and Order Notes

**Files:**
- Modify: `woo-return-shipping/includes/class-wrs-refund-handler.php`

**Step 1: Write the failing verification checklist**

- Current refund processing only supports one deduction and one refund fee line.
- Expected before code changes: there is no combined deduction calculation and no box-damage fee metadata.

**Step 2: Verify current single-fee flow**

Run:
```bash
rg -n "_wrs_return_fee|wrs_return_shipping_fee|set_amount|add_order_note" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-refund-handler.php
```

Expected: only return-shipping keys and one fee-item creation path.

**Step 3: Write minimal implementation**

- Parse both deduction inputs into normalized values:
```php
$return_shipping_fee = self::get_posted_fee_amount( 'wrs_apply_fee', 'wrs_return_shipping_fee' );
$box_damage_fee      = self::get_posted_fee_amount( 'wrs_apply_box_damage_fee', 'wrs_box_damage_fee' );
$total_fees          = $return_shipping_fee + $box_damage_fee;
```
- Guard on the combined amount:
```php
if ( $total_fees <= 0 || $total_fees > $original_amount ) {
	return;
}
```
- Set refund amount from `original - total_fees`.
- Add separate meta keys:
```php
$refund->add_meta_data( '_wrs_return_fee', $return_shipping_fee, true );
$refund->add_meta_data( '_wrs_box_damage_fee', $box_damage_fee, true );
```
- Add separate refund fee items only when each amount is greater than zero.
- Update the order note to list each applied deduction separately.
- Add a private helper:
```php
private static function get_posted_fee_amount( string $apply_key, string $amount_key ): float
```

**Step 4: Run verification**

Run:
```bash
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-refund-handler.php
```

Expected: `No syntax errors detected`.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-refund-handler.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "feat(refunds): support combined deduction fees"
```

---

### Task 5: Render Separate Email Output for Both Deductions

**Files:**
- Modify: `woo-return-shipping/includes/class-wrs-email.php`

**Step 1: Write the failing verification checklist**

- Current email logic only renders one deduction block and one note.
- Expected before code changes: no separate box-damage deduction email content exists.

**Step 2: Verify current single-deduction behavior**

Run:
```bash
rg -n "_wrs_return_fee|wrs_email_note|Return Shipping|add_refund_note" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-email.php
```

Expected: only one deduction path.

**Step 3: Write minimal implementation**

- Collect both deductions:
```php
$return_shipping_fee = floatval( $refund->get_meta( '_wrs_return_fee' ) );
$box_damage_fee      = floatval( $refund->get_meta( '_wrs_box_damage_fee' ) );
```
- Render one block per non-zero deduction with its own label and note:
```php
self::render_fee_note( $plain_text, get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ), $return_shipping_fee, get_option( 'wrs_email_note', '' ) );
self::render_fee_note( $plain_text, get_option( 'wrs_box_damage_label', __( 'Retail Box Damage', 'woo-return-shipping' ) ), $box_damage_fee, get_option( 'wrs_box_damage_email_note', '' ) );
```
- Extract repeated markup into a helper:
```php
private static function render_fee_note( bool $plain_text, string $label, float $amount, string $note ): void
```

**Step 4: Run verification**

Run:
```bash
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-email.php
```

Expected: `No syntax errors detected`.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/includes/class-wrs-email.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "feat(email): render retail box damage deduction separately"
```

---

### Task 6: Update Version and Documentation

**Files:**
- Modify: `woo-return-shipping/woo-return-shipping.php`
- Modify: `woo-return-shipping/readme.txt`
- Modify: `README.md`

**Step 1: Write the failing verification checklist**

- Current version metadata does not mention the retail box damage feature.

**Step 2: Verify current version markers**

Run:
```bash
rg -n "Version:|WRS_VERSION|Stable tag|Version-" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/woo-return-shipping.php /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/readme.txt /Users/andriikaprii/Documents/MyApps/woo-return-shipping/README.md
```

Expected: current released version values only.

**Step 3: Write minimal implementation**

- Bump plugin version consistently in all three files.
- Add a changelog entry describing:
```text
- Added Retail Box Damage as a second refund deduction.
- Added separate refund email output and hidden $0 order line.
```

**Step 4: Run verification**

Run:
```bash
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/woo-return-shipping.php
```

Expected: `No syntax errors detected`.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/woo-return-shipping.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/readme.txt \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/README.md
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping commit -m "chore: bump version for retail box damage release"
```

---

### Task 7: Final Verification and Release Package

**Files:**
- None

**Step 1: Run syntax verification**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping && find woo-return-shipping -name '*.php' -print0 | xargs -0 -n1 php -l
node --check /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping/assets/js/admin-refund.js
```

Expected: all PHP files report `No syntax errors detected`; `node --check` exits `0`.

**Step 2: Run manual verification**

- Create a refund with only `Return Shipping`.
- Create a refund with only `Retail Box Damage`.
- Create a refund with both deductions.
- Create a refund with neither deduction.
- Test an older order placed before this feature was released.
- Verify refund buttons show the correct net amount in each case.
- Verify refund emails render separate deduction lines.

**Step 3: Build release package**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping
zip -r /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping-<version>.zip woo-return-shipping -x '**/.DS_Store'
```

Expected: a release zip is generated at the repository root.

**Step 4: Report results**

- Summarize syntax-check evidence.
- Call out that refund and email verification are manual because the repository has no automated test harness.
