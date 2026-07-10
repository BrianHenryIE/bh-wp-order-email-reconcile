<?php
/**
 * Example email-extraction pattern set for the development/test plugin.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Helper_Trait;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;

/**
 * Example pattern set. The helper trait supplies null/empty defaults for the optional regexes; only
 * the amount and order-id patterns are customised here.
 *
 * @see Email_Extract_Settings_Helper_Trait
 */
class Extraction_Settings implements Email_Extract_Settings_Interface {
	use Email_Extract_Settings_Helper_Trait;

	/**
	 * Regex to extract the amount paid from the email body.
	 *
	 * Used to match to the correct order.
	 *
	 * @return string
	 */
	public function get_amount_regex(): string {
		return '~\$(\d+\.\d{2})~';
	}

	/**
	 * Regex to find the customer's payment-platform id (e.g. Venmo handle / $CashTag).
	 *
	 * Matches the "Customer ID:" line produced by the dev admin page's "Send payment email".
	 */
	public function get_customer_id_regex(): ?string {
		return '~Customer ID: (\S+)~';
	}

	/**
	 * Regex to get the order id. E.g. if the customer was instructed to add it to notes, or if it is automatically
	 * contained in the payment receipt email.
	 */
	public function get_order_id_regex(): ?string {
		return '~order_id:\d+~';
	}
}
