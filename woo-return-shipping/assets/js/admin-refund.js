/**
 * WooCommerce Return Shipping - Admin Refund Modal Enhancement
 *
 * @package WooReturnShipping
 * @version 1.2.0
 */

(function ($) {
    'use strict';

    console.log('WRS: Script loaded v1.2.0');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,
        originalRefundAmount: 0,

        init: function () {
            $(document).ready(this.setup.bind(this));
        },

        setup: function () {
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));
            setTimeout(() => this.tryInject(), 500);
        },

        onRefundStart: function () {
            console.log('WRS: Refund button clicked');
            setTimeout(() => this.tryInject(), 300);
        },

        onRefundCancel: function () {
            $('.wrs-refund-fields').remove();
            this.injected = false;
        },

        tryInject: function () {
            if (this.injected && $('.wrs-refund-fields').length) {
                return;
            }

            const $refundAmount = $('#refund_amount');
            if (!$refundAmount.length) {
                console.log('WRS: Refund amount field not found');
                return;
            }

            // Store original refund amount for calculations.
            this.originalRefundAmount = this.parseAmount($refundAmount.val());

            const template = $('#tmpl-wrs-refund-fields').html();
            if (!template) {
                console.log('WRS: Template not found');
                return;
            }

            const $refundActions = $('.refund-actions');
            if ($refundActions.length) {
                $refundActions.before(template);
            } else {
                return;
            }

            this.injected = true;
            this.bindEvents();
            this.updateAmounts();
            console.log('WRS: UI injected successfully');
        },

        bindEvents: function () {
            $(document).off('.wrs');

            // When checkbox changes.
            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                const checked = $('#wrs_apply_fee').is(':checked');
                $('#wrs_return_shipping_fee').prop('disabled', !checked);
                this.updateAmounts();
            });

            // When fee amount changes.
            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee', () => {
                this.updateAmounts();
            });

            // When WC updates the refund amount (when user changes item qty).
            $(document).on('keyup.wrs change.wrs', '#refund_amount', (e) => {
                // Only update original if user manually typed.
                if (e.originalEvent) {
                    this.originalRefundAmount = this.parseAmount($('#refund_amount').val());
                    this.updateAmounts();
                }
            });

            // Watch for WC recalculating items.
            this.watchRefundAmountChanges();

            this.interceptAjax();
        },

        /**
         * Watch for WooCommerce updating the refund amount when items change.
         */
        watchRefundAmountChanges: function () {
            // Use MutationObserver to watch for WC updating the refund_amount.
            const $refundAmount = document.getElementById('refund_amount');
            if ($refundAmount && !this.observer) {
                this.observer = new MutationObserver(() => {
                    const newAmount = this.parseAmount($('#refund_amount').val());
                    if (Math.abs(newAmount - this.originalRefundAmount) > 0.01) {
                        // WC changed the amount, update our original.
                        this.originalRefundAmount = newAmount + this.getCurrentFee();
                        this.updateAmounts();
                    }
                });
                this.observer.observe($refundAmount, { attributes: true, attributeFilter: ['value'] });
            }

            // Also watch for item quantity changes.
            $(document).on('change.wrs keyup.wrs', '.refund_order_item_qty, .refund_line_total', () => {
                setTimeout(() => {
                    // After WC recalculates, grab the new total and add our fee back.
                    const wcAmount = this.parseAmount($('#refund_amount').val());
                    this.originalRefundAmount = wcAmount + this.getCurrentFee();
                    this.updateAmounts();
                }, 100);
            });
        },

        getCurrentFee: function () {
            const applyFee = $('#wrs_apply_fee').is(':checked');
            return applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;
        },

        /**
         * Update the refund amount field and net display.
         * This is the key - we modify the actual #refund_amount so gateway gets correct amount.
         */
        updateAmounts: function () {
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

            // Calculate net refund.
            let netRefund = Math.max(0, this.originalRefundAmount - feeAmount);

            // Validate fee doesn't exceed refund.
            if (feeAmount > this.originalRefundAmount && this.originalRefundAmount > 0) {
                $('#wrs_return_shipping_fee').css('border-color', '#d63638');
                netRefund = 0;
            } else {
                $('#wrs_return_shipping_fee').css('border-color', '');
            }

            // UPDATE THE ACTUAL REFUND AMOUNT FIELD.
            // This is what gets sent to the gateway!
            $('#refund_amount').val(netRefund.toFixed(2));

            // Update our display.
            $('#wrs_net_refund').text(this.formatCurrency(netRefund));
            $('#wrs_original_amount').text(this.formatCurrency(this.originalRefundAmount));

            console.log('WRS: Updated amounts', {
                original: this.originalRefundAmount,
                fee: feeAmount,
                net: netRefund
            });
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

            $(document).ajaxSend((event, jqXHR, settings) => {
                if (settings.data && settings.data.indexOf('action=woocommerce_refund_line_items') !== -1) {
                    const applyFee = $('#wrs_apply_fee').is(':checked');
                    const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

                    settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                    settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');
                    settings.data += '&wrs_original_amount=' + encodeURIComponent(this.originalRefundAmount);

                    console.log('WRS: AJAX data added', { feeAmount, original: this.originalRefundAmount });
                }
            });
        },
    };

    WRS.init();
})(jQuery);
