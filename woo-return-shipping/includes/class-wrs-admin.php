<?php
/**
 * WRS Admin Handler
 *
 * Adds inline JavaScript directly in the page to ensure it loads.
 *
 * @package WooReturnShipping
 * @version 1.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WRS_Admin
 */
class WRS_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		
		// Add inline JavaScript directly in the footer - more reliable than external file.
		add_action( 'admin_footer', array( __CLASS__, 'inline_javascript' ) );
	}

	/**
	 * Enqueue styles only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue_styles( string $hook_suffix ): void {
		if ( ! self::is_order_screen() ) {
			return;
		}

		wp_enqueue_style(
			'wrs-admin',
			WRS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			'1.8.0'
		);
	}

	/**
	 * Check if we're on an order edit screen.
	 *
	 * @return bool
	 */
	private static function is_order_screen(): bool {
		$screen = get_current_screen();
		
		if ( ! $screen ) {
			return false;
		}

		// Support classic and HPOS screen IDs.
		$valid_screens = array( 
			'shop_order', 
			'woocommerce_page_wc-orders',
		);

		if ( in_array( $screen->id, $valid_screens, true ) ) {
			return true;
		}

		// Classic post type check.
		if ( 'post' === $screen->base && 'shop_order' === $screen->post_type ) {
			return true;
		}

		return false;
	}

	/**
	 * Output inline JavaScript directly in admin footer.
	 * This bypasses any script loading/caching issues.
	 */
	public static function inline_javascript(): void {
		if ( ! self::is_order_screen() ) {
			return;
		}

		$default_fee = floatval( get_option( 'wrs_default_fee', '10.00' ) );
		$fee_label   = esc_js( get_option( 'wrs_fee_label', __( 'Return Shipping', 'woo-return-shipping' ) ) );
		?>
		<script type="text/javascript">
		(function($) {
			'use strict';

			console.log('WRS v1.8.0: Inline script executing');

			if (!$) {
				console.error('WRS: jQuery not found');
				return;
			}

			var WRS = {
				defaultFee: <?php echo esc_js( $default_fee ); ?>,
				feeLabel: '<?php echo $fee_label; ?>',
				injected: false,

				init: function() {
					console.log('WRS: init()');
					var self = this;
					
					$(document).on('click', '.refund-items', function() {
						console.log('WRS: Refund button clicked');
						setTimeout(function() { self.inject(); }, 500);
					});

					$(document).on('click', '.cancel-action', function() {
						$('.wrs-fee-ui').remove();
						self.injected = false;
					});
				},

				inject: function() {
					console.log('WRS: inject() called');

					if ($('.wrs-fee-ui').length) {
						console.log('WRS: Already exists');
						return;
					}

					var $actions = $('.refund-actions');
					if (!$actions.length) {
						console.log('WRS: .refund-actions not found');
						return;
					}

					var html = '<div class="wrs-fee-ui" style="margin:15px 0;padding:15px;background:#fffbeb;border:2px solid #f0d866;border-radius:6px;">' +
						'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">' +
							'<label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">' +
								'<input type="checkbox" id="wrs_apply_fee" name="wrs_apply_fee" value="1" checked style="width:18px;height:18px;">' +
								this.feeLabel +
							'</label>' +
							'<div style="display:flex;align-items:center;">' +
								'<span style="color:#c00;font-weight:bold;margin-right:4px;">−$</span>' +
								'<input type="number" id="wrs_return_shipping_fee" name="wrs_return_shipping_fee" value="' + this.defaultFee.toFixed(2) + '" step="0.01" min="0" style="width:80px;text-align:right;padding:5px;">' +
							'</div>' +
						'</div>' +
						'<div style="border-top:1px solid #e0d48d;padding-top:10px;">' +
							'<div style="display:flex;justify-content:space-between;margin-bottom:5px;">' +
								'<span style="color:#666;">Gross Refund:</span>' +
								'<span id="wrs_gross">$0.00</span>' +
							'</div>' +
							'<div style="display:flex;justify-content:space-between;">' +
								'<strong style="color:#155724;">Net to Customer:</strong>' +
								'<strong id="wrs_net" style="color:#155724;font-size:16px;">$0.00</strong>' +
							'</div>' +
						'</div>' +
						'<div style="margin-top:8px;font-size:11px;color:#666;">Gateway will receive the net amount.</div>' +
					'</div>';

					$actions.before(html);
					console.log('WRS: UI injected!');
					this.injected = true;
					this.bind();
					this.update();
				},

				bind: function() {
					var self = this;
					
					$(document).off('.wrs');

					$(document).on('change.wrs', '#wrs_apply_fee', function() {
						$('#wrs_return_shipping_fee').prop('disabled', !$(this).is(':checked'));
						self.update();
					});

					$(document).on('input.wrs change.wrs', '#wrs_return_shipping_fee, #refund_amount', function() {
						self.update();
					});

					$(document).on('change.wrs', '.refund_order_item_qty, .refund_line_total', function() {
						setTimeout(function() { self.update(); }, 150);
					});

					// Add data to AJAX
					$(document).ajaxSend(function(e, xhr, settings) {
						if (!settings.data || settings.data.indexOf('action=woocommerce_refund_line_items') === -1) return;
						
						var applyFee = $('#wrs_apply_fee').is(':checked') ? '1' : '0';
						var feeAmount = $('#wrs_return_shipping_fee').val() || '0';
						
						settings.data += '&wrs_apply_fee=' + applyFee;
						settings.data += '&wrs_return_shipping_fee=' + encodeURIComponent(feeAmount);
						
						console.log('WRS: Added to AJAX', {applyFee: applyFee, feeAmount: feeAmount});
					});
				},

				update: function() {
					var gross = parseFloat($('#refund_amount').val()) || 0;
					var applyFee = $('#wrs_apply_fee').is(':checked');
					var fee = applyFee ? (parseFloat($('#wrs_return_shipping_fee').val()) || 0) : 0;
					var net = Math.max(0, gross - fee);

					$('#wrs_gross').text('$' + gross.toFixed(2));
					$('#wrs_net').text('$' + net.toFixed(2));
				}
			};

			$(document).ready(function() {
				console.log('WRS: Document ready, initializing');
				WRS.init();
			});

		})(jQuery);
		</script>
		<?php
	}
}
