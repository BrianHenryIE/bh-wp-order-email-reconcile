<?php
/**
 * The information extracted from the email using the regex patterns.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API\Model;

use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;

/**
 * Value object holding the payment values extracted from one email.
 */
class Parsed_Email {

	/**
	 * The email this data was parsed from.
	 *
	 * @var BH_Email
	 */
	protected BH_Email $original_email;

	/**
	 * The payment amount, as a numeric string, or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $amount = null;

	/**
	 * The customer's payment-platform id (e.g. Venmo username), or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $customer_id = null;

	/**
	 * The customer's email address, or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $customer_email = null;

	/**
	 * The customer's name, or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $customer_name = null;

	/**
	 * The payment-platform transaction id, or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $transaction_id = null;

	/**
	 * A url to the transaction on the payment platform, or null if not found.
	 *
	 * @var ?string
	 */
	protected ?string $transaction_url = null;

	/**
	 * Free-text notes extracted from the email, keyed by note name.
	 *
	 * @var ?string[]
	 */
	protected ?array $notes = array();

	/**
	 * Populate the object from the array of values parsed out of the email.
	 *
	 * A lazy init to bridge between the old and new ways.
	 *
	 * @param array<string, mixed> $email_properties Parsed values keyed by property name.
	 * @param BH_Email             $original_email   The email the values were parsed from.
	 */
	public function __construct( array $email_properties, BH_Email $original_email ) {

		foreach ( $email_properties as $key => $value ) {

			$this->$key = $value;
		}
		$this->original_email = $original_email;
	}

	/**
	 * The email this data was parsed from.
	 *
	 * @return BH_Email
	 */
	public function get_bh_email(): BH_Email {
		return $this->original_email;
	}

	/**
	 * The payment amount.
	 *
	 * Will be null if not found in the email.
	 *
	 * Returning a string to match WC_Order return type.
	 *
	 * @see WC_Order::get_total()
	 *
	 * @return ?string
	 */
	public function get_amount(): ?string {
		return $this->amount;
	}

	/**
	 * Set the payment amount.
	 *
	 * @param ?string $amount The amount as a numeric string.
	 */
	public function set_amount( ?string $amount ): void {
		$this->amount = $amount;
	}

	/**
	 * The customer's payment-platform id, lowercased for case-insensitive matching.
	 *
	 * @return ?string
	 */
	public function get_customer_id(): ?string {
		if ( is_null( $this->customer_id ) ) {
			return null;
		}
		return strtolower( $this->customer_id );
	}

	/**
	 * Set the customer's payment-platform id.
	 *
	 * @param string|null $customer_id The id, e.g. Venmo username.
	 */
	public function set_customer_id( ?string $customer_id ): void {
		$this->customer_id = $customer_id;
	}

	/**
	 * The customer's email address.
	 *
	 * @return string|null
	 */
	public function get_customer_email(): ?string {
		return $this->customer_email;
	}

	/**
	 * Set the customer's email address.
	 *
	 * @param string|null $customer_email The email address.
	 */
	public function set_customer_email( ?string $customer_email ): void {
		$this->customer_email = $customer_email;
	}

	/**
	 * The customer's name.
	 *
	 * @return string|null
	 */
	public function get_customer_name(): ?string {
		return $this->customer_name;
	}

	/**
	 * Set the customer's name.
	 *
	 * @param string|null $customer_name The customer name.
	 */
	public function set_customer_name( ?string $customer_name ): void {
		$this->customer_name = $customer_name;
	}

	/**
	 * The payment-platform transaction id.
	 *
	 * @return string|null
	 */
	public function get_transaction_id(): ?string {
		return $this->transaction_id;
	}

	/**
	 * Set the payment-platform transaction id.
	 *
	 * @param string|null $transaction_id The transaction id.
	 */
	public function set_transaction_id( ?string $transaction_id ): void {
		$this->transaction_id = $transaction_id;
	}

	/**
	 * A url to the transaction on the payment platform.
	 *
	 * @return string|null
	 */
	public function get_transaction_url(): ?string {
		return $this->transaction_url;
	}

	/**
	 * Set the transaction url.
	 *
	 * @param string|null $transaction_url The transaction url.
	 */
	public function set_transaction_url( ?string $transaction_url ): void {
		$this->transaction_url = $transaction_url;
	}

	/**
	 * The notes to record on the order, including transaction id/url when present.
	 *
	 * @return string[]
	 */
	public function get_notes(): array {
		$data = array();
		if ( ! is_null( $this->notes ) ) {
			$data = array_merge( $data, $this->notes );
		}
		if ( ! is_null( $this->get_transaction_id() ) ) {
			$data['transaction_id'] = $this->get_transaction_id();
		}
		if ( ! is_null( $this->get_transaction_url() ) ) {
			$data['transaction_id_href'] = $this->get_transaction_url();
		}

		return $data;
	}

	/**
	 * Set the parsed notes.
	 *
	 * @param string[] $notes Notes keyed by note name.
	 */
	public function set_notes( ?array $notes ): void {
		$this->notes = $notes;
	}

	/**
	 * If the pattern had a 'note' regex in its 'notes' regex array, i.e. to pull the note
	 * that a customer can add to the transaction, this function finds all sequences of
	 * digits and returns them as potential order ids.
	 *
	 * @return int[] Possible order ids.
	 */
	public function get_order_id_from_notes(): array {
		if ( is_null( $this->notes ) ) {
			return array();
		}
		if ( ! isset( $this->notes['note'] ) ) {
			return array();
		}
		if ( 1 === preg_match_all( '/\d+/', $this->notes['note'], $output_array ) ) {
			return array_map( 'intval', $output_array[0] );
		}
		return array();
	}
}
