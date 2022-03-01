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
use BrianHenryIE\WP_Mailboxes\BH_Email;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes_Settings_Interface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Instantiates and runs Unpaid_Orders, Email_Fetcher, Email_Parser, Email_Reconciler and Save_Emails classes.
 *
 * Class IMAP_Reconcile
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */
class API {

	/**
	 * The settings for IMAP connections, regexes for extracting values from emails and post-reconciliation action.
	 *
	 * @var Email_Reconcile_Settings_Interface
	 */
	protected Email_Reconcile_Settings_Interface $settings;

	/**
	 * PSR Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * IMAP_Reconcile constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings The settings for connections, matching and reconciliation.
	 * @param ?LoggerInterface                   $logger  Optional logger.
	 */
	public function __construct( Email_Reconcile_Settings_Interface $settings, $logger = null ) {

		$this->logger   = $logger ?? new NullLogger();
		$this->settings = $settings;

		add_action( 'bh_wp_mailboxes_fetch_emails_saved_' . $settings->get_plugin_slug(), array( $this, 'process_new_emails' ), 10, 3 );
	}

	/**
	 * Reconciles emails with unpaid orders.
	 *
	 * @hooked bh_wp_mailboxes_fetch_emails_saved_{plugin-slug}
	 * @see \BrianHenryIE\WP_Mailboxes\API\API::check_email()
	 *
	 * @param BH_Email[] $emails
	 *
	 * @return array{success:bool, num_emails:int, num_unpaid_orders:int, reconciled:int}
	 */
	public function process_new_emails( array $emails, BH_WP_Mailboxes_Settings_Interface $mailboxes, Mailbox_Settings_Interface $account ): array {

		$unpaid_orders    = new Unpaid_Orders( $this->settings, $this->logger );
		$wc_unpaid_orders = $unpaid_orders->get_unpaid_orders();

		// Nothing to do if there are no orders unpaid.
		if ( 0 === count( $unpaid_orders ) ) {
			$this->logger->info( 'No unpaid orders found. Ending. ' . count( $emails ) . ' emails found. Account: ' . $account->get_account_unique_friendly_name() );
			$result = array(
				'success'           => true,
				'num_emails'        => count( $emails ),
				'num_unpaid_orders' => 0,
				'reconciled'        => 0,
			);
			return $result;
		}

		$this->logger->info( count( $unpaid_orders ) . ' unpaid orders for ' . $this->settings->get_plugin_slug() . ' gateways.' );

		$new_payment_emails = $emails;

		// TODO: Return here if there are none.
		if ( 0 === count( $new_payment_emails ) ) {

			$result = array(
				'success'           => true,
				'num_emails'        => 0,
				'num_unpaid_orders' => count( $wc_unpaid_orders ),
				'reconciled'        => 0,
			);
			return $result;
		}

		$email_parser = new Email_Parser( $this->settings->get_patterns(), $this->logger );

		$parsed_emails = $email_parser->parse_emails( $new_payment_emails );

		$email_reconciler = new Email_Reconciler( $this->settings, $this->logger );
		$email_reconciler->index_orders( $wc_unpaid_orders );

		$reconcile_emails_result = $email_reconciler->reconcile_emails( $parsed_emails );

		$result = array(
			'success'           => true,
			'num_emails'        => count( $emails ),
			'num_unpaid_orders' => count( $wc_unpaid_orders ),
			'reconciled'        => count( $reconcile_emails_result['reconciled_emails'] ),
		);

		return $result;
	}

}
