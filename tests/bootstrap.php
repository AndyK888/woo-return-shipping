<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, ?string $domain = null ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, ?string $domain = null ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $text ): string {
		return trim( $text );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( string $text ): string {
		return stripslashes( $text );
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	function wc_price( float $amount ): string {
		return '$' . number_format( $amount, 2, '.', ',' );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( string $hook_name ): int {
		return 0;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $GLOBALS['wrs_test_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, $value ): void {
		$GLOBALS['wrs_test_options'][ $option ] = $value;
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( int $order_id ) {
		return $GLOBALS['wrs_test_orders'][ $order_id ] ?? null;
	}
}

if ( ! class_exists( 'WC_Order_Item_Fee' ) ) {
	class WC_Order_Item_Fee {
		private int $id = 0;
		private string $name = '';
		private float $amount = 0.0;
		private float $total = 0.0;
		private string $tax_status = 'none';
		private array $meta = array();

		public function __construct( ?self $item = null ) {
			if ( ! $item instanceof self ) {
				return;
			}

			$this->id         = $item->get_id();
			$this->name       = $item->get_name();
			$this->amount     = $item->get_amount();
			$this->total      = $item->get_total();
			$this->tax_status = $item->get_tax_status();
			$this->meta       = $item->meta;
		}

		public function set_id( int $id ): void {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function set_name( string $name ): void {
			$this->name = $name;
		}

		public function get_name(): string {
			return $this->name;
		}

		public function set_amount( float $amount ): void {
			$this->amount = $amount;
		}

		public function get_amount(): float {
			return $this->amount;
		}

		public function set_total( float $total ): void {
			$this->total = $total;
		}

		public function get_total(): float {
			return $this->total;
		}

		public function set_tax_status( string $tax_status ): void {
			$this->tax_status = $tax_status;
		}

		public function get_tax_status(): string {
			return $this->tax_status;
		}

		public function add_meta_data( string $key, $value, bool $unique = false ): void {
			$this->meta[ $key ] = $value;
		}

		public function get_meta( string $key ) {
			return $this->meta[ $key ] ?? '';
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		private int $id = 0;
		private array $items = array();
		private array $notes = array();

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function add_item( WC_Order_Item_Fee $item ): void {
			if ( 0 === $item->get_id() ) {
				$item->set_id( count( $this->items['fee'] ?? array() ) + 1 );
			}

			$this->items['fee'][ $item->get_id() ] = $item;
		}

		public function get_items( string $type = '' ): array {
			if ( '' === $type ) {
				return $this->items;
			}

			return $this->items[ $type ] ?? array();
		}

		public function add_order_note( string $note ): void {
			$this->notes[] = $note;
		}

		public function get_order_notes(): array {
			return $this->notes;
		}

		public function save(): void {}
	}
}

if ( ! class_exists( 'WC_Order_Refund' ) ) {
	class WC_Order_Refund {
		private int $id = 0;
		private int $parent_id = 0;
		private float $amount = 0.0;
		private float $total = 0.0;
		private array $meta = array();
		private array $items = array();

		public function __construct( float $amount = 0.0, int $id = 0 ) {
			$this->amount = $amount;
			$this->id     = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function set_id( int $id ): void {
			$this->id = $id;
		}

		public function set_parent_id( int $parent_id ): void {
			$this->parent_id = $parent_id;
		}

		public function get_parent_id(): int {
			return $this->parent_id;
		}

		public function get_amount(): float {
			return $this->amount;
		}

		public function set_amount( float $amount ): void {
			$this->amount = $amount;
		}

		public function set_total( float $total ): void {
			$this->total = $total;
		}

		public function get_total(): float {
			return $this->total;
		}

		public function add_meta_data( string $key, $value, bool $unique = false ): void {
			$this->meta[ $key ] = $value;
		}

		public function get_meta( string $key ) {
			return $this->meta[ $key ] ?? '';
		}

		public function add_item( WC_Order_Item_Fee $item ): void {
			$this->items['fee'][] = $item;
		}

		public function get_items( string $type = '' ): array {
			if ( '' === $type ) {
				return $this->items;
			}

			return $this->items[ $type ] ?? array();
		}
	}
}

require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-deduction-validator.php';
require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-fee-factory.php';
require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-email-deductions.php';
require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-checkout-fee.php';
require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-email.php';
require_once dirname( __DIR__ ) . '/woo-return-shipping/includes/class-wrs-refund-handler.php';
