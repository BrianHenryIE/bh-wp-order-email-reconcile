<?php
/**
 * Adapts a WC_Order to the integration-agnostic Unpaid_Order interface.
 *
 * All WooCommerce-specific knowledge lives here; the core API and Email_Reconciler see only the
 * Unpaid_Order interface.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use DateTimeInterface;
use WC_Order;

/**
 * Wraps a WC_Order so the core only ever sees the Unpaid_Order interface.
 */
class WC_Unpaid_Order implements Unpaid_Order {

	/**
	 * The integration identifier recorded against reconciled orders.
	 *
	 * @var string
	 */
	const INTEGRATION = 'woocommerce';

	/**
	 * Wrap a WooCommerce order.
	 *
	 * @param WC_Order $order                       The wrapped WooCommerce order.
	 * @param ?string  $customer_payment_id_meta_key Order meta key storing the customer's payment-platform id (e.g. Venmo username), or null.
	 */
	public function __construct(
		protected WC_Order $order,
		protected ?string $customer_payment_id_meta_key
	) {
	}

	/**
	 * The integration that owns this order.
	 *
	 * @return string
	 */
	public function get_integration(): string {
		return self::INTEGRATION;
	}

	/**
	 * The WooCommerce order id.
	 *
	 * @return int
	 */
	public function get_order_id(): int {
		return $this->order->get_id();
	}

	/**
	 * The order total, as a numeric string for exact comparison.
	 *
	 * @return numeric-string
	 */
	public function get_amount(): string {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var numeric-string $total */
		$total = $this->order->get_total();
		return $total;
	}

	/**
	 * Whether the order has already been paid.
	 *
	 * @return bool
	 */
	public function is_paid(): bool {
		return $this->order->is_paid();
	}

	/**
	 * The customer's payment-platform id from order meta, lowercased, or null.
	 *
	 * @return ?string
	 */
	public function get_customer_payment_id(): ?string {
		if ( empty( $this->customer_payment_id_meta_key ) ) {
			return null;
		}
		$payment_id = $this->order->get_meta( $this->customer_payment_id_meta_key );
		if ( empty( $payment_id ) ) {
			return null;
		}
		return strtolower( (string) $payment_id );
	}

	/**
	 * The billing email address, lowercased.
	 *
	 * @return string
	 */
	public function get_email_address(): string {
		return strtolower( $this->order->get_billing_email() );
	}

	/**
	 * Billing and shipping names, lowercased and deduplicated, empty entries removed.
	 *
	 * @return string[]
	 */
	public function get_customer_names(): array {
		$names = array(
			strtolower( trim( "{$this->order->get_billing_first_name()} {$this->order->get_billing_last_name()}" ) ),
			strtolower( trim( "{$this->order->get_shipping_first_name()} {$this->order->get_shipping_last_name()}" ) ),
		);
		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * Record metadata against the order.
	 *
	 * @param string $key   The meta key.
	 * @param string $value The meta value.
	 */
	public function add_meta( string $key, string $value ): void {
		$this->order->add_meta_data( $key, $value );
	}

	/**
	 * Add a human-readable note to the order.
	 *
	 * @param string $note_html The note, which may contain HTML.
	 */
	public function add_note( string $note_html ): void {
		$this->order->add_order_note( $note_html );
	}

	/**
	 * Mark the order paid, recording the transaction id if provided.
	 *
	 * @param ?string $transaction_id The payment-platform transaction id.
	 */
	public function mark_paid( ?string $transaction_id ): void {
		$this->order->payment_complete( $transaction_id ?? '' );
	}

	/**
	 * Persist pending changes to the order.
	 */
	public function save(): void {
		$this->order->save();
	}

	/**
	 * `shop_order` (the order's type is also the post type it is stored as).
	 */
	public function get_post_type(): string {
		return $this->order->get_type();
	}

	/**
	 * When the order was placed.
	 */
	public function get_date_created(): ?DateTimeInterface {
		return $this->order->get_date_created();
	}

	/**
	 * The billing name as entered.
	 */
	public function get_customer_display_name(): string {
		return trim( $this->order->get_formatted_billing_full_name() );
	}

	/**
	 * The order's edit screen (HPOS or posts, per WooCommerce).
	 */
	public function get_edit_url(): ?string {
		return $this->order->get_edit_order_url();
	}

	/**
	 * Escape hatch for integration-specific code that legitimately needs the underlying order.
	 */
	public function get_wc_order(): WC_Order {
		return $this->order;
	}
}
