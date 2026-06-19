<?php
/**
 * Re-evaluates the fetch-emails cron whenever a WooCommerce order changes state.
 *
 * This is the WooCommerce-specific half of the cron start/stop feature: it listens to order
 * lifecycle hooks and delegates to the integration-agnostic Cron_Scheduler. Each integration that
 * can create unpaid orders provides its own listener.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes\Cron_Scheduler;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Hooks WooCommerce order transitions and asks the scheduler to start/stop the fetch-emails cron.
 */
class WC_Order_Status_Listener {
	use LoggerAwareTrait;

	/**
	 * Constructor. Registers the order lifecycle hooks.
	 *
	 * @param Cron_Scheduler  $cron_scheduler Starts/stops the fetch-emails cron.
	 * @param LoggerInterface $logger         PSR-3 logger.
	 */
	public function __construct(
		protected Cron_Scheduler $cron_scheduler,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'woocommerce_new_order', array( $this, 'update_cron_schedule' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'update_cron_schedule' ) );
	}

	/**
	 * Refresh the cached unpaid-orders state (once per request, on shutdown).
	 *
	 * WooCommerce order saves run inside an HPOS database transaction, so querying/scheduling
	 * synchronously from these hooks is unreliable. Deferring to `shutdown` runs the refresh after
	 * the order is committed and coalesces multiple order changes in a request into one query.
	 *
	 * @hooked woocommerce_new_order
	 * @hooked woocommerce_order_status_changed
	 *
	 * @return void
	 */
	public function update_cron_schedule(): void {
		if ( ! has_action( 'shutdown', array( $this->cron_scheduler, 'refresh_unpaid_orders_state' ) ) ) {
			add_action( 'shutdown', array( $this->cron_scheduler, 'refresh_unpaid_orders_state' ) );
		}
	}
}
