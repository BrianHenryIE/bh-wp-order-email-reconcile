<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;

/**
 * @see Email_Extract_Settings_Helper_Trait
 */
class Extraction_Settings implements Email_Extract_Settings_Interface {

	/**
	 * Regex to extract the amount paid from the email body.
	 *
	 * Used to match to the correct order.
	 *
	 * @return string
	 */
	public function get_amount_regex(): string {
		return '~\$\d+\.\d{2}~';
	}

	/**
	 * Regex to get the customer's email address, for matching.
	 *
	 * @return string
	 */
	public function get_customer_email_regex(): ?string {
		return null;
	}

	/**
	 * Regex to get the customer's actual name, for notes.
	 */
	public function get_customer_name_regex(): ?string {
		return null;
	}

	/**
	 * Regex to find Venmo handle/ CashTag, etc.
	 *
	 * @return ?string
	 */
	public function get_customer_id_regex(): ?string {
		return null;
	}

	/**
	 * Regex to extract any messages added to the payment, for notes.
	 *
	 * An associative array with the note title as the key.
	 *
	 * @return string[]
	 */
	public function get_notes_array_regex(): array {
		return array();
	}

	/**
	 * Regex to extract the transaction id according to the payment processor.
	 *
	 * @return ?string
	 */
	public function get_transaction_id_regex(): ?string {
		return null;
	}

	/**
	 * Regex to extract URL to payment processor website for this transaction.
	 *
	 * Will be used on the wp-admin order screen and in the order note.
	 *
	 * @return ?string
	 */
	public function get_transaction_url_regex(): ?string {
		return null;
	}

	/**
	 * Regex to get the order id. E.g. if the customer was instructed to add it to notes, or if it is automatically
	 * contained in the payment receipt email.
	 */
	public function get_order_id_regex(): ?string {
		return '~order_id:\d+~';
	}
}
