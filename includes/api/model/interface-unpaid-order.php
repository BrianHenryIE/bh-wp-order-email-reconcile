<?php
/**
 * An order/donation awaiting payment, abstracted away from any specific e-commerce plugin.
 *
 * The core API and Email_Reconciler operate only on this interface; they have no knowledge of
 * WooCommerce, GiveWP, or any other integration. Each integration provides a concrete
 * implementation (e.g. WC_Unpaid_Order wrapping a WC_Order).
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API\Model;

use DateTimeInterface;

/**
 * Read the data needed to match a payment email to this order, and mutate the order when a
 * matching payment email is found.
 */
interface Unpaid_Order {

	/**
	 * Identifies which integration created this order, e.g. "woocommerce", "givewp".
	 *
	 * Used so a reconciled order can be matched back to the integration that owns it.
	 */
	public function get_integration(): string;

	/**
	 * The integration's unique numeric identifier for this order (e.g. WooCommerce order id).
	 *
	 * Used both as an index key and to match an order id parsed from a payment note.
	 */
	public function get_order_id(): int;

	/**
	 * The WordPress post type the order is stored as, e.g. `shop_order`, `give_payment`.
	 *
	 * Log messages reference the order as `` `{post_type}:{id}` ``, which bh-wp-logger turns into a
	 * link to the order.
	 */
	public function get_post_type(): string;

	/**
	 * The amount outstanding on the order, as a numeric string for exact comparison with the
	 * amount parsed from the payment email.
	 *
	 * @see \WC_Order::get_total()
	 *
	 * @return numeric-string
	 */
	public function get_amount(): string;

	/**
	 * Whether the order has already been paid. A paid order is never reconciled again.
	 */
	public function is_paid(): bool;

	/**
	 * When the order was placed, for display; null if the integration does not record it.
	 */
	public function get_date_created(): ?DateTimeInterface;

	/**
	 * The customer's name as entered, for display (unlike {@see get_customer_names()}, which is
	 * lowercased variants for matching). Empty string when unknown.
	 */
	public function get_customer_display_name(): string;

	/**
	 * Admin URL of the order's edit/view screen, or null if there is none.
	 */
	public function get_edit_url(): ?string;

	/**
	 * The customer's id on the payment platform, e.g. Venmo username or $CashTag, if it was
	 * captured at checkout and stored on the order. Returned lowercased for case-insensitive matching.
	 */
	public function get_customer_payment_id(): ?string;

	/**
	 * The customer's email address, lowercased, for matching against the payment email.
	 */
	public function get_email_address(): string;

	/**
	 * All known lowercased name variants for the customer (e.g. billing and shipping names),
	 * each used as an index key for name-based matching.
	 *
	 * @return string[]
	 */
	public function get_customer_names(): array;

	/**
	 * Record a piece of metadata against the order, e.g. the payment processor transaction url.
	 *
	 * @param string $key   The meta key.
	 * @param string $value The meta value.
	 */
	public function add_meta( string $key, string $value ): void;

	/**
	 * Add a human-readable note to the order, e.g. the auto-reconciliation summary. May contain HTML.
	 *
	 * @param string $note_html The note, which may contain HTML.
	 */
	public function add_note( string $note_html ): void;

	/**
	 * Mark the order as paid, optionally recording the payment processor's transaction id.
	 *
	 * @see \WC_Order::payment_complete()
	 *
	 * @param ?string $transaction_id The payment processor's transaction id, if known.
	 */
	public function mark_paid( ?string $transaction_id ): void;

	/**
	 * Persist any pending changes made via add_meta()/add_note()/mark_paid().
	 */
	public function save(): void;
}
