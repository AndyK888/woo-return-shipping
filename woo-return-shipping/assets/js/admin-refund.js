/**
 * WooCommerce Return Shipping - Admin Refund Modal Enhancement
 *
 * Injects return shipping fee controls into the WooCommerce refund modal
 * and dynamically updates the net refund display.
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
			showReasonField: false,
			i18n: {
				feeLabel: 'Return Shipping Fee',
				exemptLabel: 'Exempt from return fee',
				netRefund: 'Net Refund (after fee)',
			},
		},

		/**
		 * Cache DOM elements.
		 */
		elements: {
			feeRow: null,
			exemptRow: null,
			reasonRow: null,
			netRefundRow: null,
			feeInput: null,
			exemptCheckbox: null,
			refundAmount: null,
			netRefundDisplay: null,
		},

		/**
		 * Initialize the module.
		 */
		init: function () {
			// Wait for WooCommerce order meta boxes to be ready.
			$(document).ready(this.setup.bind(this));

			// Re-initialize when refund items are loaded via AJAX.
			$(document.body).on(
				'wc_backbone_modal_loaded',
				this.onModalLoad.bind(this)
			);
		},

		/**
		 * Initial setup - cache elements and bind events.
		 */
		setup: function () {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Cache frequently used DOM elements.
		 */
		cacheElements: function () {
			this.elements.feeRow = $('.wrs-return-shipping-row');
			this.elements.exemptRow = $('.wrs-exempt-row');
			this.elements.reasonRow = $('.wrs-reason-row');
			this.elements.netRefundRow = $('.wrs-net-refund-row');
			this.elements.feeInput = $('#wrs_return_shipping_fee');
			this.elements.exemptCheckbox = $('#wrs_exempt_fee');
			this.elements.netRefundDisplay = $('#wrs_net_refund_display');
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			// Show/hide our fields when refund button area becomes visible.
			$(document).on('click', '.refund-items', this.onRefundStart.bind(this));
			$(document).on('click', '.cancel-action', this.onRefundCancel.bind(this));

			// Update net refund when fee changes.
			$(document).on(
				'input change',
				'#wrs_return_shipping_fee',
				this.updateNetRefund.bind(this)
			);
			$(document).on(
				'change',
				'#wrs_exempt_fee',
				this.onExemptChange.bind(this)
			);

			// Watch for refund amount changes.
			$(document).on(
				'input change keyup',
				'#refund_amount',
				this.updateNetRefund.bind(this)
			);

			// Intercept refund submission to include our data.
			this.interceptRefundSubmission();
		},

		/**
		 * Handle refund start - show our fields.
		 */
		onRefundStart: function () {
			// Small delay to ensure WC has shown the refund UI.
			setTimeout(() => {
				this.cacheElements();
				this.showFeeFields();
				this.updateNetRefund();
			}, 100);
		},

		/**
		 * Handle refund cancel - hide our fields.
		 */
		onRefundCancel: function () {
			this.hideFeeFields();
		},

		/**
		 * Show the return shipping fee fields.
		 */
		showFeeFields: function () {
			this.elements.feeRow.show();
			this.elements.exemptRow.show();
			this.elements.netRefundRow.show();

			if (this.config.showReasonField) {
				this.elements.reasonRow.show();
			}
		},

		/**
		 * Hide the return shipping fee fields.
		 */
		hideFeeFields: function () {
			this.elements.feeRow.hide();
			this.elements.exemptRow.hide();
			this.elements.reasonRow.hide();
			this.elements.netRefundRow.hide();
		},

		/**
		 * Handle exempt checkbox change.
		 */
		onExemptChange: function () {
			const isExempt = this.elements.exemptCheckbox.is(':checked');

			if (isExempt) {
				this.elements.feeInput.prop('disabled', true).addClass('disabled');
				if (this.elements.reasonRow.length) {
					this.elements.reasonRow.hide();
				}
			} else {
				this.elements.feeInput.prop('disabled', false).removeClass('disabled');
				if (this.config.showReasonField && this.elements.reasonRow.length) {
					this.elements.reasonRow.show();
				}
			}

			this.updateNetRefund();
		},

		/**
		 * Update the net refund display.
		 */
		updateNetRefund: function () {
			// Re-cache in case elements changed.
			this.elements.refundAmount = $('#refund_amount');
			this.elements.feeInput = $('#wrs_return_shipping_fee');
			this.elements.exemptCheckbox = $('#wrs_exempt_fee');
			this.elements.netRefundDisplay = $('#wrs_net_refund_display');

			const refundAmount = this.parseAmount(this.elements.refundAmount.val());
			const feeAmount = this.elements.exemptCheckbox.is(':checked')
				? 0
				: this.parseAmount(this.elements.feeInput.val());

			let netRefund = refundAmount - feeAmount;

			// Prevent negative net refund.
			if (netRefund < 0) {
				netRefund = 0;
				this.elements.feeInput.addClass('wrs-error');
			} else {
				this.elements.feeInput.removeClass('wrs-error');
			}

			// Format and display.
			const formatted = this.formatCurrency(netRefund);
			this.elements.netRefundDisplay.text(formatted);

			// Update styling based on fee impact.
			if (feeAmount > 0 && feeAmount < refundAmount) {
				this.elements.netRefundDisplay
					.removeClass('wrs-warning')
					.addClass('wrs-success');
			} else if (feeAmount >= refundAmount) {
				this.elements.netRefundDisplay
					.removeClass('wrs-success')
					.addClass('wrs-warning');
			} else {
				this.elements.netRefundDisplay.removeClass('wrs-success wrs-warning');
			}
		},

		/**
		 * Parse a currency amount string to float.
		 *
		 * @param {string} value Currency string.
		 * @return {number} Parsed amount.
		 */
		parseAmount: function (value) {
			if (!value) {
				return 0;
			}

			// Remove currency symbols and thousand separators.
			const cleaned = value
				.toString()
				.replace(/[^0-9.,\-]/g, '')
				.replace(',', '.');

			return parseFloat(cleaned) || 0;
		},

		/**
		 * Format a number as currency.
		 *
		 * @param {number} amount Amount to format.
		 * @return {string} Formatted currency string.
		 */
		formatCurrency: function (amount) {
			// Use WooCommerce accounting if available.
			if (
				typeof window.accounting !== 'undefined' &&
				typeof window.woocommerce_admin_meta_boxes !== 'undefined'
			) {
				const params = window.woocommerce_admin_meta_boxes;
				return window.accounting.formatMoney(amount, {
					symbol: params.currency_format_symbol,
					decimal: params.currency_format_decimal_sep,
					thousand: params.currency_format_thousand_sep,
					precision: params.currency_format_num_decimals,
					format: params.currency_format,
				});
			}

			// Fallback formatting.
			return '$' + amount.toFixed(2);
		},

		/**
		 * Intercept refund form submission to include our data.
		 */
		interceptRefundSubmission: function () {
			// Hook into the WooCommerce AJAX refund.
			$(document).ajaxSend((event, jqXHR, settings) => {
				// Check if this is a refund request.
				if (
					settings.url &&
					settings.url.indexOf('wc_ajax') !== -1 &&
					settings.data &&
					settings.data.indexOf('action=woocommerce_refund_line_items') !== -1
				) {
					// Add our fee data to the request.
					const feeAmount = this.elements.exemptCheckbox.is(':checked')
						? 0
						: this.parseAmount(this.elements.feeInput.val());

					const isExempt = this.elements.exemptCheckbox.is(':checked')
						? 1
						: 0;

					const reason = $('#wrs_fee_reason').val() || '';

					settings.data +=
						'&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
					settings.data += '&wrs_exempt_fee=' + encodeURIComponent(isExempt);
					settings.data += '&wrs_fee_reason=' + encodeURIComponent(reason);
				}
			});
		},

		/**
		 * Handle modal load events.
		 */
		onModalLoad: function () {
			this.setup();
		},
	};

	// Initialize.
	WRS.init();
})(jQuery);
