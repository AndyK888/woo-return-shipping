/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * Adds return shipping fee as a line in the order items table.
 *
 * @package WooReturnShipping
 * @version 1.5.0
 */

(function ($) {
    'use strict';

    console.log('WRS: Script loaded v1.5.0');

    const WRS = {
        config: window.wrsAdmin || { defaultFee: 10.0, feeLabel: 'Return Shipping' },
        injected: false,

        init: function () {
            $(document).ready(this.setup.bind(this));
        },

        setup: function () {
            console.log('WRS: Setup');
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));
        },

        onRefundStart: function () {
            console.log('WRS: Refund started');
            setTimeout(() => this.injectRow(), 300);
        },

        onRefundCancel: function () {
            $('#wrs-return-shipping-row').hide();
            $('.wrs-summary-box').hide();
        },

        injectRow: function () {
            console.log('WRS: Injecting row');

            // Don't inject twice.
            if ($('#wrs-return-shipping-row').length) {
                $('#wrs-return-shipping-row').show();
                $('.wrs-summary-box').show();
                this.updateCalculation();
                return;
            }

            const defaultFee = parseFloat(this.config.defaultFee) || 10.00;
            const feeLabel = this.config.feeLabel || 'Return Shipping';

            // Create a row that looks like a shipping/fee row.
            const rowHtml = `
				<tr id="wrs-return-shipping-row" class="wrs-fee-row">
					<td class="thumb"><div></div></td>
					<td class="name">
						<div class="wrs-fee-label">
							<input type="checkbox" id="wrs_apply_fee" checked style="margin-right: 8px;">
							<label for="wrs_apply_fee"><strong>${this.escapeHtml(feeLabel)}</strong></label>
							<small style="color: #666; margin-left: 10px;">(deducted from refund)</small>
						</div>
					</td>
					<td class="item_cost" width="1%">&nbsp;</td>
					<td class="quantity" width="1%">&nbsp;</td>
					<td class="line_cost" width="1%">
						<div class="edit" style="display: block;">
							<input type="text" 
								id="wrs_fee_amount" 
								class="wrs-fee-input wc_input_price"
								value="-${defaultFee.toFixed(2)}"
								style="width: 80px; text-align: right;"
							/>
						</div>
					</td>
					<td class="wc-order-edit-line-item" width="1%">&nbsp;</td>
				</tr>
			`;

            // Find the order items table and insert after shipping or at end.
            const $shippingTable = $('#order_shipping_line_items');
            const $feesTable = $('#order_fee_line_items');

            if ($feesTable.length) {
                $feesTable.find('tbody').append(rowHtml);
                console.log('WRS: Added to fees table');
            } else if ($shippingTable.length) {
                $shippingTable.find('tbody').append(rowHtml);
                console.log('WRS: Added to shipping table');
            } else {
                // Fallback: add to main line items.
                $('#order_line_items tbody').append(rowHtml);
                console.log('WRS: Added to line items');
            }

            // Add summary box before refund actions.
            const summaryHtml = `
				<div class="wrs-summary-box" style="margin: 15px 0; padding: 12px 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<strong style="color: #155724;">Net Refund to Customer:</strong>
						<strong id="wrs_net_refund" style="font-size: 16px; color: #155724;">$0.00</strong>
					</div>
				</div>
			`;
            $('.refund-actions').before(summaryHtml);

            this.bindEvents();
            this.updateCalculation();
        },

        bindEvents: function () {
            $(document).off('.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee', () => {
                const checked = $('#wrs_apply_fee').is(':checked');
                $('#wrs_fee_amount').prop('disabled', !checked);
                if (!checked) {
                    $('#wrs_fee_amount').val('0.00');
                } else {
                    $('#wrs_fee_amount').val('-' + parseFloat(this.config.defaultFee).toFixed(2));
                }
                this.updateCalculation();
            });

            $(document).on('input.wrs change.wrs', '#wrs_fee_amount', () => {
                this.updateCalculation();
            });

            // Watch WC's refund amount field.
            $(document).on('change.wrs keyup.wrs', '#refund_amount', () => {
                this.updateCalculation();
            });

            // Watch for item quantity changes.
            $(document).on('change.wrs keyup.wrs', '.refund_order_item_qty, .refund_line_total', () => {
                setTimeout(() => this.updateCalculation(), 100);
            });

            // Intercept AJAX to modify refund amount.
            this.interceptAjax();
        },

        updateCalculation: function () {
            const wcAmount = this.parseAmount($('#refund_amount').val());
            const feeValue = this.parseAmount($('#wrs_fee_amount').val());
            const feeAmount = Math.abs(feeValue); // Make positive for display.

            let netRefund = wcAmount - feeAmount;
            if (netRefund < 0) netRefund = 0;

            $('#wrs_net_refund').text(this.formatCurrency(netRefund));

            console.log('WRS: Calculation', { wc: wcAmount, fee: feeAmount, net: netRefund });
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

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        interceptAjax: function () {
            if (this.ajaxIntercepted) return;
            this.ajaxIntercepted = true;

            $(document).ajaxSend((event, jqXHR, settings) => {
                if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) {
                    return;
                }

                const applyFee = $('#wrs_apply_fee').is(':checked');
                const feeAmount = applyFee ? Math.abs(this.parseAmount($('#wrs_fee_amount').val())) : 0;

                settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');

                if (feeAmount > 0) {
                    const match = settings.data.match(/refund_amount=([^&]*)/);
                    if (match) {
                        const originalAmount = parseFloat(match[1]) || 0;
                        const netAmount = Math.max(0, originalAmount - feeAmount).toFixed(2);

                        settings.data = settings.data.replace(
                            /refund_amount=[^&]*/,
                            'refund_amount=' + netAmount
                        );
                        settings.data += '&wrs_original_refund_amount=' + encodeURIComponent(originalAmount);

                        console.log('WRS: MODIFIED for gateway', { original: originalAmount, fee: feeAmount, net: netAmount });
                    }
                }
            });
        },
    };

    WRS.init();
})(jQuery);
