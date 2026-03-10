const fs = require('fs');
const http = require('http');
const path = require('path');
const { test, expect } = require('@playwright/test');

const pluginScriptPath = path.resolve(__dirname, '../../woo-return-shipping/assets/js/admin-refund.js');
const jqueryPath = require.resolve('jquery/dist/jquery.min.js');

function createServer() {
    let requestCount = 0;
    let lastRequestBody = '';

    const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Refund Fixture</title>
</head>
<body>
  <button class="refund-items">Refund</button>
  <div id="woocommerce-order-items">
    <input id="refund_amount" value="40.00" />
    <div class="refund-actions" style="display:block">
      <button class="button do-manual-refund">Refund $40.00 manually</button>
      <button class="button do-api-refund">Refund $40.00 via Stripe</button>
    </div>
  </div>
  <script>
    window.wrsConfig = {
      defaultFee: 10.00,
      feeLabel: 'Return Shipping',
      boxDamageDefaultFee: 0.00,
      boxDamageLabel: 'Retail Box Damage',
      messages: {
        combinedDeductionsExceedRefund: 'Combined refund deductions cannot exceed the refund amount.',
        invalidDeductionAmount: 'Deduction amounts must be valid non-negative numbers.'
      }
    };
    window.woocommerce_admin_meta_boxes = {
      currency_format_symbol: '$',
      currency_format_decimal_sep: '.',
      currency_format_thousand_sep: ',',
      currency_format_num_decimals: 2,
      currency_format: '%s%v'
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

    const server = http.createServer((req, res) => {
        if (req.url === '/') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(html);
            return;
        }

        if (req.url === '/jquery.js') {
            res.writeHead(200, { 'Content-Type': 'application/javascript; charset=utf-8' });
            res.end(fs.readFileSync(jqueryPath));
            return;
        }

        if (req.url === '/admin-refund.js') {
            res.writeHead(200, { 'Content-Type': 'application/javascript; charset=utf-8' });
            res.end(fs.readFileSync(pluginScriptPath));
            return;
        }

        if (req.url === '/refund') {
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

        if (req.url === '/request-count') {
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ requestCount, lastRequestBody }));
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

async function mountRefundUi(page, serverUrl) {
    await page.goto(serverUrl);
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
                serverUrl = `http://127.0.0.1:${port}/`;
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
        await page.fill('#refund_amount', '10.00');
        await page.fill('#wrs_return_shipping_fee', '7.00');
        await page.check('#wrs_apply_box_damage_fee');
        await page.fill('#wrs_box_damage_fee', '5.00');

        await expect(page.locator('#wrs-validation-error')).toBeVisible();
        await expect(page.locator('#wrs-validation-error')).toContainText('Combined refund deductions cannot exceed the refund amount.');
        await expect(page.locator('.do-manual-refund')).toBeDisabled();

        await page.click('.do-manual-refund', { force: true });

        await expect.poll(() => fixture.getState().requestCount).toBe(0);
    });
});
