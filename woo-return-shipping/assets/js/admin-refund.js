/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Shows/hides the fee row rendered by PHP and handles AJAX.
 *
 * @package WooReturnShipping
 * @version 1.9.0
 */

(function ($) {
    'use strict';

    console.log('WRS v1.9.0: Script loaded');

    var WRS = {
        init: function () {
            console.log('WRS: Initializing');

            // Show fee row when refund starts.
            $(document).on('click', '.refund-items', function () {
                console.log('WRS: Refund button clicked');
                $('.wrs-fee-row').show();
                WRS.update();
            });

            // Hide on cancel.
            $(document).on('click', '.cancel-action', function () {
                console.log('WRS: Cancel clicked');
                $('.wrs-fee-row').hide();
            });

            // Bind events.
            $(document).on('change', '#wrs_apply_fee', function () {
                $('#wrs_return_shipping_fee').prop('disabled', !$(this).is(':checked'));
                WRS.update();
            });

            $(document).on('input change', '#wrs_return_shipping_fee, #refund_amount', function () {
                WRS.update();
            });

            $(document).on('change', '.refund_order_item_qty, .refund_line_total', function () {
                setTimeout(WRS.update, 150);
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
        }
    };

    $(document).ready(function () {
        console.log('WRS: Document ready');
        WRS.init();
    });

})(jQuery);
