<?php
/**
 * Supplies the unpaid orders the library should try to reconcile.
 *
 * Each e-commerce integration (WooCommerce, GiveWP, ...) implements this interface. The core API
 * depends only on this interface and the Unpaid_Order model, never on a concrete integration.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;

interface Unpaid_Orders_Provider_Interface {

	/**
	 * Whether this provider's backing plugin is active and able to supply orders.
	 *
	 * Allows an aggregate provider to skip inactive integrations without fatal errors.
	 */
	public function is_available(): bool;

	/**
	 * The unpaid orders to attempt to reconcile against payment emails.
	 *
	 * @return Unpaid_Order[]
	 */
	public function get_unpaid_orders(): array;
}
