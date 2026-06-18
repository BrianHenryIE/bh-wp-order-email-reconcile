<?php
/**
 * A convenience class with a static `::make()` method.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile;

use BrianHenryIE\WP_Order_Email_Reconcile\API\API;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler;
use BrianHenryIE\WP_Order_Email_Reconcile\API\WC_Unpaid_Orders;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BH_WP_Order_Email_Reconcile extends API {

	public static function make(
		Email_Reconcile_Settings_Interface $settings,
		?LoggerInterface $logger = null
	): BH_WP_Order_Email_Reconcile {

		$logger ??= new NullLogger();

		// BH WP Mailboxes cron job should be disabled, this library should register a cron job when a relevent
		// order is created and unregister it when there are no unpaid orders.
		$bh_wp_mailboxes = BH_WP_Mailboxes::make( $settings, $logger );

		$unpaid_orders_service = new WC_Unpaid_Orders(
			$settings,
			$logger
		);
		$email_reconciler_service = new Email_Reconciler(
			$settings,
			$logger
		);

		$order_email_reconcile = new self(
			$settings,
			$unpaid_orders_service,
			$email_reconciler_service,
			$logger
		);
		// TODO: hooks.

		return $order_email_reconcile;
	}
}
