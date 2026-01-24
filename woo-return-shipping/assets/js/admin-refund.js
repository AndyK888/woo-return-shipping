/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Injects UI before refund action buttons using JavaScript.
 * Script is confirmed loading as of v1.9.0.
 *
 * @package WooReturnShipping
 * @version 2.0.0
 */

(function ($) {
    'use strict';

    console.log('WRS v2.0.0: Script loaded');

    var WRS = {
        config: window.wrsConfig || { defaultFee: 10.00, feeLabel: 'Return Shipping' },

        init: function () {
            console.log('WRS: init() with config:', this.config);
            var self = this;

            // When refund button is clicked, wait and then inject.
            $(document).on('click', '.refund-items', function () {
                console.log('WRS: Refund items clicked');
                // Wait for WC to show the refund UI.
                setTimeout(function () {
                    self.inject();
                }, 300);
            });

            // Hide on cancel.
            $(document).on('click', '.cancel-action', function () {
                console.log('WRS: Cancel clicked');
                $('#wrs-fee-container').remove();
            });
        },

        inject: function () {
            console.log('WRS: inject() starting');

            // Don't double-inject.
            if ($('#wrs-fee-container').length) {
                console.log('WRS: Already injected');
                return;
            }

            // Find the refund actions div.
            var $actions = $('.refund-actions');
            console.log('WRS: Found .refund-actions:', $actions.length);

            if (!$actions.length) {
                console.log('WRS: .refund-actions not found, retrying in 500ms');
                var self = this;
                setTimeout(function () { self.inject(); }, 500);
                return;
            }

            // Check if refund is active.
            var $refundAmount = $('#refund_amount');
            console.log('WRS: Found #refund_amount:', $refundAmount.length, 'visible:', $refundAmount.is(':visible'));

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
            console.log('WRS: UI injected before', $actions.get(0));

            this.bind();
            this.update();
        },

        bind: function () {
            var self = this;

            $(document).on('change', '#wrs_apply_fee', function () {
                $('#wrs_return_shipping_fee').prop('disabled', !$(this).is(':checked'));
                self.update();
            });

            $(document).on('input change', '#wrs_return_shipping_fee, #refund_amount', function () {
                self.update();
            });

            $(document).on('change', '.refund_order_item_qty, .refund_line_total', function () {
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

            console.log('WRS: Updated display', { gross: gross, fee: fee, net: net });
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
