/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Injects return shipping fee row into the order items table.
 * The fee is subtracted from the refund total natively by WC.
 *
 * @package WooReturnShipping
 * @version 1.3.0
 */

(function ($) {
    'use strict';

    console.log('WRS: Script loaded v1.3.0');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,

        init: function () {
            $(document).ready(this.setup.bind(this));
        },

        setup: function () {
            // Watch for refund button click.
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));
        },

        onRefundStart: function () {
            console.log('WRS: Refund mode started');
            setTimeout(() => this.injectReturnShippingRow(), 200);
        },

        onRefundCancel: function () {
            console.log('WRS: Refund cancelled');
            $('.wrs-return-shipping-item').hide();
            $('.wrs-net-summary').hide();
            this.injected = false;
        },

        injectReturnShippingRow: function () {
            console.log('WRS: Injecting return shipping row');

            // Check if already injected.
            if ($('.wrs-return-shipping-item').length === 0) {
                // Get template and inject after last shipping row.
                const rowTemplate = $('#tmpl-wrs-return-shipping-row').html();
                const summaryTemplate = $('#tmpl-wrs-net-refund-summary').html();

                if (!rowTemplate) {
                    console.log('WRS: Row template not found');
                    return;
                }

                // Find the shipping items in the table and insert after the last one.
                const $shippingRows = $('#order_shipping_line_items tr.shipping');

                if ($shippingRows.length) {
                    // Insert after the last shipping row.
                    $shippingRows.last().after(rowTemplate);
                    console.log('WRS: Inserted after shipping rows');
                } else {
                    // Fallback: insert at the end of the line items table.
                    const $table = $('#order_line_items tbody');
                    if ($table.length) {
                        $table.append(rowTemplate);
                        console.log('WRS: Appended to order_line_items');
                    }
                }

                // Add summary before refund actions.
                const $actions = $('.refund-actions');
                if ($actions.length && summaryTemplate) {
                    $actions.before(summaryTemplate);
                }
            }

            // Show the row and summary.
            $('.wrs-return-shipping-item').show();
            $('.wrs-net-summary').show();

            this.injected = true;
            this.bindEvents();
            this.updateNetRefund();
        },

        bindEvents: function () {
            $(document).off('.wrs');

            // Toggle fee when checkbox changes.
            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                const checked = $('#wrs_apply_fee').is(':checked');
                $('#wrs_return_shipping_fee').prop('disabled', !checked);
                this.updateNetRefund();
            });

            // Update when fee amount changes.
            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee', () => {
                this.updateNetRefund();
            });

            // Update when WC recalculates (when item qty changes).
            $(document).on('change.wrs keyup.wrs', '.refund_order_item_qty, .refund_line_total, .refund_line_tax', () => {
                setTimeout(() => this.updateNetRefund(), 100);
            });

            // Watch refund_amount field for WC updates.
            $(document).on('change.wrs', '#refund_amount', () => {
                this.updateNetRefund();
            });

            // Intercept AJAX to add our fee data.
            this.interceptAjax();
        },

        updateNetRefund: function () {
            // Get WC's calculated refund amount.
            const wcRefundAmount = this.parseAmount($('#refund_amount').val());

            // Get our fee.
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

            // Calculate net.
            let netRefund = Math.max(0, wcRefundAmount - feeAmount);

            // Validate.
            if (feeAmount > wcRefundAmount && wcRefundAmount > 0) {
                $('#wrs_return_shipping_fee').addClass('wrs-error');
            } else {
                $('#wrs_return_shipping_fee').removeClass('wrs-error');
            }

            // Update display.
            $('#wrs_net_refund').text(this.formatCurrency(netRefund));

            console.log('WRS: Net refund updated', { wc: wcRefundAmount, fee: feeAmount, net: netRefund });
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

        interceptAjax: function () {
            if (this.ajaxIntercepted) return;
            this.ajaxIntercepted = true;

            // Intercept the refund AJAX to modify the amount sent to gateway.
            $(document).ajaxSend((event, jqXHR, settings) => {
                if (settings.data && settings.data.indexOf('action=woocommerce_refund_line_items') !== -1) {
                    const applyFee = $('#wrs_apply_fee').is(':checked');
                    const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

                    // Add our custom data.
                    settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                    settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');

                    // IMPORTANT: Modify the refund_amount to be the NET amount!
                    if (feeAmount > 0) {
                        const currentAmount = this.parseAmount($('#refund_amount').val());
                        const netAmount = Math.max(0, currentAmount - feeAmount);

                        // Replace refund_amount in the data string.
                        settings.data = settings.data.replace(
                            /refund_amount=[^&]*/,
                            'refund_amount=' + netAmount.toFixed(2)
                        );

                        // Store original for the fee line item.
                        settings.data += '&wrs_original_refund_amount=' + encodeURIComponent(currentAmount);

                        console.log('WRS: Modified refund_amount for gateway', {
                            original: currentAmount,
                            net: netAmount,
                            fee: feeAmount
                        });
                    }
                }
            });
        },
    };

    WRS.init();
})(jQuery);
