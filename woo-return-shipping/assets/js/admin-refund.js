/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Simple UI injection - just passes data to PHP which handles the logic.
 *
 * @package WooReturnShipping
 * @version 1.7.0
 */

(function ($) {
    'use strict';

    console.log('WRS v1.7.0: Script loaded');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,

        init: function () {
            $(document).ready(() => {
                console.log('WRS: Document ready');
                this.setup();
            });
        },

        setup: function () {
            // Watch for refund button click.
            $(document).on('click', '.refund-items', () => {
                console.log('WRS: Refund button clicked');
                setTimeout(() => this.injectUI(), 400);
            });

            // Clean up on cancel.
            $(document).on('click', '.cancel-action', () => {
                $('.wrs-fee-box').remove();
                this.injected = false;
            });
        },

        injectUI: function () {
            if ($('.wrs-fee-box').length) {
                console.log('WRS: Already injected');
                return;
            }

            const $actions = $('.refund-actions');
            if (!$actions.length) {
                console.log('WRS: .refund-actions not found');
                return;
            }

            const fee = parseFloat(this.config.defaultFee) || 10.00;
            const label = this.config.feeLabel || 'Return Shipping';

            const html = `
				<div class="wrs-fee-box" style="margin: 15px 0; padding: 15px; background: #fffbeb; border: 2px solid #f0d866; border-radius: 6px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
						<label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer;">
							<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked 
								style="width: 18px; height: 18px;">
							${this.escapeHtml(label)}
						</label>
						<div style="display: flex; align-items: center;">
							<span style="color: #c00; font-weight: bold; margin-right: 4px;">−$</span>
							<input type="number" id="wrs_return_shipping_fee" name="wrs_return_shipping_fee" 
								value="${fee.toFixed(2)}" step="0.01" min="0"
								style="width: 80px; text-align: right; padding: 5px;">
						</div>
					</div>
					<div style="border-top: 1px solid #e0d48d; padding-top: 10px;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
							<span style="color: #666;">Gross Refund:</span>
							<span id="wrs_gross">$0.00</span>
						</div>
						<div style="display: flex; justify-content: space-between;">
							<strong style="color: #155724;">Net to Customer:</strong>
							<strong id="wrs_net" style="color: #155724; font-size: 16px;">$0.00</strong>
						</div>
					</div>
					<div style="margin-top: 8px; font-size: 11px; color: #666;">
						The net amount will be sent to the payment gateway.
					</div>
				</div>
			`;

            $actions.before(html);
            console.log('WRS: UI injected');

            this.injected = true;
            this.bindEvents();
            this.updateDisplay();
        },

        bindEvents: function () {
            $(document).off('.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                $('#wrs_return_shipping_fee').prop('disabled', !$('#wrs_apply_fee').is(':checked'));
                this.updateDisplay();
            });

            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee, #refund_amount', () => {
                this.updateDisplay();
            });

            $(document).on('change.wrs', '.refund_order_item_qty, .refund_line_total', () => {
                setTimeout(() => this.updateDisplay(), 150);
            });

            // SIMPLE: Just add our fields to the AJAX data. PHP handles the rest.
            this.hookAjax();
        },

        updateDisplay: function () {
            const gross = this.parseAmount($('#refund_amount').val());
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const fee = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;
            const net = Math.max(0, gross - fee);

            $('#wrs_gross').text(this.formatCurrency(gross));
            $('#wrs_net').text(this.formatCurrency(net));

            console.log('WRS: Display updated', { gross, fee, net });
        },

        hookAjax: function () {
            if (this.hooked) return;
            this.hooked = true;

            // Add our fields to the refund AJAX request.
            $(document).ajaxSend((e, xhr, settings) => {
                if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) {
                    return;
                }

                const applyFee = $('#wrs_apply_fee').is(':checked') ? '1' : '0';
                const feeAmount = $('#wrs_return_shipping_fee').val() || '0';

                settings.data += '&wrs_apply_fee=' + applyFee;
                settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);

                console.log('WRS: Added to AJAX', { applyFee, feeAmount });
            });
        },

        parseAmount: function (val) {
            if (!val) return 0;
            return parseFloat(val.toString().replace(/[^0-9.\-]/g, '')) || 0;
        },

        formatCurrency: function (amount) {
            if (window.accounting && window.woocommerce_admin_meta_boxes) {
                const p = window.woocommerce_admin_meta_boxes;
                return window.accounting.formatMoney(amount, {
                    symbol: p.currency_format_symbol,
                    decimal: p.currency_format_decimal_sep,
                    thousand: p.currency_format_thousand_sep,
                    precision: p.currency_format_num_decimals,
                    format: p.currency_format
                });
            }
            return '$' + amount.toFixed(2);
        },

        escapeHtml: function (str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    };

    WRS.init();
})(jQuery);
