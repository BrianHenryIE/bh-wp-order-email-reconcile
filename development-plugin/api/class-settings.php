<?php
/**
 * Settings for the development/test plugin.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Logger\Logger_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes_Settings_Defaults_Trait;
use Psr\Log\LogLevel;

/**
 * Hard-coded settings implementing the library's settings interface plus the logger's.
 *
 * @see BH_WP_Mailboxes_Settings_Defaults_Trait
 */
class Settings implements Email_Reconcile_Settings_Interface, Logger_Settings_Interface {
	use BH_WP_Mailboxes_Settings_Defaults_Trait;

	/**
	 * Order meta key storing the customer's payment-platform id (e.g. Venmo username).
	 */
	const CUSTOMER_PAYMENT_ID_META_KEY = '_customer_payment_id';

	/**
	 * Rules (sets of regex patterns) for extracting the data from the emails.
	 *
	 * @var Email_Extract_Settings_Interface[]
	 */
	protected array $extraction_patterns;

	/**
	 * Constructor.
	 *
	 * @param Email_Extract_Settings_Interface[] $extraction_patterns Pattern sets for extracting payment data from emails.
	 */
	public function __construct( array $extraction_patterns ) {
		$this->extraction_patterns = $extraction_patterns;
	}

	/**
	 * The plugin's slug, used in option/transient names.
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string {
		return 'test-plugin';
	}

	/**
	 * When searching for orders to reconcile, include orders placed using these payment gateway ids.
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids(): array {
		return array( 'my-payment-gateway-id' );
	}

	/**
	 * Rules for matching emails.
	 *
	 * @return Email_Extract_Settings_Interface[]
	 */
	public function get_patterns(): array {
		return $this->extraction_patterns;
	}

	/**
	 * If a customer has a known id, e.g. Venmo username, $CashTag, it should be saved in the order meta, then
	 * the meta key name provided here to help match the order.
	 *
	 * @return ?string
	 */
	public function get_customer_payment_id_meta_key(): ?string {
		return self::CUSTOMER_PAYMENT_ID_META_KEY;
	}

	/**
	 * Prefix for the meta the reconciler records on matched orders.
	 *
	 * @see Email_Reconcile_Settings_Interface::get_order_meta_prefix()
	 */
	public function get_order_meta_prefix(): string {
		return 'dev_';
	}

	/**
	 * The display name for the emails' custom post type.
	 *
	 * @return string
	 */
	public function get_emails_cpt_friendly_name(): string {
		return 'Test Payment Emails';
	}

	/**
	 * The display name for the email accounts' custom post type.
	 *
	 * @return string
	 */
	public function get_email_accounts_cpt_friendly_name(): string {
		return 'Test Email Accounts';
	}

	/**
	 * Cron schedules. `fetch_emails` is intentionally omitted: this library schedules it on demand,
	 * only while there are unpaid orders to reconcile.
	 *
	 * @return array<string, string>
	 */
	public function get_cron_schedules(): array {
		return array(
			'delete_local_emails' => 'daily',
		);
	}

	/**
	 * Enable bh-wp-mailboxes' REST endpoints, including the raw-MIME email ingress endpoint at
	 * `{namespace}/v2/{emails-cpt-dashed}/new`, used by the e2e tests to POST emails.
	 *
	 * @see \BrianHenryIE\WP_Mailboxes\Connections\Rest\REST_Ingress_Connection
	 */
	public function get_rest_namespace(): ?string {
		return 'test-plugin';
	}

	/**
	 * The minimum severity of logs to record.
	 *
	 * @return string
	 * @see LogLevel
	 */
	public function get_log_level(): string {
		return LogLevel::DEBUG;
	}

	/**
	 * Plugin name for use by the logger in friendly messages printed to WordPress admin UI.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'Order Email Reconcile Test Plugin';
	}

	/**
	 * The plugin basename is used by the logger to add the plugins page action link.
	 *
	 * @return string
	 */
	public function get_plugin_basename(): string {
		return defined( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME' )
			? BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME
			: 'bh-wp-order-email-reconcile/bh-wp-order-email-reconcile.php';
	}

	/**
	 * An optional WP-CLI command base for the logger's commands.
	 *
	 * @return ?string
	 */
	public function get_cli_base(): ?string {
		return null;
	}
}
