const fs = require('fs');
const http = require('http');
const path = require('path');
const { test, expect } = require('@playwright/test');

const pluginScriptPath = path.resolve(__dirname, '../../woo-return-shipping/assets/js/admin-refund.js');
const jqueryPath = require.resolve('jquery/dist/jquery.min.js');

function getActionMarkup(mode, grossAmountText, currencySymbol) {
    const grossLabel = currencySymbol ? `${currencySymbol}${grossAmountText}` : grossAmountText;

    if (mode === 'all-hidden') {
        return `
            <div class="refund-actions" style="display:none">
                <button class="button do-manual-refund">Refund ${grossLabel} manually</button>
                <button class="button do-api-refund">Refund ${grossLabel} via Stripe</button>
            </div>
            <div class="refund-actions" style="display:none">
                <a class="button do-api-refund" href="#">Refund ${grossAmountText} via PayPal</a>
            </div>
        `;
    }

    if (mode === 'multiple-visible') {
        return `
            <div class="refund-actions" style="display:block">
                <button class="button do-manual-refund">Refund ${grossLabel} manually</button>
                <button class="button do-api-refund">Refund ${grossLabel} via Stripe</button>
            </div>
            <div class="refund-actions" style="display:block">
                <button class="button do-manual-refund">Second refund ${grossAmountText}</button>
            </div>
        `;
    }

    return `
        <div class="refund-actions" style="display:block">
            <button class="button do-manual-refund">Refund ${grossLabel} manually</button>
            <button class="button do-api-refund">Refund ${grossLabel} via Stripe</button>
        </div>
    `;
}

function createServer() {
    let requestCount = 0;
    let lastRequestBody = '';
    const safeDecode = (value, fallback = '') => {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }

        try {
            return decodeURIComponent(value);
        } catch (error) {
            return fallback || value;
        }
    };

    const server = http.createServer((req, res) => {
        const requestUrl = new URL(req.url, 'http://127.0.0.1');
        const params = requestUrl.searchParams;
        const pathname = requestUrl.pathname;

        const decimals = params.get('currency_format_num_decimals');
        const parsedDecimals = decimals === null || decimals === '' ? 2 : parseInt(decimals, 10);
        const precision = Number.isNaN(parsedDecimals) ? 2 : parsedDecimals;

        const grossAmount = params.get('grossAmount') || '40.00';
        const defaultFee = params.get('defaultFee') || '10.00';
        const boxDamageDefaultFee = params.get('boxDamageDefaultFee') || '0.00';

        const actionLayout = params.get('actionLayout') || 'single';
        const currencySymbol = safeDecode(params.get('currencySymbol'), '$');
        const feeLabel = safeDecode(params.get('feeLabel'), 'Return Shipping');
        const boxDamageLabel = safeDecode(params.get('boxDamageLabel'), 'Retail Box Damage');
        const amountForLabelParam = params.get('amountForLabel');
        const amountForLabel = amountForLabelParam === null || amountForLabelParam === ''
            ? 'Amount for %s'
            : safeDecode(amountForLabelParam, 'Amount for %s');

        const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Refund Fixture</title>
</head>
<body>
  <button class="refund-items">Refund</button>
  <div id="woocommerce-order-items">
    <input id="refund_amount" value="${grossAmount}" />
    ${getActionMarkup(actionLayout, grossAmount, currencySymbol)}
  </div>
  <script>
    window.wrsConfig = {
      defaultFee: ${defaultFee},
      feeLabel: '${feeLabel}',
      boxDamageDefaultFee: ${boxDamageDefaultFee},
      boxDamageLabel: '${boxDamageLabel}',
      messages: {
        combinedDeductionsExceedRefund: 'Combined refund deductions cannot exceed the refund amount.',
        invalidDeductionAmount: '%s amount must be a valid non-negative number.',
        amountForLabel: '${amountForLabel}',
      }
    };
    window.woocommerce_admin_meta_boxes = {
      currency_format_symbol: '${currencySymbol}',
      currency_format_decimal_sep: '${safeDecode(params.get('currencyDecimal'), '.')}',
      currency_format_thousand_sep: '${safeDecode(params.get('currencyThousands'), ',')}',
      currency_format_num_decimals: ${precision},
      currency_format: '${safeDecode(params.get('currencyFormat'), '%s%v')}'
    };
  </script>
  <script src="/jquery.js"></script>
  <script>
    jQuery(function ($) {
      $(document).on('click', '.do-manual-refund, .do-api-refund', function (event) {
        event.preventDefault();
        $.ajax({
          url: '/refund',
          method: 'POST',
          data: 'action=woocommerce_refund_line_items'
        });
      });
    });
  </script>
  <script src="/admin-refund.js"></script>
</body>
</html>`;

        if (pathname === '/') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(html);
            return;
        }

        if (pathname === '/jquery.js') {
            res.writeHead(200, { 'Content-Type': 'application/javascript; charset=utf-8' });
            res.end(fs.readFileSync(jqueryPath));
            return;
        }

        if (pathname === '/admin-refund.js') {
            res.writeHead(200, { 'Content-Type': 'application/javascript; charset=utf-8' });
            res.end(fs.readFileSync(pluginScriptPath));
            return;
        }

        if (pathname === '/refund') {
            req.on('data', (chunk) => {
                lastRequestBody += chunk.toString();
            });
            req.on('end', () => {
                requestCount += 1;
                res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
                res.end(JSON.stringify({ success: true }));
            });
            return;
        }

        if (pathname === '/request-count') {
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ requestCount, lastRequestBody }));
            return;
        }

        if (pathname === '/favicon.ico') {
            res.writeHead(204);
            return;
        }

        res.writeHead(404);
        res.end();
    });

    return {
        server,
        getState() {
            return { requestCount, lastRequestBody };
        }
    };
}

async function mountRefundUi(page, serverUrl, query = '') {
    const queryPrefix = query ? `?${query}` : '';
    await page.goto(`${serverUrl}${queryPrefix}`);
    await page.click('.refund-items');
    await expect(page.locator('#wrs-fee-container')).toBeVisible();
}

test.describe('admin refund deductions', () => {
    let fixture;
    let port;
    let serverUrl;

    test.beforeAll(async () => {
        fixture = createServer();
        await new Promise((resolve) => {
            fixture.server.listen(0, '127.0.0.1', () => {
                port = fixture.server.address().port;
                serverUrl = `http://127.0.0.1:${port}`;
                resolve();
            });
        });
    });

    test.afterAll(async () => {
        await new Promise((resolve, reject) => {
            fixture.server.close((error) => {
                if (error) {
                    reject(error);
                    return;
                }
                resolve();
            });
        });
    });

    test('shows net refund on action buttons for valid deductions', async ({ page }) => {
        await mountRefundUi(page, serverUrl);
        await page.check('#wrs_apply_box_damage_fee');
        await page.fill('#wrs_box_damage_fee', '5.00');

        await expect(page.locator('.do-manual-refund')).toHaveText('Refund $25.00 manually');
        await expect(page.locator('.do-api-refund')).toHaveText('Refund $25.00 via Stripe');
    });

    test('blocks refund submission and shows inline error when deductions exceed gross refund', async ({ page }) => {
        await mountRefundUi(page, serverUrl);
        const baseCount = fixture.getState().requestCount;

        await page.fill('#refund_amount', '10.00');
        await page.fill('#wrs_return_shipping_fee', '7.00');
        await page.check('#wrs_apply_box_damage_fee');
        await page.fill('#wrs_box_damage_fee', '5.00');

        await expect(page.locator('#wrs-validation-error')).toBeVisible();
        await expect(page.locator('#wrs-validation-error')).toContainText('Combined refund deductions cannot exceed the refund amount.');
        await expect(page.locator('.do-manual-refund')).toBeDisabled();

        await page.click('.do-manual-refund', { force: true });

        await expect.poll(() => fixture.getState().requestCount - baseCount).toBe(0);
    });

    test('uses zero-decimal currency precision for step and amount formatting', async ({ page }) => {
        await mountRefundUi(page, serverUrl, 'currency_format_num_decimals=0&grossAmount=40&defaultFee=12.00');

        await expect(page.locator('#wrs_return_shipping_fee')).toHaveAttribute('step', '1');
        await expect(page.locator('#wrs_return_shipping_fee')).toHaveValue('12');
        await expect(page.locator('.do-manual-refund')).toHaveText('Refund $28 manually');
    });

    test('updates button labels using multi-decimal currency parsing', async ({ page }) => {
        await mountRefundUi(page, serverUrl, 'currency_format_num_decimals=3&grossAmount=40.000&defaultFee=1.00');

        await expect(page.locator('#wrs_return_shipping_fee')).toHaveValue('1.000');
        await expect(page.locator('.do-manual-refund')).toHaveText('Refund $39.000 manually');
    });

    test('falls back to all .refund-actions when none are visible', async ({ page }) => {
        await mountRefundUi(page, serverUrl, 'actionLayout=all-hidden&defaultFee=0');

        await expect(page.locator('.refund-actions')).toHaveCount(2);
        await expect(page.locator('.refund-actions:visible')).toHaveCount(0);
        await expect(page.locator('#wrs-fee-container')).toBeVisible();
    });

    test('handles multiple .refund-actions rows in update flow', async ({ page }) => {
        await mountRefundUi(page, serverUrl, 'actionLayout=multiple-visible&grossAmount=40.00&defaultFee=5.00');

        await expect(page.locator('.do-manual-refund')).toHaveCount(2);
        await expect(page.locator('.do-manual-refund').first()).toHaveText('Refund $35.00 manually');
        await expect(page.locator('.do-manual-refund').nth(1)).toHaveText('Second refund 35.00');
    });

    test('injects localized aria-label attributes for both refund inputs', async ({ page }) => {
        const query =
            'feeLabel=Return%20Delivery&boxDamageLabel=Damage%20Fee&amountForLabel=Amount%20for%20%25s&defaultFee=1';

        await mountRefundUi(page, serverUrl, query);

        await expect(page.locator('#wrs_return_shipping_fee')).toHaveAttribute('aria-label', 'Amount for Return Delivery');
        await expect(page.locator('#wrs_box_damage_fee')).toHaveAttribute('aria-label', 'Amount for Damage Fee');
    });
});
