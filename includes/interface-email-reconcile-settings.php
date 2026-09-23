<?php
/**
 * The required settings for the library to work.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile;

use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes_Settings_Interface;

interface Email_Reconcile_Settings_Interface extends BH_WP_Mailboxes_Settings_Interface {

	/**
	 * The plugin's name is used when saving options. e.g. my-plugin-slug-last-run.
	 *
	 * @return string The plugin's name.
	 */
	public function get_plugin_slug(): string;

	/**
	 * When searching for orders to reconcile, include orders placed using these payment gateway ids.
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids(): array;

	/**
	 * Rules (sets of regex patterns) for extracting the data from the emails.
	 *
	 * @return Email_Extract_Settings_Interface[]
	 */
	public function get_patterns(): array;

	/**
	 * If a customer has a known id, e.g. Venmo username, $CashTag, it should be saved in the order meta, then
	 * the meta key name provided here to help match the order.
	 *
	 * @return ?string
	 */
	public function get_customer_payment_id_meta_key(): ?string;
}
