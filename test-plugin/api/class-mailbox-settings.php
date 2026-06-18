<?php

namespace BrianHenryIE\WC_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Mailboxes\Account_Credentials_Interface;
use BrianHenryIE\WP_Mailboxes\API\Ddeboer_Imap\IMAP_Credentials_Interface;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Defaults_Trait;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Interface;

/**
 * @see Mailbox_Settings_Defaults_Trait
 */
class Mailbox_Settings implements Mailbox_Settings_Interface {

	public function get_account_unique_friendly_name(): string {
		return 'support@brianhenryie.com';
	}

	public function get_credentials(): Account_Credentials_Interface {
		return new class() implements IMAP_Credentials_Interface {

			public function get_email_imap_server(): string {
				return $_ENV['IMAP_SERVER'];
			}

			public function get_email_account_username(): string {
				return $_ENV['IMAP_USERNAME'];
			}

			public function get_email_account_password(): string {
				return $_ENV['IMAP_PASSWORD'];
			}
		};
	}

	/**
	 * Should the email be deleted after it is reconciled.
	 *
	 * @return string nothing|mark_read|delete
	 */
	public function after_download_email_action(): string {
		// TODO: Implement after_reconcile_email_action() method.
	}

	/**
	 * Regex to filter email from address for further matching.
	 *
	 * Return null to match all addresses. (e.g. when emails are forwarded and do not preserve the real sender).
	 *
	 * @return string The pertinent email address.
	 */
	public function get_from_email_regex(): ?string {
		return null;
	}

	/**
	 * Set an identifier filter emails. i.e. if the email does not contain this, it is not relevant, so
	 * continue onto the next email in the inbox. Checks the body text of the email.
	 *
	 * Return null to match all.
	 *
	 * @return string|null
	 */
	public function get_identifier_regex(): ?string {
		return null;
	}

	/**
	 * Number of days to keep the emails. i.e. number of days after which the emails should be deleted.
	 *
	 * Set to 0 or null to disable.
	 *
	 * @return int|null
	 */
	public function get_delete_emails_days(): ?int {
		return 30;
	}
}
