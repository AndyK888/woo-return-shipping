/**
 * WooCommerce Return Shipping - Admin Refund Modal Enhancement
 *
 * @package WooReturnShipping
 * @version 1.1.0
 */

(function ($) {
    'use strict';

    console.log('WRS: Script loaded');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,

        init: function () {
            console.log('WRS: Initializing');
            $(document).ready(this.setup.bind(this));
        },

        setup: function () {
            console.log('WRS: Document ready');

            // Watch for refund button click.
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));

            // Also try to inject if refund panel is already open.
            setTimeout(() => this.tryInject(), 500);
        },

        onRefundStart: function () {
            console.log('WRS: Refund button clicked');
            // Wait for WC to show the refund UI.
            setTimeout(() => this.tryInject(), 300);
        },

        onRefundCancel: function () {
            console.log('WRS: Refund cancelled');
            $('.wrs-refund-fields').remove();
            this.injected = false;
        },

        tryInject: function () {
            console.log('WRS: Trying to inject UI');

            if (this.injected && $('.wrs-refund-fields').length) {
                console.log('WRS: Already injected');
                return;
            }

            // Find the refund amount field - this is our anchor point.
            const $refundAmount = $('#refund_amount');

            if (!$refundAmount.length) {
                console.log('WRS: Refund amount field not found');
                return;
            }

            console.log('WRS: Found refund amount field');

            // Get the template HTML.
            const template = $('#tmpl-wrs-refund-fields').html();

            if (!template) {
                console.log('WRS: Template not found');
                return;
            }

            // Find where to insert - look for the refund actions area.
            const $refundActions = $('.refund-actions');

            if ($refundActions.length) {
                console.log('WRS: Inserting before refund-actions');
                $refundActions.before(template);
            } else {
                // Try inserting after the totals table.
                const $totals = $refundAmount.closest('table');
                if ($totals.length) {
                    console.log('WRS: Inserting after totals table');
                    $totals.after(template);
                } else {
                    console.log('WRS: Could not find insertion point');
                    return;
                }
            }

            this.injected = true;
            this.bindEvents();
            this.updateNetRefund();
            console.log('WRS: UI injected successfully');
        },

        bindEvents: function () {
            $(document).off('.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                const checked = $('#wrs_apply_fee').is(':checked');
                $('#wrs_return_shipping_fee').prop('disabled', !checked);
                this.updateNetRefund();
            });

            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee, #refund_amount', () => {
                this.updateNetRefund();
            });

            // Intercept AJAX.
            this.interceptAjax();
        },

        updateNetRefund: function () {
            const refundAmount = this.parseAmount($('#refund_amount').val());
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

            let netRefund = Math.max(0, refundAmount - feeAmount);

            // Show error if fee exceeds refund.
            if (feeAmount > refundAmount && refundAmount > 0) {
                $('#wrs_return_shipping_fee').css('border-color', '#d63638');
            } else {
                $('#wrs_return_shipping_fee').css('border-color', '');
            }

            $('#wrs_net_refund').text(this.formatCurrency(netRefund));
        },

        parseAmount: function (value) {
            if (!value) return 0;
            return parseFloat(value.toString().replace(/[^0-9.,\-]/g, '').replace(',', '.')) || 0;
        },

        formatCurrency: function (amount) {
            // Use WC formatting if available.
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

        interceptAjax: function () {
            if (this.ajaxIntercepted) return;
            this.ajaxIntercepted = true;

            $(document).ajaxSend((event, jqXHR, settings) => {
                if (settings.data && settings.data.indexOf('action=woocommerce_refund_line_items') !== -1) {
                    const applyFee = $('#wrs_apply_fee').is(':checked');
                    const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

                    settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                    settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');

                    console.log('WRS: Added fee data to AJAX request', { applyFee, feeAmount });
                }
            });
        },
    };

    WRS.init();
})(jQuery);
