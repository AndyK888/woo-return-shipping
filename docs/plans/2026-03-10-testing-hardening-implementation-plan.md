# Testing and Hardening Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a real automated test harness, strengthen refund validation and admin error handling, and align compatibility metadata to the latest verified stable WordPress and WooCommerce targets.

**Architecture:** Keep the plugin’s WooCommerce integration points intact, but extract deduction validation, fee-item construction, and email deduction collection into small PHP helpers that can be tested directly with PHPUnit. Add inline browser-side validation for the refund panel and a Playwright smoke test that exercises the admin deduction UI against a real WooCommerce environment when one is available.

**Tech Stack:** PHP 8.x, Composer, PHPUnit, WordPress/WooCommerce plugin code, jQuery admin script, Playwright browser tests.

---

### Task 1: Add PHP Test Tooling

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/Unit/.gitkeep`

**Step 1: Write the failing verification**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
test -f composer.json || echo "missing composer.json"
test -f phpunit.xml.dist || echo "missing phpunit.xml.dist"
```

Expected: both files are missing.

**Step 2: Add minimal Composer and PHPUnit config**

- `composer.json`:
```json
{
  "name": "andriikaprii/woo-return-shipping",
  "type": "wordpress-plugin",
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  },
  "autoload-dev": {
    "classmap": [
      "tests/bootstrap.php",
      "tests/Unit"
    ]
  },
  "scripts": {
    "test": "phpunit --configuration phpunit.xml.dist"
  }
}
```
- `phpunit.xml.dist`:
```xml
<phpunit bootstrap="tests/bootstrap.php" colors="true">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests/Unit</directory>
    </testsuite>
  </testsuites>
</phpunit>
```
- `tests/bootstrap.php` should provide the minimal WordPress/WooCommerce stubs needed by extracted helpers and load the plugin helper files under test.

**Step 3: Verify config exists**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
test -f composer.json && test -f phpunit.xml.dist && echo "test tooling files exist"
```

Expected: `test tooling files exist`.

**Step 4: Install dependencies**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
composer install
```

Expected: Composer installs `vendor/` successfully.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/composer.json \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/composer.lock \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/phpunit.xml.dist \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/tests
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "test: add phpunit harness"
```

---

### Task 2: Extract Deduction Validation and Fee-Building Helpers

**Files:**
- Create: `woo-return-shipping/includes/class-wrs-deduction-validator.php`
- Create: `woo-return-shipping/includes/class-wrs-fee-factory.php`
- Modify: `woo-return-shipping/woo-return-shipping.php`
- Modify: `woo-return-shipping/includes/class-wrs-refund-handler.php`
- Modify: `woo-return-shipping/includes/class-wrs-checkout-fee.php`
- Modify: `woo-return-shipping/includes/class-wrs-email.php`

**Step 1: Write the failing test**

Create `tests/Unit/WRS_Deduction_Validator_Test.php` with cases like:
```php
public function test_when_combined_deductions_exceed_refund_returns_error(): void {
    $result = WRS_Deduction_Validator::validate( 20.0, 15.0, 10.0 );
    $this->assertSame( 'combined_deductions_exceed_refund', $result['error_code'] );
}
```

**Step 2: Verify RED**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
vendor/bin/phpunit --filter WRS_Deduction_Validator_Test
```

Expected: fail because helper class does not exist yet.

**Step 3: Implement minimal helpers**

- `class-wrs-deduction-validator.php`:
```php
final class WRS_Deduction_Validator {
	public static function validate( float $gross_refund, float $return_shipping_fee, float $box_damage_fee ): array {
		if ( $return_shipping_fee < 0 || $box_damage_fee < 0 ) {
			return array( 'is_valid' => false, 'error_code' => 'negative_deduction' );
		}

		$total_deductions = $return_shipping_fee + $box_damage_fee;
		if ( $total_deductions > $gross_refund ) {
			return array( 'is_valid' => false, 'error_code' => 'combined_deductions_exceed_refund' );
		}

		return array(
			'is_valid'         => true,
			'total_deductions' => $total_deductions,
			'net_refund'       => $gross_refund - $total_deductions,
		);
	}
}
```
- `class-wrs-fee-factory.php` should create hidden fee items and refund fee items with the correct metadata.
- Update plugin bootstrap to load both files.
- Update existing classes to call the helpers instead of building fee items inline.

**Step 4: Verify GREEN**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
vendor/bin/phpunit --filter WRS_Deduction_Validator_Test
```

Expected: pass.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-deduction-validator.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-fee-factory.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/woo-return-shipping.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-refund-handler.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-checkout-fee.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-email.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/tests/Unit/WRS_Deduction_Validator_Test.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "refactor: extract deduction validator and fee factory"
```

---

### Task 3: Add PHPUnit Coverage for Core PHP Logic

**Files:**
- Create: `tests/Unit/WRS_Fee_Factory_Test.php`
- Create: `tests/Unit/WRS_Checkout_Fee_Test.php`
- Create: `tests/Unit/WRS_Email_Deductions_Test.php`

**Step 1: Write failing tests**

- `WRS_Fee_Factory_Test.php`:
```php
public function test_create_refund_fee_item_sets_fee_type_metadata(): void {
    $fee_item = WRS_Fee_Factory::create_refund_fee_item( 'Retail Box Damage', 5.0, 'retail_box_damage' );
    $this->assertSame( 'retail_box_damage', $fee_item->get_meta( '_wrs_fee_type' ) );
}
```
- `WRS_Checkout_Fee_Test.php`:
```php
public function test_is_our_fee_recognizes_both_fee_types(): void {
    $item = WRS_Fee_Factory::create_hidden_fee_item( 'Retail Box Damage', 'retail_box_damage' );
    $this->assertTrue( WRS_Checkout_Fee::is_our_fee( $item ) );
}
```
- `WRS_Email_Deductions_Test.php` should cover collection of return shipping and box damage deductions separately.

**Step 2: Verify RED**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
vendor/bin/phpunit
```

Expected: fail because helper coverage is incomplete or methods are not yet exposed in a testable way.

**Step 3: Implement minimal code to support tests**

- Expose email deduction collection through a testable helper or extracted class.
- Keep integration classes thin and delegate to helpers under test.
- Do not broaden scope beyond current plugin behavior.

**Step 4: Verify GREEN**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
vendor/bin/phpunit
```

Expected: all PHPUnit tests pass.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/tests/Unit
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "test: cover core deduction logic"
```

---

### Task 4: Add Client-Side and Server-Side Validation Errors

**Files:**
- Modify: `woo-return-shipping/assets/js/admin-refund.js`
- Modify: `woo-return-shipping/includes/class-wrs-refund-handler.php`

**Step 1: Write the failing verification**

- Current behavior on invalid combined deductions is to silently return from PHP.
- Expected before code changes: no visible inline error exists in the refund UI.

**Step 2: Verify current absence**

Run:
```bash
rg -n "error|notice|combined_deductions_exceed_refund|wp_send_json_error|Exception" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/assets/js/admin-refund.js /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-refund-handler.php
```

Expected: no refund validation error path for combined deductions.

**Step 3: Implement minimal validation**

- In `admin-refund.js` add:
```javascript
showValidationError: function (message) { ... }
clearValidationError: function () { ... }
validateDeductions: function (gross, totalDeductions) {
    if (totalDeductions > gross) {
        this.showValidationError('Combined deductions cannot exceed the refund amount.');
        return false;
    }
    this.clearValidationError();
    return true;
}
```
- Block AJAX when validation fails:
```javascript
if (!self.validateDeductions(gross, totalDeductions)) {
    xhr.abort();
    return;
}
```
- In `class-wrs-refund-handler.php`, on invalid combined deductions:
```php
throw new Exception( __( 'Combined deductions cannot exceed the refund amount.', 'woo-return-shipping' ) );
```

**Step 4: Verify**

Run:
```bash
node --check /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/assets/js/admin-refund.js
php -l /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-refund-handler.php
vendor/bin/phpunit
```

Expected: syntax checks pass and PHPUnit remains green.

**Step 5: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/assets/js/admin-refund.js \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/includes/class-wrs-refund-handler.php
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "fix(refunds): block invalid combined deductions"
```

---

### Task 5: Add Browser Smoke Test Scaffolding

**Files:**
- Create: `package.json`
- Create: `playwright.config.ts`
- Create: `tests/e2e/refund-deductions.spec.ts`

**Step 1: Write the failing verification**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
test -f package.json || echo "missing package.json"
```

Expected: browser test tooling is missing.

**Step 2: Add minimal Playwright setup**

- `package.json`:
```json
{
  "private": true,
  "devDependencies": {
    "@playwright/test": "^1.52.0"
  },
  "scripts": {
    "test:e2e": "playwright test"
  }
}
```
- `playwright.config.ts` should require environment variables such as:
```ts
process.env.WRS_BASE_URL
process.env.WRS_ADMIN_USER
process.env.WRS_ADMIN_PASSWORD
```
- `tests/e2e/refund-deductions.spec.ts` should cover:
- both deduction controls render
- combined deductions change the net amount and refund button labels
- over-limit deductions show the inline error and block refund

**Step 3: Install and syntax-check**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
npm install
npx playwright test --list
```

Expected: Playwright test discovery works. Actual execution requires a live WooCommerce environment.

**Step 4: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/package.json \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/package-lock.json \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/playwright.config.ts \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/tests/e2e/refund-deductions.spec.ts
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "test(e2e): add refund deduction smoke coverage"
```

---

### Task 6: Align Compatibility Metadata to Verified Stable Versions

**Files:**
- Modify: `woo-return-shipping/woo-return-shipping.php`
- Modify: `woo-return-shipping/readme.txt`
- Modify: `README.md`

**Step 1: Write the failing verification**

Run:
```bash
rg -n "WC tested up to|Tested up to|Version:" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/woo-return-shipping.php /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/readme.txt /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/README.md
```

Expected: WooCommerce compatibility currently shows `10.5.2`, which is not the validated latest stable target.

**Step 2: Update metadata**

- Keep WordPress tested version at `6.9.1`.
- Update WooCommerce tested version to `10.5.1`.
- Keep the version consistent with the current feature release line unless a new release number is explicitly chosen.

**Step 3: Verify**

Run:
```bash
rg -n "WC tested up to|Tested up to|Stable tag|Version:" /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/woo-return-shipping.php /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/readme.txt /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/README.md
```

Expected: versions and compatibility markers match the chosen release.

**Step 4: Commit**

```bash
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage add \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/woo-return-shipping.php \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/woo-return-shipping/readme.txt \
  /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage/README.md
git -C /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage commit -m "chore: align compatibility metadata"
```

---

### Task 7: Final Verification and Packaging

**Files:**
- None

**Step 1: Run automated verification**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
find woo-return-shipping -name '*.php' -print0 | xargs -0 -n1 php -l
node --check woo-return-shipping/assets/js/admin-refund.js
vendor/bin/phpunit
npx playwright test --list
```

Expected:
- all PHP syntax checks pass
- JS parse check passes
- PHPUnit passes
- Playwright test discovery succeeds

**Step 2: Run browser smoke test if a real WooCommerce environment is available**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
WRS_BASE_URL=... WRS_ADMIN_USER=... WRS_ADMIN_PASSWORD=... npx playwright test tests/e2e/refund-deductions.spec.ts
```

Expected: the smoke test passes against a real site. If credentials/site are unavailable, report that execution is blocked while keeping the scaffold committed.

**Step 3: Build release package**

Run:
```bash
cd /Users/andriikaprii/Documents/MyApps/woo-return-shipping/.worktrees/codex/retail-box-damage
zip -r /Users/andriikaprii/Documents/MyApps/woo-return-shipping/woo-return-shipping-<version>.zip woo-return-shipping -x '**/.DS_Store'
```

Expected: release zip exists at the repo root.

**Step 4: Report evidence and gaps**

- Summarize actual command results.
- Explicitly call out whether the browser smoke test was only scaffolded or actually executed against a live WooCommerce site.
