/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * @package WooReturnShipping
 * @version 1.4.0
 */

(function ($) {
    'use strict';

    console.log('WRS: Script loaded v1.4.0');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,

        init: function () {
            $(document).ready(this.setup.bind(this));
        },

        setup: function () {
            console.log('WRS: Setup called');

            // Watch for refund button click.
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));

            // Check if refund mode is already active.
            setTimeout(() => this.tryInject(), 500);
        },

        onRefundStart: function () {
            console.log('WRS: Refund button clicked');
            setTimeout(() => this.tryInject(), 300);
        },

        onRefundCancel: function () {
            console.log('WRS: Refund cancelled');
            $('.wrs-refund-fields').remove();
            this.injected = false;
        },

        tryInject: function () {
            console.log('WRS: Trying to inject');

            if (this.injected && $('.wrs-refund-fields').length) {
                console.log('WRS: Already injected');
                return;
            }

            // Check if refund amount field exists (means refund mode is active).
            const $refundAmount = $('#refund_amount');
            if (!$refundAmount.length) {
                console.log('WRS: Refund amount field not found');
                return;
            }

            // Get template.
            const template = $('#tmpl-wrs-refund-fields').html();
            if (!template) {
                console.log('WRS: Template not found');
                return;
            }

            // Find refund actions and insert before.
            const $refundActions = $('.refund-actions');
            if ($refundActions.length) {
                $refundActions.before(template);
                console.log('WRS: Injected before refund-actions');
            } else {
                console.log('WRS: refund-actions not found');
                return;
            }

            this.injected = true;
            this.bindEvents();
            this.updateNetRefund();
        },

        bindEvents: function () {
            $(document).off('.wrs');

            // Toggle fee.
            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                const checked = $('#wrs_apply_fee').is(':checked');
                $('#wrs_return_shipping_fee').prop('disabled', !checked);
                this.updateNetRefund();
            });

            // Fee amount change.
            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee', () => {
                this.updateNetRefund();
            });

            // WC refund amount change.
            $(document).on('input.wrs change.wrs keyup.wrs', '#refund_amount', () => {
                this.updateNetRefund();
            });

            // Watch for WC recalculating.
            $(document).on('change.wrs', '.refund_order_item_qty, .refund_line_total', () => {
                setTimeout(() => this.updateNetRefund(), 150);
            });

            // Intercept AJAX - THIS IS THE KEY!
            this.interceptAjax();
        },

        updateNetRefund: function () {
            const wcAmount = this.parseAmount($('#refund_amount').val());
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

            let netRefund = Math.max(0, wcAmount - feeAmount);

            if (feeAmount > wcAmount && wcAmount > 0) {
                $('#wrs_return_shipping_fee').css('border-color', '#d63638');
            } else {
                $('#wrs_return_shipping_fee').css('border-color', '');
            }

            $('#wrs_net_refund').text(this.formatCurrency(netRefund));

            console.log('WRS: Updated display', { wc: wcAmount, fee: feeAmount, net: netRefund });
        },

        parseAmount: function (value) {
            if (!value) return 0;
            return parseFloat(value.toString().replace(/[^0-9.,\-]/g, '').replace(',', '.')) || 0;
        },

        formatCurrency: function (amount) {
            if (window.woocommerce_admin_meta_boxes && window.accounting) {
                const p = window.woocommerce_admin_meta_boxes;
                return window.accounting.formatMoney(amount, {
                    symbol: p.currency_format_symbol,
                    decimal: p.currency_format_decimal_sep,
                    thousand: p.currency_format_thousand_sep,
                    precision: p.currency_format_num_decimals,
                    format: p.currency_format,
                });
            }
            return '$' + amount.toFixed(2);
        },

        /**
         * CRITICAL: Intercept AJAX and modify refund_amount in the POST data.
         */
        interceptAjax: function () {
            if (this.ajaxIntercepted) return;
            this.ajaxIntercepted = true;

            $(document).ajaxSend((event, jqXHR, settings) => {
                if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) {
                    return;
                }

                console.log('WRS: Intercepting refund AJAX');

                const applyFee = $('#wrs_apply_fee').is(':checked');
                const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

                // Add our custom fields.
                settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');

                // MODIFY THE REFUND AMOUNT!
                if (feeAmount > 0) {
                    // Parse current refund_amount from the data string.
                    const match = settings.data.match(/refund_amount=([^&]*)/);
                    if (match) {
                        const originalAmount = parseFloat(match[1]) || 0;
                        const netAmount = Math.max(0, originalAmount - feeAmount).toFixed(2);

                        // Replace in data string.
                        settings.data = settings.data.replace(
                            /refund_amount=[^&]*/,
                            'refund_amount=' + netAmount
                        );

                        // Store original for backend.
                        settings.data += '&wrs_original_refund_amount=' + encodeURIComponent(originalAmount);

                        console.log('WRS: MODIFIED refund_amount', {
                            original: originalAmount,
                            fee: feeAmount,
                            net: netAmount,
                            data: settings.data.substring(0, 200) + '...'
                        });
                    }
                }
            });
        },
    };

    WRS.init();
})(jQuery);
