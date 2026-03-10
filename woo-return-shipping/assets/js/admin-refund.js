/**
 * WooCommerce Return Shipping - Admin Refund Enhancement
 *
 * @package WooReturnShipping
 * @version 2.7.0
 */

(function ($) {
    'use strict';

    var WRS = {
        config: $.extend(true, {
            defaultFee: 10.00,
            feeLabel: 'Return Shipping',
            boxDamageDefaultFee: 0.00,
            boxDamageLabel: 'Retail Box Damage',
            messages: {
                combinedDeductionsExceedRefund: 'Combined refund deductions cannot exceed the refund amount.',
                invalidDeductionAmount: '%s amount must be a valid non-negative number.'
            }
        }, window.wrsConfig || {}),
        observing: false,

        init: function () {
            var self = this;
            var buttonSelectors = [
                '.refund-items',
                '.button.refund-items',
                '.wc-order-refund-items',
                'button.refund-items',
                '[data-action="refund"]',
                '.do-api-refund',
                '.do-manual-refund'
            ];

            $(document).on('click.wrs', buttonSelectors.join(', '), function () {
                setTimeout(function () {
                    self.tryInject();
                }, 400);
            });

            $(document).on('click.wrs', '.cancel-action, .wc-order-bulk-actions .cancel', function () {
                $('#wrs-fee-container').remove();
            });

            this.observeRefundPanel();

            setTimeout(function () {
                self.tryInject();
            }, 1000);
        },

        observeRefundPanel: function () {
            if (this.observing) {
                return;
            }

            this.observing = true;

            var self = this;
            var observer = new MutationObserver(function () {
                if ($('.refund-actions:visible').length && !$('#wrs-fee-container').length) {
                    self.tryInject();
                }
            });

            observer.observe(document.querySelector('#woocommerce-order-items') || document.body, {
                childList: true,
                subtree: true,
                attributes: true
            });
        },

        tryInject: function () {
            if ($('#wrs-fee-container').length) {
                return;
            }

            var $actions = $('.refund-actions:visible');

            if (!$actions.length) {
                $actions = $('.refund-actions');
            }

            if (!$actions.length) {
                return;
            }

            this.doInject($actions.first());
        },

        doInject: function ($actions) {
            var fee = parseFloat(this.config.defaultFee) || 10.00;
            var label = this.config.feeLabel || 'Return Shipping';
            var boxDamageFee = parseFloat(this.config.boxDamageDefaultFee) || 0.00;
            var boxDamageLabel = this.config.boxDamageLabel || 'Retail Box Damage';

            $actions.before(
                '' +
                '<div id="wrs-fee-container" style="margin: 15px 0; padding: 15px; background: #fffbeb; border: 2px solid #f0d866; border-radius: 6px;">' +
                    '<div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;">' +
                        '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                            '<label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer;">' +
                                '<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked style="width: 18px; height: 18px;">' +
                                this.escapeHtml(label) +
                            '</label>' +
                            '<div style="display: flex; align-items: center;">' +
                                '<span style="color: #c00; font-weight: bold; margin-right: 4px;">-$</span>' +
                                '<input type="number" id="wrs_return_shipping_fee" name="wrs_return_shipping_fee" value="' + fee.toFixed(2) + '" step="0.01" min="0" style="width: 80px; text-align: right; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">' +
                            '</div>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                            '<label style="display: flex; align-items: center; gap: 10px; font-weight: 600; cursor: pointer;">' +
                                '<input type="checkbox" id="wrs_apply_box_damage_fee" name="wrs_apply_box_damage_fee" value="1" style="width: 18px; height: 18px;">' +
                                this.escapeHtml(boxDamageLabel) +
                            '</label>' +
                            '<div style="display: flex; align-items: center;">' +
                                '<span style="color: #c00; font-weight: bold; margin-right: 4px;">-$</span>' +
                                '<input type="number" id="wrs_box_damage_fee" name="wrs_box_damage_fee" value="' + boxDamageFee.toFixed(2) + '" step="0.01" min="0" disabled style="width: 80px; text-align: right; padding: 5px; border: 1px solid #ccc; border-radius: 4px;">' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div id="wrs-validation-error" style="display: none; margin-bottom: 12px; padding: 10px 12px; border-left: 4px solid #d63638; background: #fcf0f1; color: #8a2424;"></div>' +
                    '<div style="border-top: 1px solid #e0d48d; padding-top: 10px;">' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">' +
                            '<span style="color: #666;">Gross Refund:</span>' +
                            '<span id="wrs_gross">$0.00</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">' +
                            '<span style="color: #666;">Total Deductions:</span>' +
                            '<span id="wrs_total_deductions" style="color: #c00;">$0.00</span>' +
                        '</div>' +
                        '<div style="display: flex; justify-content: space-between;">' +
                            '<strong style="color: #155724;">Net to Customer:</strong>' +
                            '<strong id="wrs_net" style="color: #155724; font-size: 16px;">$0.00</strong>' +
                        '</div>' +
                    '</div>' +
                    '<div style="margin-top: 8px; font-size: 11px; color: #666;">This amount will be sent to the payment gateway.</div>' +
                '</div>'
            );

            this.bind();
            this.syncFeeInputStates();
            this.update();
        },

        bind: function () {
            var self = this;

            $(document).off('change.wrs', '#wrs_apply_fee, #wrs_apply_box_damage_fee');
            $(document).off('input.wrs change.wrs', '#wrs_return_shipping_fee, #wrs_box_damage_fee, #refund_amount');
            $(document).off('change.wrs', '.refund_order_item_qty, .refund_line_total');
            $(document).off('ajaxSend.wrs');
            $(document).off('ajaxSuccess.wrs');

            $(document).on('change.wrs', '#wrs_apply_fee, #wrs_apply_box_damage_fee', function () {
                self.syncFeeInputStates();
                self.update();
            });

            $(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee, #wrs_box_damage_fee, #refund_amount', function () {
                self.update();
            });

            $(document).on('change.wrs', '.refund_order_item_qty, .refund_line_total', function () {
                setTimeout(function () {
                    self.update();
                }, 150);
            });

            $(document).on('ajaxSend.wrs', function (event, xhr, settings) {
                if (!self.isRefundRequest(settings)) {
                    return;
                }

                var state = self.getState();

                self.renderValidation(state.validation);
                self.setButtonsBlocked(!state.validation.isValid);

                if (!state.validation.isValid) {
                    xhr.abort();
                    return;
                }

                settings.data += '&wrs_apply_fee=' + (state.returnShipping.enabled ? '1' : '0');
                settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(state.returnShipping.raw);
                settings.data += '&wrs_apply_box_damage_fee=' + (state.boxDamage.enabled ? '1' : '0');
                settings.data += '&wrs_box_damage_fee=' + encodeURIComponent(state.boxDamage.raw);
            });

            $(document).on('ajaxSuccess.wrs', function (event, xhr, settings, data) {
                if (!self.isRefundRequest(settings)) {
                    return;
                }

                var response = data || xhr.responseJSON;

                if (response && response.success === false && response.data && response.data.error) {
                    self.showValidationError(response.data.error);
                    self.setButtonsBlocked(true);
                    return;
                }

                self.update();
            });
        },

        isRefundRequest: function (settings) {
            return settings && typeof settings.data === 'string' && settings.data.indexOf('action=woocommerce_refund_line_items') !== -1;
        },

        syncFeeInputStates: function () {
            $('#wrs_return_shipping_fee').prop('disabled', !$('#wrs_apply_fee').is(':checked'));
            $('#wrs_box_damage_fee').prop('disabled', !$('#wrs_apply_box_damage_fee').is(':checked'));
        },

        getAmountState: function (checkboxSelector, inputSelector, label) {
            var enabled = $(checkboxSelector).is(':checked');
            var rawValue = $.trim($(inputSelector).val() || '');

            if (!enabled) {
                return {
                    enabled: false,
                    raw: '0',
                    amount: 0,
                    isValid: true,
                    errorMessage: ''
                };
            }

            if (rawValue === '') {
                return {
                    enabled: true,
                    raw: '0',
                    amount: 0,
                    isValid: true,
                    errorMessage: ''
                };
            }

            var amount = Number(rawValue);

            if (!isFinite(amount) || amount < 0) {
                return {
                    enabled: true,
                    raw: rawValue,
                    amount: 0,
                    isValid: false,
                    errorMessage: this.formatTemplate(this.config.messages.invalidDeductionAmount, label)
                };
            }

            return {
                enabled: true,
                raw: rawValue,
                amount: amount,
                isValid: true,
                errorMessage: ''
            };
        },

        getState: function () {
            var gross = Number($.trim($('#refund_amount').val() || '0'));

            if (!isFinite(gross) || gross < 0) {
                gross = 0;
            }

            var returnShipping = this.getAmountState('#wrs_apply_fee', '#wrs_return_shipping_fee', this.config.feeLabel || 'Return Shipping');
            var boxDamage = this.getAmountState('#wrs_apply_box_damage_fee', '#wrs_box_damage_fee', this.config.boxDamageLabel || 'Retail Box Damage');
            var validation = this.validateState(gross, returnShipping, boxDamage);

            return {
                gross: gross,
                totalDeductions: returnShipping.amount + boxDamage.amount,
                displayNet: Math.max(0, gross - (returnShipping.amount + boxDamage.amount)),
                validation: validation,
                returnShipping: returnShipping,
                boxDamage: boxDamage
            };
        },

        validateState: function (gross, returnShipping, boxDamage) {
            var totalDeductions = returnShipping.amount + boxDamage.amount;

            if (!returnShipping.isValid) {
                return {
                    isValid: false,
                    message: returnShipping.errorMessage
                };
            }

            if (!boxDamage.isValid) {
                return {
                    isValid: false,
                    message: boxDamage.errorMessage
                };
            }

            if (totalDeductions > gross) {
                return {
                    isValid: false,
                    message: this.config.messages.combinedDeductionsExceedRefund
                };
            }

            return {
                isValid: true,
                message: ''
            };
        },

        update: function () {
            var state = this.getState();

            $('#wrs_gross').text(this.formatMoney(state.gross));
            $('#wrs_total_deductions').text(this.formatMoney(state.totalDeductions));
            $('#wrs_net').text(this.formatMoney(state.displayNet));

            this.renderValidation(state.validation);
            this.setButtonsBlocked(!state.validation.isValid);
            this.updateButtonLabels(state.gross, state.totalDeductions, state.validation.isValid);
        },

        renderValidation: function (validation) {
            if (validation.isValid) {
                this.clearValidationError();
                return;
            }

            this.showValidationError(validation.message);
        },

        showValidationError: function (message) {
            $('#wrs-validation-error').text(message).show();
        },

        clearValidationError: function () {
            $('#wrs-validation-error').hide().text('');
        },

        setButtonsBlocked: function (isBlocked) {
            var disabledValue = isBlocked ? 'true' : 'false';

            $('.refund-actions .button, .refund-actions button, .refund-actions a.button').each(function () {
                var $button = $(this);
                var isAnchor = $button.is('a');

                $button.toggleClass('disabled', isBlocked);
                $button.attr('aria-disabled', disabledValue);

                if (isAnchor) {
                    $button.css('pointer-events', isBlocked ? 'none' : '');
                    $button.attr('tabindex', isBlocked ? '-1' : '0');
                    return;
                }

                $button.prop('disabled', isBlocked);
            });
        },

        updateButtonLabels: function (gross, totalDeductions, isValid) {
            var $buttons = $('.refund-actions .button, .refund-actions button, .refund-actions a.button');

            if (!$buttons.length) {
                return;
            }

            var self = this;
            var targetAmount = isValid && totalDeductions > 0 ? Math.max(0, gross - totalDeductions) : gross;
            var formattedAmount = this.formatMoney(targetAmount);
            var formattedNumber = this.formatNumber(targetAmount);

            $buttons.each(function () {
                var $button = $(this);
                var currentLabel = $button.text();
                var templateLabel = $button.data('wrs-label-template');
                var lastWrittenLabel = $button.data('wrs-last-written-label');

                if (!currentLabel) {
                    return;
                }

                if (!templateLabel || (lastWrittenLabel && currentLabel !== lastWrittenLabel)) {
                    templateLabel = currentLabel;
                    $button.data('wrs-label-template', templateLabel);
                }

                var updatedLabel = self.replaceAmountInLabel(templateLabel, formattedAmount, formattedNumber);

                if (!updatedLabel) {
                    return;
                }

                if (updatedLabel !== currentLabel) {
                    $button.text(updatedLabel);
                }

                $button.data('wrs-last-written-label', updatedLabel);
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

            if (new RegExp(amountPattern).test(label)) {
                return label.replace(new RegExp(amountPattern), formattedNumber);
            }

            return label;
        },

        formatTemplate: function (template, value) {
            return String(template || '').replace('%s', value);
        },

        escapeHtml: function (str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    $(function () {
        WRS.init();
    });
})(jQuery);
