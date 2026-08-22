<?php
/**
 * A convenience class with a static `::make()` method that wires up the library.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile;

use BrianHenryIE\WP_Order_Email_Reconcile\API\API;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Aggregate_Unpaid_Orders_Provider;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Order_Status_Listener;
use BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Reconciliation_Admin;
use BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes\Cron_Scheduler;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Convenience factory that wires up bh-wp-mailboxes, the unpaid-orders providers, the reconciler and
 * the API in one `::make()` call.
 */
class BH_WP_Order_Email_Reconcile extends API {

	/**
	 * The booted bh-wp-mailboxes API, exposed for consumers that need to manage email accounts.
	 *
	 * @var ?\BrianHenryIE\WP_Mailboxes\API\API
	 */
	protected ?\BrianHenryIE\WP_Mailboxes\API\API $mailboxes_api = null;

	/**
	 * Instantiate the library: bh-wp-mailboxes, the unpaid-orders providers, the reconciler and the API.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Plugin settings.
	 * @param ?LoggerInterface                   $logger   PSR-3 logger.
	 */
	public static function make(
		Email_Reconcile_Settings_Interface $settings,
		?LoggerInterface $logger = null
	): BH_WP_Order_Email_Reconcile {

		$logger ??= new NullLogger();

		// bh-wp-mailboxes fetches and saves the emails and fires the
		// `bh_wp_mailboxes_fetch_emails_saved_{plugin-slug}` action this library hooks.
		$mailboxes_api = BH_WP_Mailboxes::make( $settings, $logger );

		// Builds its provider list (WooCommerce, GiveWP, plus the
		// `bh_wp_order_email_reconcile_unpaid_orders_providers` filter) on each use, so integrations
		// registering on later hooks are still picked up.
		$unpaid_orders_provider = new Aggregate_Unpaid_Orders_Provider( $settings, $logger );

		// The fetch-emails cron runs only while there are unpaid orders to reconcile. Each integration
		// listens to its order lifecycle and refreshes the scheduler; the scheduler re-asserts the
		// schedule on plugins_loaded (after bh-wp-mailboxes' Cron has unscheduled non-configured jobs).
		$cron_scheduler = new Cron_Scheduler( $settings, $unpaid_orders_provider, $logger );
		add_action( 'plugins_loaded', array( $cron_scheduler, 'enforce_cron_schedule' ), Cron_Scheduler::PLUGINS_LOADED_PRIORITY );
		new WC_Order_Status_Listener( $cron_scheduler, $logger );

		// Admin cross-links between reconciled orders and their payment emails.
		if ( is_admin() ) {
			new WC_Reconciliation_Admin( $settings, $logger );
		}

		$email_reconciler = new Email_Reconciler( $settings, $logger );

		$instance = new self(
			$settings,
			$unpaid_orders_provider,
			$email_reconciler,
			$logger
		);

		$instance->mailboxes_api = $mailboxes_api;

		return $instance;
	}

	/**
	 * The booted bh-wp-mailboxes API (e.g. to add or list email accounts), or null if not booted.
	 *
	 * @return ?\BrianHenryIE\WP_Mailboxes\API\API
	 */
	public function get_mailboxes_api(): ?\BrianHenryIE\WP_Mailboxes\API\API {
		return $this->mailboxes_api;
	}
}
