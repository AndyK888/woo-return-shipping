/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * @package WooReturnShipping
 * @version 1.6.0
 */

// Immediate debug - this should show in console even if jQuery isn't ready.
console.log('WRS v1.6.0: Script file loaded at', new Date().toISOString());

(function ($) {
    'use strict';

    if (!$) {
        console.error('WRS: jQuery not available!');
        return;
    }

    console.log('WRS: jQuery is available');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },

        init: function () {
            console.log('WRS: init() called, config:', this.config);

            $(document).ready(() => {
                console.log('WRS: document.ready fired');
                this.setup();
            });
        },

        setup: function () {
            console.log('WRS: setup() called');

            // Bind to refund button.
            $(document).on('click', '.refund-items', () => {
                console.log('WRS: .refund-items clicked');
                setTimeout(() => this.injectUI(), 500);
            });

            $(document).on('click', '.cancel-action', () => {
                console.log('WRS: .cancel-action clicked');
                $('.wrs-box').remove();
            });

            // Check if refund panel is already visible.
            if ($('#refund_amount').length) {
                console.log('WRS: Refund panel already visible');
                this.injectUI();
            }
        },

        injectUI: function () {
            console.log('WRS: injectUI() called');

            // Check if already injected.
            if ($('.wrs-box').length) {
                console.log('WRS: Already injected');
                return;
            }

            // Check if refund panel is active.
            const $refundAmount = $('#refund_amount');
            if (!$refundAmount.length) {
                console.log('WRS: #refund_amount not found');
                return;
            }
            console.log('WRS: #refund_amount found, value:', $refundAmount.val());

            // Find refund-actions.
            const $actions = $('.refund-actions');
            if (!$actions.length) {
                console.log('WRS: .refund-actions not found');
                return;
            }
            console.log('WRS: .refund-actions found');

            // Create and inject the UI.
            const defaultFee = parseFloat(this.config.defaultFee) || 10.00;
            const feeLabel = this.config.feeLabel || 'Return Shipping';

            const html = `
				<div class="wrs-box" style="margin: 15px 0; padding: 15px; background: #fffbeb; border: 2px solid #f0d866; border-radius: 6px;">
					<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
						<label style="display: flex; align-items: center; gap: 10px; font-weight: bold; cursor: pointer;">
							<input type="checkbox" id="wrs_apply_fee" checked style="width: 20px; height: 20px;">
							<span>${this.escapeHtml(feeLabel)}</span>
						</label>
						<div style="display: flex; align-items: center; gap: 5px;">
							<span style="color: #c00; font-weight: bold;">−$</span>
							<input type="number" id="wrs_fee_amount" value="${defaultFee.toFixed(2)}" step="0.01" min="0" 
								style="width: 80px; text-align: right; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
						</div>
					</div>
					<div style="border-top: 1px solid #e0d48d; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
						<strong>Net Refund to Customer:</strong>
						<strong id="wrs_net_refund" style="font-size: 18px; color: #2e7d32;">$0.00</strong>
					</div>
					<div style="margin-top: 8px; font-size: 12px; color: #666;">
						This is the amount that will be sent to the payment gateway.
					</div>
				</div>
			`;

            $actions.before(html);
            console.log('WRS: UI injected!');

            this.bindEvents();
            this.updateDisplay();
        },

        bindEvents: function () {
            // Unbind previous.
            $(document).off('.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                $('#wrs_fee_amount').prop('disabled', !$('#wrs_apply_fee').is(':checked'));
                this.updateDisplay();
            });

            $(document).on('input.wrs change.wrs', '#wrs_fee_amount, #refund_amount', () => {
                this.updateDisplay();
            });

            $(document).on('change.wrs keyup.wrs', '.refund_order_item_qty, .refund_line_total', () => {
                setTimeout(() => this.updateDisplay(), 200);
            });

            this.interceptAjax();
        },

        updateDisplay: function () {
            const refundAmount = this.parseAmount($('#refund_amount').val());
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_fee_amount').val()) : 0;
            const netRefund = Math.max(0, refundAmount - feeAmount);

            $('#wrs_net_refund').text(this.formatCurrency(netRefund));
            console.log('WRS: Display updated', { refund: refundAmount, fee: feeAmount, net: netRefund });
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
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        interceptAjax: function () {
            if (this.ajaxBound) return;
            this.ajaxBound = true;

            $(document).ajaxSend((e, xhr, settings) => {
                if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) return;

                console.log('WRS: Intercepting refund AJAX');

                const applyFee = $('#wrs_apply_fee').is(':checked');
                const feeAmount = applyFee ? this.parseAmount($('#wrs_fee_amount').val()) : 0;

                settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');
                settings.data += '&wrs_return_shipping_fee=' + feeAmount;

                if (feeAmount > 0) {
                    const match = settings.data.match(/refund_amount=([^&]*)/);
                    if (match) {
                        const original = parseFloat(match[1]) || 0;
                        const net = Math.max(0, original - feeAmount).toFixed(2);
                        settings.data = settings.data.replace(/refund_amount=[^&]*/, 'refund_amount=' + net);
                        settings.data += '&wrs_original_amount=' + original;
                        console.log('WRS: MODIFIED refund_amount:', { original, fee: feeAmount, net });
                    }
                }
            });
        }
    };

    WRS.init();

})(jQuery);
