<?php
/**
 * The heavy lifting.
 * 1. Checks for unpaid orders.
 * 2. Fetches emails since last run (via bh-wp-mailboxes).
 * 3. Parses values from those emails.
 * 4. Reconciles the emails with the outstanding orders.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Reacts to newly saved emails from bh-wp-mailboxes, parses them, and reconciles them against the
 * unpaid orders supplied by the integration providers. Knows nothing about WooCommerce or GiveWP.
 */
class API {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings                The settings for connections, matching and reconciliation.
	 * @param Unpaid_Orders_Provider_Interface   $unpaid_orders_provider  Supplies the unpaid orders to reconcile (integration-agnostic).
	 * @param Email_Reconciler                   $email_reconciler        Matches emails to orders.
	 * @param LoggerInterface                    $logger                  PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		protected Unpaid_Orders_Provider_Interface $unpaid_orders_provider,
		protected Email_Reconciler $email_reconciler,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );

		add_action(
			'bh_wp_mailboxes_new_email',
			/**
			 * Reconcile each newly fetched email as bh-wp-mailboxes saves it.
			 *
			 * Untyped parameters: the action is global, so another plugin's (possibly
			 * namespace-prefixed) copy of bh-wp-mailboxes may fire it with its own classes; the
			 * plugin-slug and post-type guards filter to this instance before the objects are
			 * touched.
			 *
			 * @param string                                             $plugin_slug      The plugin the library instance is firing from.
			 * @param string                                             $emails_post_type The emails post type key, identifying which mailbox instance fired the action.
			 * @param \BrianHenryIE\WP_Mailboxes\BH_Email_Account       $account          The account the email was fetched for.
			 * @param \BrianHenryIE\WP_Mailboxes\API\New_Email_Interface $new_email        Wrapper around the saved email.
			 */
			function ( $plugin_slug, $emails_post_type, $account, $new_email ): void {
				if ( $this->settings->get_plugin_slug() !== $plugin_slug
					|| $this->settings->get_emails_cpt_underscored_20() !== $emails_post_type ) {
					return;
				}
				$this->process_new_emails( array( $new_email->get_email() ) );
			},
			10,
			4
		);
	}

	/**
	 * The unpaid orders provider the reconciler matches emails against (e.g. for the unreconciled orders page).
	 */
	public function get_unpaid_orders_provider(): Unpaid_Orders_Provider_Interface {
		return $this->unpaid_orders_provider;
	}

	/**
	 * Reconciles newly fetched payment emails with unpaid orders.
	 *
	 * @hooked bh_wp_mailboxes_new_email
	 * @see \BrianHenryIE\WP_Mailboxes\API\API::check_email_for_account()
	 *
	 * @param BH_Email[] $new_payment_emails The emails saved during the latest fetch.
	 *
	 * @return array{success:bool, num_emails:int, num_unpaid_orders:null|int, reconciled:int}
	 */
	public function process_new_emails(
		array $new_payment_emails
	): array {

		if ( 0 === count( $new_payment_emails ) ) {
			return array(
				'success'           => true,
				'num_emails'        => 0,
				'num_unpaid_orders' => null,
				'reconciled'        => 0,
			);
		}

		$unpaid_orders = $this->unpaid_orders_provider->get_unpaid_orders();

		// Nothing to do if there are no unpaid orders.
		if ( 0 === count( $unpaid_orders ) ) {
			$this->logger->info( 'No unpaid orders found. Ending. ' . count( $new_payment_emails ) . ' emails found for ' . $this->settings->get_plugin_slug() . '.' );
			return array(
				'success'           => true,
				'num_emails'        => count( $new_payment_emails ),
				'num_unpaid_orders' => 0,
				'reconciled'        => 0,
			);
		}

		$this->logger->info( count( $unpaid_orders ) . ' unpaid orders for ' . $this->settings->get_plugin_slug() . '.' );

		$email_parser  = new Email_Parser( $this->settings->get_patterns(), $this->logger );
		$parsed_emails = $email_parser->parse_emails( $new_payment_emails );

		$this->email_reconciler->index_orders( $unpaid_orders );
		$reconcile_emails_result = $this->email_reconciler->reconcile_emails( $parsed_emails );

		// TODO: create Process_New_Emails_Result class.
		return array(
			'success'           => true,
			'num_emails'        => count( $new_payment_emails ),
			'num_unpaid_orders' => count( $unpaid_orders ),
			'reconciled'        => count( $reconcile_emails_result['reconciled_emails'] ),
		);
	}
}
