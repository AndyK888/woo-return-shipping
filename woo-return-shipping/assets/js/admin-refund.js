/**
 * WooCommerce Return Shipping - Admin Refund Modal Enhancement
 *
 * Injects return shipping fee controls into the WooCommerce refund modal.
 * The fee is shown as a line with a checkbox - when checked, fee is applied.
 *
 * @package WooReturnShipping
 */

(function ($) {
    'use strict';

    const WRS = {
        /**
         * Configuration from localized script.
         */
        config: window.wrsAdmin || {
            defaultFee: 10.0,
            feeLabel: 'Return Shipping',
            currencySymbol: '$',
            currencyPosition: 'left',
            showReasonField: false,
        },

        /**
         * Whether the UI has been injected.
         */
        injected: false,

        /**
         * Initialize the module.
         */
        init: function () {
            $(document).ready(this.setup.bind(this));
        },

        /**
         * Initial setup - watch for refund button click.
         */
        setup: function () {
            // Watch for the refund items button click
            $(document).on('click', '.refund-items', this.onRefundStart.bind(this));
            $(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));

            // Also check if refund UI is already visible (page reload)
            this.checkAndInject();
        },

        /**
         * Check if refund UI is visible and inject if needed.
         */
        checkAndInject: function () {
            if ($('.wc-order-refund-items').is(':visible')) {
                this.injectUI();
            }
        },

        /**
         * Handle refund start - inject our UI.
         */
        onRefundStart: function () {
            // Small delay to let WC show the refund UI
            setTimeout(() => {
                this.injectUI();
            }, 150);
        },

        /**
         * Handle refund cancel - remove our UI.
         */
        onRefundCancel: function () {
            this.removeUI();
            this.injected = false;
        },

        /**
         * Inject the return shipping fee UI into the refund area.
         */
        injectUI: function () {
            if (this.injected) {
                $('.wrs-container').show();
                return;
            }

            // Find the refund amount row - this is where we want to insert
            const $refundRow = $('.wc-order-refund-items tr:has(#refund_amount)');

            if (!$refundRow.length) {
                // Try alternative selector for different WC versions
                const $refundAmount = $('#refund_amount');
                if (!$refundAmount.length) {
                    console.log('WRS: Could not find refund amount field');
                    return;
                }
            }

            // Create our UI HTML
            const html = this.buildUI();

            // Insert before the refund amount row or after reason row
            const $reasonRow = $('.wc-order-refund-items tr:contains("Reason for refund")');
            if ($reasonRow.length) {
                $reasonRow.after(html);
            } else {
                // Insert in the refund totals area
                const $refundTotals = $('.wc-order-refund-items .wc-order-totals');
                if ($refundTotals.length) {
                    $refundTotals.find('tr:last').before(html);
                } else {
                    // Fallback: insert before refund buttons
                    $('.refund-actions').before('<div class="wrs-container-wrapper">' + html + '</div>');
                }
            }

            this.injected = true;
            this.bindEvents();
            this.updateNetRefund();
        },

        /**
         * Build the UI HTML.
         */
        buildUI: function () {
            const defaultFee = parseFloat(this.config.defaultFee) || 10.00;
            const feeLabel = this.config.feeLabel || 'Return Shipping';

            return `
				<tr class="wrs-container wrs-fee-row">
					<td class="label">
						<label>
							<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked />
							${this.escapeHtml(feeLabel)}
						</label>
					</td>
					<td class="total">
						<span class="wrs-fee-display">
							-<input type="number" 
								id="wrs_return_shipping_fee" 
								name="wrs_return_shipping_fee" 
								class="wc_input_price" 
								step="0.01" 
								min="0" 
								value="${defaultFee.toFixed(2)}"
								style="width: 80px; text-align: right;"
							/>
						</span>
					</td>
				</tr>
				<tr class="wrs-container wrs-net-row">
					<td class="label">
						<strong>Net Refund Amount:</strong>
					</td>
					<td class="total">
						<strong><span id="wrs_net_refund">-</span></strong>
					</td>
				</tr>
			`;
        },

        /**
         * Remove the UI.
         */
        removeUI: function () {
            $('.wrs-container').hide();
            $('.wrs-container-wrapper').hide();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function () {
            // Toggle fee application
            $(document).off('change.wrs', '#wrs_apply_fee');
            $(document).on('change.wrs', '#wrs_apply_fee', this.onApplyFeeChange.bind(this));

            // Update on fee amount change
            $(document).off('input.wrs change.wrs', '#wrs_return_shipping_fee');
            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee', this.updateNetRefund.bind(this));

            // Update on refund amount change
            $(document).off('input.wrs change.wrs keyup.wrs', '#refund_amount');
            $(document).on('input.wrs change.wrs keyup.wrs', '#refund_amount', this.updateNetRefund.bind(this));

            // Intercept AJAX submission
            this.interceptAjax();
        },

        /**
         * Handle apply fee checkbox change.
         */
        onApplyFeeChange: function () {
            const checked = $('#wrs_apply_fee').is(':checked');
            $('#wrs_return_shipping_fee').prop('disabled', !checked);

            if (!checked) {
                $('.wrs-fee-row .wrs-fee-display').addClass('wrs-disabled');
            } else {
                $('.wrs-fee-row .wrs-fee-display').removeClass('wrs-disabled');
            }

            this.updateNetRefund();
        },

        /**
         * Update the net refund display.
         */
        updateNetRefund: function () {
            const refundAmount = this.parseAmount($('#refund_amount').val());
            const applyFee = $('#wrs_apply_fee').is(':checked');
            const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

            let netRefund = refundAmount - feeAmount;

            // Validate
            if (feeAmount > refundAmount) {
                $('#wrs_return_shipping_fee').addClass('wrs-error');
                netRefund = 0;
            } else {
                $('#wrs_return_shipping_fee').removeClass('wrs-error');
            }

            // Format and display
            const formatted = this.formatCurrency(netRefund);
            $('#wrs_net_refund').text(formatted);
        },

        /**
         * Parse currency string to float.
         */
        parseAmount: function (value) {
            if (!value) return 0;
            const cleaned = value.toString().replace(/[^0-9.,\-]/g, '').replace(',', '.');
            return parseFloat(cleaned) || 0;
        },

        /**
         * Format number as currency.
         */
        formatCurrency: function (amount) {
            if (typeof window.accounting !== 'undefined' && window.woocommerce_admin_meta_boxes) {
                const params = window.woocommerce_admin_meta_boxes;
                return window.accounting.formatMoney(amount, {
                    symbol: params.currency_format_symbol,
                    decimal: params.currency_format_decimal_sep,
                    thousand: params.currency_format_thousand_sep,
                    precision: params.currency_format_num_decimals,
                    format: params.currency_format,
                });
            }
            return '$' + amount.toFixed(2);
        },

        /**
         * Escape HTML for safe insertion.
         */
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Intercept the AJAX refund submission to include our data.
         */
        interceptAjax: function () {
            // Only set up once
            if (this.ajaxIntercepted) return;
            this.ajaxIntercepted = true;

            $(document).ajaxSend((event, jqXHR, settings) => {
                // Check if this is a refund request
                if (settings.data && settings.data.indexOf('action=woocommerce_refund_line_items') !== -1) {
                    const applyFee = $('#wrs_apply_fee').is(':checked');
                    const feeAmount = applyFee ? this.parseAmount($('#wrs_return_shipping_fee').val()) : 0;

                    // Add our data to the request
                    settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
                    settings.data += '&wrs_apply_fee=' + (applyFee ? '1' : '0');
                }
            });
        },
    };

    // Initialize
    WRS.init();
})(jQuery);
