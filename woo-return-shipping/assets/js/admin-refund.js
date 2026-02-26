/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Uses multiple selectors and MutationObserver to detect refund panel.
 *
 * @package WooReturnShipping
 * @version 2.1.0
 */

(function ($) {
    'use strict';

    console.log('WRS v2.1.0: Script loaded');

    var WRS = {
        config: window.wrsConfig || { defaultFee: 10.00, feeLabel: 'Return Shipping' },
        observing: false,

        init: function () {
            console.log('WRS: init()');
            var self = this;

            // Try MANY possible button selectors for refund.
            var buttonSelectors = [
                '.refund-items',
                '.button.refund-items',
                '.wc-order-refund-items',
                'button.refund-items',
                '[data-action="refund"]',
                '.do-api-refund',
                '.do-manual-refund'
            ];

            $(document).on('click', buttonSelectors.join(', '), function (e) {
                console.log('WRS: Refund button clicked!', e.target);
                setTimeout(function () { self.tryInject(); }, 400);
            });

            // Also watch for Cancel button.
            $(document).on('click', '.cancel-action, .wc-order-bulk-actions .cancel', function () {
                console.log('WRS: Cancel clicked');
                $('#wrs-fee-container').remove();
            });

            // ALSO use a MutationObserver to detect when refund UI appears.
            this.observeRefundPanel();

            // Check immediately if refund panel is already visible.
            setTimeout(function () { self.tryInject(); }, 1000);
        },

        observeRefundPanel: function () {
            if (this.observing) return;
            this.observing = true;

            var self = this;
            var observer = new MutationObserver(function (mutations) {
                // Check if refund-actions appeared.
                if ($('.refund-actions:visible').length && !$('#wrs-fee-container').length) {
                    console.log('WRS: MutationObserver detected refund-actions');
                    self.tryInject();
                }
            });

            // Observe the woocommerce order metabox.
            var target = document.querySelector('#woocommerce-order-items') || document.body;
            observer.observe(target, { childList: true, subtree: true, attributes: true });
            console.log('WRS: MutationObserver started on', target);
        },

        tryInject: function () {
            console.log('WRS: tryInject()');

            // Don't double-inject.
            if ($('#wrs-fee-container').length) {
                console.log('WRS: Already injected');
                return;
            }

            // Look for refund-actions in various possible containers.
            var $actions = $('.refund-actions:visible');
            console.log('WRS: Looking for .refund-actions:visible, found:', $actions.length);

            if (!$actions.length) {
                // Try without :visible.
                $actions = $('.refund-actions');
                console.log('WRS: Looking for .refund-actions (any), found:', $actions.length);
            }

            if (!$actions.length) {
                console.log('WRS: No refund-actions found');
                return;
            }

            // Check if refund amount input exists.
            var $refundAmount = $('#refund_amount');
            console.log('WRS: #refund_amount found:', $refundAmount.length, 'value:', $refundAmount.val());

            this.doInject($actions.first());
        },

        doInject: function ($actions) {
            var fee = parseFloat(this.config.defaultFee) || 10.00;
            var label = this.config.feeLabel || 'Return Shipping';

            var html = '' +
                '<div id="wrs-fee-container" style="margin: 15px 0; padding: 15px; background: #fffbeb; border: 2px solid #f0d866; border-radius: 6px;">' +
                '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' +
                '<label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer;">' +
                '<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked style="width: 18px; height: 18px;">' +
                this.escapeHtml(label) +
                '</label>' +
                '<div style="display: flex; align-items: center;">' +
                '<span style="color: #c00; font-weight: bold; margin-right: 4px;">−$</span>' +
                '<input type="number" id="wrs_return_shipping_fee" name="wrs_return_shipping_fee" value="' + fee.toFixed(2) + '" step="0.01" min="0" style="width: 80px; text-align: right; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">' +
                '</div>' +
                '</div>' +
                '<div style="border-top: 1px solid #e0d48d; padding-top: 10px;">' +
                '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">' +
                '<span style="color: #666;">Gross Refund:</span>' +
                '<span id="wrs_gross">$0.00</span>' +
                '</div>' +
                '<div style="display: flex; justify-content: space-between;">' +
                '<strong style="color: #155724;">Net to Customer:</strong>' +
                '<strong id="wrs_net" style="color: #155724; font-size: 16px;">$0.00</strong>' +
                '</div>' +
                '</div>' +
                '<div style="margin-top: 8px; font-size: 11px; color: #666;">This amount will be sent to the payment gateway.</div>' +
                '</div>';

            $actions.before(html);
            console.log('WRS: ✅ UI INJECTED!');

            this.bind();
            this.update();
        },

        bind: function () {
            var self = this;

            $(document).off('.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee', function () {
                $('#wrs_return_shipping_fee').prop('disabled', !$(this).is(':checked'));
                self.update();
            });

            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee, #refund_amount', function () {
                self.update();
            });

            $(document).on('change.wrs', '.refund_order_item_qty, .refund_line_total', function () {
                setTimeout(function () { self.update(); }, 150);
            });

            // Add data to AJAX.
            $(document).ajaxSend(function (e, xhr, settings) {
                if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) {
                    return;
                }

                var applyFee = $('#wrs_apply_fee').is(':checked') ? '1' : '0';
                var feeAmount = $('#wrs_return_shipping_fee').val() || '0';

                settings.data += '&wrs_apply_fee=' + applyFee;
                settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);

                console.log('WRS: Added to AJAX', { applyFee: applyFee, feeAmount: feeAmount });
            });
        },

        update: function () {
            var gross = parseFloat($('#refund_amount').val()) || 0;
            var applyFee = $('#wrs_apply_fee').is(':checked');
            var fee = applyFee ? (parseFloat($('#wrs_return_shipping_fee').val()) || 0) : 0;
            var net = Math.max(0, gross - fee);

            $('#wrs_gross').text('$' + gross.toFixed(2));
            $('#wrs_net').text('$' + net.toFixed(2));
            this.updateButtonLabels(gross, net, applyFee, fee);
        },

        updateButtonLabels: function (gross, net, applyFee, fee) {
            var shouldApply = applyFee && fee > 0;
            var $buttons = $('.refund-actions .button, .refund-actions button, .refund-actions a.button');

            if (!$buttons.length) {
                return;
            }

            var formattedNet = this.formatMoney(net);
            var formattedNetNumber = this.formatNumber(net);
            var self = this;

            $buttons.each(function () {
                var $button = $(this);
                var currentLabel = $button.text();
                var originalLabel = $button.data('wrs-original-label');
                var lastNetLabel = $button.data('wrs-net-label');
                var isShowingNetLabel = lastNetLabel && currentLabel === lastNetLabel;

                if (!currentLabel) {
                    return;
                }

                if (!shouldApply) {
                    // Only restore when we know the current label was written by WRS.
                    if (isShowingNetLabel && originalLabel) {
                        $button.text(originalLabel);
                        currentLabel = originalLabel;
                    }

                    // Keep baseline synced with WooCommerce's live amount updates.
                    $button.data('wrs-original-label', currentLabel);
                    $button.removeData('wrs-net-label');
                    return;
                }

                // If WooCommerce updated the gross label, use that as the replacement source.
                var sourceLabel = isShowingNetLabel && originalLabel ? originalLabel : currentLabel;
                $button.data('wrs-original-label', sourceLabel);

                var updatedLabel = self.replaceAmountInLabel(sourceLabel, formattedNet, formattedNetNumber);

                if (updatedLabel && updatedLabel !== currentLabel) {
                    $button.text(updatedLabel);
                }

                if (updatedLabel) {
                    $button.data('wrs-net-label', updatedLabel);
                }
            });
        },

        getCurrencyFormat: function () {
            var meta = window.woocommerce_admin_meta_boxes || {};
            var precision = parseInt(meta.currency_format_num_decimals || 2, 10);

            return {
                symbol: meta.currency_format_symbol || '$',
                decimal: meta.currency_format_decimal_sep || '.',
                thousand: meta.currency_format_thousand_sep || ',',
                precision: isNaN(precision) ? 2 : precision,
                format: meta.currency_format || '%s%v'
            };
        },

        formatMoney: function (amount) {
            var currency = this.getCurrencyFormat();

            if (window.accounting && typeof window.accounting.formatMoney === 'function') {
                return window.accounting.formatMoney(amount, currency);
            }

            var fixed = amount.toFixed(currency.precision);
            if (currency.format.indexOf('%s') !== -1 && currency.format.indexOf('%v') !== -1) {
                return currency.format.replace('%s', currency.symbol).replace('%v', fixed);
            }

            return currency.symbol + fixed;
        },

        formatNumber: function (amount) {
            var currency = this.getCurrencyFormat();

            if (window.accounting && typeof window.accounting.formatNumber === 'function') {
                return window.accounting.formatNumber(amount, currency.precision, currency.thousand, currency.decimal);
            }

            return amount.toFixed(currency.precision);
        },

        replaceAmountInLabel: function (label, formattedMoney, formattedNumber) {
            if (!label) {
                return label;
            }

            var currency = this.getCurrencyFormat();
            var escapedSymbol = currency.symbol.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var escapedDecimal = currency.decimal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var escapedThousand = currency.thousand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var amountPattern = '[0-9]+(?:' + escapedThousand + '[0-9]{3})*(?:' + escapedDecimal + '[0-9]{2})?';

            var symbolBefore = new RegExp(escapedSymbol + '\\s*' + amountPattern);
            var symbolAfter = new RegExp(amountPattern + '\\s*' + escapedSymbol);

            if (symbolBefore.test(label)) {
                return label.replace(symbolBefore, formattedMoney);
            }

            if (symbolAfter.test(label)) {
                return label.replace(symbolAfter, formattedMoney);
            }

            var numberOnly = new RegExp(amountPattern);
            if (numberOnly.test(label)) {
                return label.replace(numberOnly, formattedNumber);
            }

            return label;
        },

        escapeHtml: function (str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        console.log('WRS: Document ready');
        WRS.init();
    });

})(jQuery);
