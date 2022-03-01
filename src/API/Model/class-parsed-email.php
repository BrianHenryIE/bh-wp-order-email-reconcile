<?php
/**
 * The information extracted from the email using the regex patterns.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wc-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */

namespace BrianHenryIE\WC_Order_Email_Reconcile\API\Model;

use BrianHenryIE\WP_Mailboxes\BH_Email;

class Parsed_Email {

	protected BH_Email $original_email;

	protected ?string $amount          = null;
	protected ?string $customer_id     = null;
	protected ?string $customer_email  = null;
	protected ?string $customer_name   = null;
	protected ?string $transaction_id  = null;
	protected ?string $transaction_url = null;

	/** @var string[] */
	protected ?array $notes = array();

	/**
	 * A lazy init to bridge between the old and new ways.
	 *
	 * Parsed_Email constructor.
	 *
	 * @param string[] $email_properties
	 */
	public function __construct( array $email_properties, BH_Email $original_email ) {

		foreach ( $email_properties as $key => $value ) {

			$this->$key = $value;
		}
		$this->original_email = $original_email;

	}

	public function get_bh_email(): BH_Email {
		return $this->original_email;
	}

	/**
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
	 * @param ?string $amount
	 */
	public function set_amount( ?string $amount ): void {
		$this->amount = $amount;
	}

	/**
	 * @return ?string
	 */
	public function get_customer_id(): ?string {
		if ( is_null( $this->customer_id ) ) {
			return null;
		}
		return strtolower( $this->customer_id );
	}

	/**
	 * @param string|null $customer_id
	 */
	public function set_customer_id( ?string $customer_id ): void {
		$this->customer_id = $customer_id;
	}

	/**
	 * @return string|null
	 */
	public function get_customer_email(): ?string {
		return $this->customer_email;
	}

	/**
	 * @param string|null $customer_email
	 */
	public function set_customer_email( ?string $customer_email ): void {
		$this->customer_email = $customer_email;
	}

	/**
	 * @return string|null
	 */
	public function get_customer_name(): ?string {
		return $this->customer_name;
	}

	/**
	 * @param string|null $customer_name
	 */
	public function set_customer_name( ?string $customer_name ): void {
		$this->customer_name = $customer_name;
	}

	/**
	 * @return string|null
	 */
	public function get_transaction_id(): ?string {
		return $this->transaction_id;
	}

	/**
	 * @param string|null $transaction_id
	 */
	public function set_transaction_id( ?string $transaction_id ): void {
		$this->transaction_id = $transaction_id;
	}

	/**
	 * @return string|null
	 */
	public function get_transaction_url(): ?string {
		return $this->transaction_url;
	}

	/**
	 * @param string|null $transaction_url
	 */
	public function set_transaction_url( ?string $transaction_url ): void {
		$this->transaction_url = $transaction_url;
	}

	/**
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
	 * @param string[] $notes
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
