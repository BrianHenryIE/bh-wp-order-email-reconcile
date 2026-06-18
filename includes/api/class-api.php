<?php
/**
 * The heavy lifting.
 * 1. Checks for unpaid orders.
 * 2. Fetches emails since last run.
 * 3. Parses values from those emails.
 * 4. Reconciles the emails with the outstanding orders.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wc-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;
use BrianHenryIE\WP_Mailboxes\BH_Email_Account;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Instantiates and runs Unpaid_Orders, Email_Fetcher, Email_Parser, Email_Reconciler and Save_Emails classes.
 *
 * Class IMAP_Reconcile
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */
class API {
	use LoggerAwareTrait;

	/**
	 * IMAP_Reconcile constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings The settings for connections, matching and reconciliation.
	 * @param LoggerInterface                    $logger  PSR logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		protected WC_Unpaid_Orders $unpaid_orders_service,
		protected Email_Reconciler $email_reconciler_service,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger);

		add_action( 'bh_wp_mailboxes_fetch_emails_saved_' . $this->settings->get_plugin_slug(), array( $this, 'process_new_emails' ), 10, 3 );
	}

	/**
	 * Synchronous check.
	 *
	 * Use when customers might be sitting watching the payment screen after sending payment.
	 *
	 * public function fetch_now(): Fetch_Now_Result
	 */

	/**
	 * Reconciles emails with unpaid orders.
	 *
	 * @hooked bh_wp_mailboxes_fetch_emails_saved_{plugin-slug}
	 * @see \BrianHenryIE\WP_Mailboxes\API\API::check_email()
	 *
	 * @param BH_Email[] $new_payment_emails
	 *
	 * @return array{success:bool, num_emails:int, num_unpaid_orders:int, reconciled:int}
	 */
	public function process_new_emails(
		array $new_payment_emails,
		BH_Email_Account $account,
		\BrianHenryIE\WP_Mailboxes\API\API $mailboxes
	): array {

		if ( 0 === count( $new_payment_emails ) ) {

			$result = array(
				'success'           => true,
				'num_emails'        => 0,
				'num_unpaid_orders' => null,
				'reconciled'        => 0,
			);
			return $result;
		}

		$unpaid_orders_service = $this->unpaid_orders_service;
		$unpaid_orders      = $unpaid_orders_service->get_unpaid_orders();

		// Nothing to do if there are no orders unpaid.
		if ( 0 === count( $unpaid_orders ) ) {
			$this->logger->info( 'No unpaid orders found. Ending. ' . count( $new_payment_emails ) . ' emails found. Account: ' . $account->get_account_unique_friendly_name() );
			$result = array(
				'success'           => true,
				'num_emails'        => count( $new_payment_emails ),
				'num_unpaid_orders' => 0,
				'reconciled'        => 0,
			);
			return $result;
		}

		$this->logger->info( count( $unpaid_orders ) . ' unpaid orders for ' . $this->settings->get_plugin_slug() . ' gateways.' );

		$email_parser = new Email_Parser( $this->settings->get_patterns(), $this->logger );

		$parsed_emails = $email_parser->parse_emails( $new_payment_emails );

		$email_reconciler = $this->email_reconciler_service;
		$email_reconciler->index_orders( $unpaid_orders );

		$reconcile_emails_result = $email_reconciler->reconcile_emails( $parsed_emails );

		// TODO: create Process_New_Emails_Result class.
		$result = array(
			'success'           => true,
			'num_emails'        => count( $new_payment_emails ),
			'num_unpaid_orders' => count( $unpaid_orders ),
			'reconciled'        => count( $reconcile_emails_result['reconciled_emails'] ),
		);

		return $result;
	}

}
