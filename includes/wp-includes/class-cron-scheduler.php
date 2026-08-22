<?php
/**
 * Schedules the bh-wp-mailboxes fetch-emails cron only while there is reconciliation work to do.
 *
 * The bh-wp-mailboxes library owns the cron job and, on every `plugins_loaded`, unschedules any job
 * that is not listed in get_cron_schedules(). This library deliberately omits `fetch_emails` from
 * those settings so the job is not always-on. To re-assert the on-demand schedule, this class hooks
 * `plugins_loaded` at a later priority (after mailboxes has run) and schedules/clears the job based
 * on a cheap cached flag. The flag is refreshed (a real unpaid-orders query) only when an order
 * changes, via the integration order listeners.
 *
 * It is integration-agnostic: it depends only on the aggregate Unpaid_Orders_Provider_Interface.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Starts/stops the mailboxes fetch-emails cron based on whether any unpaid orders remain.
 */
class Cron_Scheduler {
	use LoggerAwareTrait;

	/**
	 * Priority for re-asserting the schedule on plugins_loaded; after bh-wp-mailboxes Cron (20).
	 */
	const PLUGINS_LOADED_PRIORITY = 21;

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings               Used to derive the cron hook and option names.
	 * @param Unpaid_Orders_Provider_Interface   $unpaid_orders_provider The aggregate provider of unpaid orders.
	 * @param LoggerInterface                    $logger                 PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		protected Unpaid_Orders_Provider_Interface $unpaid_orders_provider,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * The bh-wp-mailboxes fetch-emails cron hook name, derived the same way as that library's Cron.
	 *
	 * @see \BrianHenryIE\WP_Mailboxes\WP_Includes\Cron::get_fetch_emails_cron_hook_name()
	 *
	 * @return string
	 */
	public function get_fetch_emails_cron_hook_name(): string {
		return sanitize_key( $this->settings->get_emails_cpt_underscored_20() ) . '_fetch_emails_job';
	}

	/**
	 * The option name caching whether unpaid orders currently exist.
	 *
	 * @return string
	 */
	protected function get_state_option_name(): string {
		return 'bh_wp_oer_' . sanitize_key( $this->settings->get_plugin_slug() ) . '_has_unpaid_orders';
	}

	/**
	 * Re-query the providers, cache whether unpaid orders exist, and apply the schedule immediately.
	 *
	 * Called when an order changes (via the integration order listeners) — not on every request.
	 *
	 * @return void
	 */
	public function refresh_unpaid_orders_state(): void {
		$has_unpaid = count( $this->unpaid_orders_provider->get_unpaid_orders() ) > 0;
		update_option( $this->get_state_option_name(), $has_unpaid ? 'yes' : 'no', false );
		$this->enforce_cron_schedule();
	}

	/**
	 * Schedule or clear the fetch-emails cron to match the cached unpaid-orders flag.
	 *
	 * Cheap (reads an option, no order query). Hooked on plugins_loaded after bh-wp-mailboxes' Cron
	 * has had its chance to unschedule the job, so the on-demand schedule is re-asserted each request.
	 *
	 * @hooked plugins_loaded
	 *
	 * @return void
	 */
	public function enforce_cron_schedule(): void {

		$hook         = $this->get_fetch_emails_cron_hook_name();
		$is_scheduled = false !== wp_next_scheduled( $hook );
		$has_unpaid   = 'yes' === get_option( $this->get_state_option_name(), 'no' );

		if ( $has_unpaid && ! $is_scheduled ) {
			/**
			 * Filter the recurrence used when scheduling the fetch-emails cron.
			 *
			 * @param string $recurrence A wp_get_schedules() key, e.g. 'hourly'.
			 * @param string $plugin_slug
			 */
			$recurrence = apply_filters( 'bh_wp_order_email_reconcile_fetch_emails_cron_recurrence', 'hourly', $this->settings->get_plugin_slug() );
			wp_schedule_event( time(), $recurrence, $hook );
			$this->logger->info( "Scheduled fetch-emails cron ({$hook}); unpaid orders await reconciliation." );
			return;
		}

		if ( ! $has_unpaid && $is_scheduled ) {
			wp_unschedule_hook( $hook );
			$this->logger->info( "Cleared fetch-emails cron ({$hook}); no unpaid orders remain." );
		}
	}

	/**
	 * Whether the fetch-emails cron is currently scheduled, and when it next runs.
	 *
	 * @return array{scheduled:bool, next:?int}
	 */
	public function is_scheduled(): array {
		$next = wp_next_scheduled( $this->get_fetch_emails_cron_hook_name() );
		return array(
			'scheduled' => false !== $next,
			'next'      => false !== $next ? $next : null,
		);
	}
}
