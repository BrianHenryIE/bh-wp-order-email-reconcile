<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Helper_Trait;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Logger\API\Logger_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\Mailbox_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\Mailboxes_Settings_Helpers_Trait;
use Psr\Log\LogLevel;

/**
 * @see Email_Extract_Settings_Helper_Trait
 * @see Mailboxes_Settings_Helpers_Trait
 */
class Settings implements Email_Reconcile_Settings_Interface, Logger_Settings_Interface {

	/**
	 * @var Mailbox_Settings_Interface[]
	 */
	protected array $configured_mailboxes = array();

	protected array $extraction_patterns = array();

	public function __construct( array $configured_mailboxes, array $extraction_patterns ) {

		$this->configured_mailboxes = $configured_mailboxes;
		$this->extraction_patterns  = $extraction_patterns;
	}

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
	 * The settings for the mailboxes to be checked.
	 *
	 * @return Mailbox_Settings_Interface[]
	 */
	public function get_configured_mailbox_settings(): array {
		return $this->configured_mailboxes;
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
		return null;
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
	 * @see Logger
	 */
	public function get_plugin_name(): string {
		return 'Order Email Reconcile Test Plugin';
	}

	/**
	 * The plugin basename is used by the logger to add the plugins page action link.
	 * (and maybe for PHP errors)
	 *
	 * @return string
	 * @see Logger
	 */
	public function get_plugin_basename(): string {
		return defined( 'BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME' ) ? BH_WP_ORDER_EMAIL_RECONCILE_TEST_PLUGIN_BASENAME : 'bh-wp-order-email-reconcile/bh-wp-order-email-reconcile.php';
	}

	/**
	 * Name for the emails custom post type, e.g. "My Plugin Emails".
	 *
	 * Trait will automatically convert this to "my-plugin-emails" and "my_plugin_emails" where appropriate,
	 * using `sanitize_title` and additionally `str_replace('-','_'...)` respectively.
	 *
	 * Should usually be one cpt per plugin. But there can be more than one mailbox per plugin.
	 * This should be hard-coded, and not derived from user input (e.g. mailbox name).
	 *
	 * Max.  length 20 characters.
	 */
	public function get_cpt_friendly_name(): string {
		return 'Test Plugin Payment Emails';
	}

	/**
	 * Email attachments are stored in a subfolder of the wp-content/uploads directory. What name should be given to
	 * the folder? e.g. my-helpdesk-attachments. (use hyphenated name for WordPress standard)
	 *
	 * Trait default: plugin-slug-email-attachments
	 */
	public function get_private_uploads_directory_name(): string {
		return 'test-plugin-email-attachments';
	}

	/**
	 *
	 * @return string
	 * @see Mailboxes_Settings_Helpers_Trait::get_cpt_dashed()
	 */
	public function get_cpt_dashed(): string {
		return sanitize_title( $this->get_cpt_friendly_name() );
	}

	/**
	 * @return string
	 * @see Mailboxes_Settings_Helpers_Trait::get_cpt_underscored()
	 */
	public function get_cpt_underscored_20(): string {
		$cpt_underscored = str_replace( '-', '_', $this->get_cpt_dashed() );
		return substr( $cpt_underscored, 0, 20 );
	}
}
