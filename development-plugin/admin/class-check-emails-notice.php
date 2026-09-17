<?php
/**
 * The admin notice summarising a "Check emails" run from an order's edit screen.
 *
 * Rendered by the REST endpoint (returned to the JS, which shows it in place when the order was not
 * reconciled) and by Order_UI after the reload that follows a reconciliation.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds the green (reconciled) or blue (nothing matched this order) notice markup.
 */
class Check_Emails_Notice {

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger The plugin's logger: when it is a NullLogger there are no logs to point the user to.
	 */
	public function __construct(
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * The notice markup for a check-emails summary.
	 *
	 * @param array<string, mixed> $summary new_emails, reconciled_emails, matched_orders, matched_email_id, failed_accounts.
	 */
	public function render( array $summary ): string {
		$new_emails        = (int) ( $summary['new_emails'] ?? 0 );
		$reconciled_emails = (int) ( $summary['reconciled_emails'] ?? 0 );
		$matched_orders    = (int) ( $summary['matched_orders'] ?? 0 );
		$matched_email_id  = (int) ( $summary['matched_email_id'] ?? 0 );
		$failed_accounts   = (int) ( $summary['failed_accounts'] ?? 0 );

		/* translators: %d: number of emails downloaded. */
		$downloaded = sprintf( _n( '%d new email downloaded', '%d new emails downloaded', $new_emails, 'bh-wp-order-email-reconcile' ), $new_emails );

		if ( $matched_email_id > 0 ) {
			$email_url = get_edit_post_link( $matched_email_id, 'raw' );
			/* translators: %d: email post id. */
			$email_label = sprintf( __( 'email #%d', 'bh-wp-order-email-reconcile' ), $matched_email_id );
			$email_link  = is_null( $email_url ) ? esc_html( $email_label ) : '<a href="' . esc_url( $email_url ) . '">' . esc_html( $email_label ) . '</a>';

			$message = sprintf(
				/* translators: 1: link to the email, 2: "N new emails downloaded", 3: number of emails reconciled. */
				__( 'This order was reconciled by %1$s. %2$s, %3$d reconciled.', 'bh-wp-order-email-reconcile' ),
				$email_link,
				esc_html( $downloaded ),
				$reconciled_emails
			);

			return '<div class="notice notice-success is-dismissible bh-wp-oer-check-emails-notice"><p>' . $message . '</p></div>';
		}

		$message = esc_html(
			sprintf(
				/* translators: 1: "N new emails downloaded", 2: number of orders reconciled. */
				_n(
					'%1$s. No emails matched this order; %2$d order was reconciled.',
					'%1$s. No emails matched this order; %2$d orders were reconciled.',
					$matched_orders,
					'bh-wp-order-email-reconcile'
				),
				$downloaded,
				$matched_orders
			)
		);
		if ( $failed_accounts > 0 ) {
			/* translators: %d: number of accounts that could not be checked. */
			$failed = sprintf( _n( '%d account could not be checked', '%d accounts could not be checked', $failed_accounts, 'bh-wp-order-email-reconcile' ), $failed_accounts );
			// Only point to the logs when there are logs.
			$failed  .= $this->logger instanceof NullLogger ? '.' : __( '; see the logs.', 'bh-wp-order-email-reconcile' );
			$message .= ' ' . esc_html( $failed );
		}

		return '<div class="notice notice-info is-dismissible bh-wp-oer-check-emails-notice"><p>' . $message . '</p></div>';
	}
}
