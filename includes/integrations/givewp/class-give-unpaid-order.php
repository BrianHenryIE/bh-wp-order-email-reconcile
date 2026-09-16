<?php
/**
 * Adapts a GiveWP donation (Give_Payment) to the integration-agnostic Unpaid_Order interface.
 *
 * SCAFFOLD: the GiveWP method calls below follow the documented Give_Payment API but have not been
 * exercised. Verify against the installed GiveWP version and add wpunit coverage before relying on it.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\GiveWP;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use DateTimeImmutable;
use DateTimeInterface;
use Give_Payment;

/**
 * Wraps a GiveWP Give_Payment so the core only ever sees the Unpaid_Order interface.
 */
class Give_Unpaid_Order implements Unpaid_Order {

	/**
	 * The integration identifier recorded against reconciled donations.
	 *
	 * @var string
	 */
	const INTEGRATION = 'givewp';

	/**
	 * Wrap a GiveWP donation payment.
	 *
	 * @param Give_Payment $payment                      The wrapped GiveWP donation payment.
	 * @param ?string      $customer_payment_id_meta_key Donation meta key storing the customer's payment-platform id, or null.
	 */
	public function __construct(
		protected Give_Payment $payment,
		protected ?string $customer_payment_id_meta_key
	) {
	}

	/**
	 * The integration that owns this donation.
	 *
	 * @return string
	 */
	public function get_integration(): string {
		return self::INTEGRATION;
	}

	/**
	 * The GiveWP payment id.
	 *
	 * @return int
	 */
	public function get_order_id(): int {
		return (int) $this->payment->ID;
	}

	/**
	 * The donation total, as a numeric string for exact comparison.
	 *
	 * @return numeric-string
	 */
	public function get_amount(): string {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var numeric-string $total */
		$total = (string) $this->payment->total;
		return $total;
	}

	/**
	 * Whether the donation has already been paid.
	 *
	 * @return bool
	 */
	public function is_paid(): bool {
		return in_array( $this->payment->status, array( 'publish', 'complete' ), true );
	}

	/**
	 * The customer's payment-platform id from donation meta, lowercased, or null.
	 *
	 * @return ?string
	 */
	public function get_customer_payment_id(): ?string {
		if ( empty( $this->customer_payment_id_meta_key ) ) {
			return null;
		}
		$payment_id = give_get_meta( $this->payment->ID, $this->customer_payment_id_meta_key, true );
		if ( empty( $payment_id ) ) {
			return null;
		}
		return strtolower( (string) $payment_id );
	}

	/**
	 * The donor's email address, lowercased.
	 *
	 * @return string
	 */
	public function get_email_address(): string {
		return strtolower( (string) $this->payment->email );
	}

	/**
	 * The donor's name, lowercased.
	 *
	 * @return string[]
	 */
	public function get_customer_names(): array {
		$name = strtolower( trim( "{$this->payment->first_name} {$this->payment->last_name}" ) );
		return '' === $name ? array() : array( $name );
	}

	/**
	 * Record metadata against the donation.
	 *
	 * @param string $key   The meta key.
	 * @param string $value The meta value.
	 */
	public function add_meta( string $key, string $value ): void {
		give_update_meta( $this->payment->ID, $key, $value );
	}

	/**
	 * Add a human-readable note to the donation.
	 *
	 * @param string $note_html The note, which may contain HTML.
	 */
	public function add_note( string $note_html ): void {
		$this->payment->add_note( $note_html );
	}

	/**
	 * Mark the donation paid, recording the transaction id if provided.
	 *
	 * @param ?string $transaction_id The payment-platform transaction id.
	 */
	public function mark_paid( ?string $transaction_id ): void {
		if ( ! empty( $transaction_id ) ) {
			$this->payment->transaction_id = $transaction_id;
		}
		$this->payment->status = 'publish';
	}

	/**
	 * Persist pending changes to the donation.
	 */
	public function save(): void {
		$this->payment->save();
	}

	/**
	 * GiveWP stores donations as `give_payment` posts.
	 */
	public function get_post_type(): string {
		return 'give_payment';
	}

	/**
	 * When the donation was made.
	 */
	public function get_date_created(): ?DateTimeInterface {
		$date = $this->payment->date;
		if ( ! is_string( $date ) || '' === $date ) {
			return null;
		}
		try {
			return new DateTimeImmutable( $date, wp_timezone() );
		} catch ( \Exception ) {
			return null;
		}
	}

	/**
	 * The donor's name as entered.
	 */
	public function get_customer_display_name(): string {
		return trim( $this->payment->first_name . ' ' . $this->payment->last_name );
	}

	/**
	 * The donation's details screen in GiveWP's payment history.
	 */
	public function get_edit_url(): ?string {
		return admin_url( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-payment-details&id=' . $this->payment->ID );
	}
}
