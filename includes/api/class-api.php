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

use BrianHenryIE\WP_Mailboxes\API\Controller\Email_Controller_Interface;
use BrianHenryIE\WP_Mailboxes\BH_Email_Account;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
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

		add_action( 'bh_wp_mailboxes_new_email', array( $this, 'on_new_email' ), 10, 4 );
	}

	/**
	 * The unpaid orders provider the reconciler matches emails against (e.g. for the unreconciled orders page).
	 */
	public function get_unpaid_orders_provider(): Unpaid_Orders_Provider_Interface {
		return $this->unpaid_orders_provider;
	}

	/**
	 * Reconcile each newly fetched email as bh-wp-mailboxes saves it, against the unpaid orders.
	 *
	 * Untyped parameters: the action is global, so another plugin's (possibly namespace-prefixed)
	 * copy of bh-wp-mailboxes may fire it with its own classes; the plugin-slug and post-type guards
	 * filter to this instance before the objects are touched.
	 *
	 * @see \BrianHenryIE\WP_Mailboxes\API\API::alert_new_email()
	 * @hooked bh_wp_mailboxes_new_email
	 *
	 * @param string                     $plugin_slug      The plugin the library instance is firing from.
	 * @param string                     $emails_post_type The emails post type key, identifying which mailbox instance fired the action.
	 * @param BH_Email_Account           $account          The account the email was fetched for.
	 * @param Email_Controller_Interface $new_email        Controller wrapping the saved email.
	 */
	public function on_new_email( string $plugin_slug, string $emails_post_type, BH_Email_Account $account, Email_Controller_Interface $new_email ): void {
		if ( $this->settings->get_plugin_slug() !== $plugin_slug
			|| $this->settings->get_emails_cpt_underscored_20() !== $emails_post_type ) {
			return;
		}

		$email = $new_email->get_email();

		// Extract the payment values once, and keep what each pattern matched on the email for admin UIs.
		$email_parser = new Email_Parser( $this->settings->get_patterns(), $this->logger );
		$extraction   = $email_parser->extract( $email );
		if ( $email->get_post_id() > 0 ) {
			// Slashed: update_post_meta() unslashes its value, which would strip the regexes' backslashes.
			update_post_meta( $email->get_post_id(), Email_Parser::EMAIL_META_EXTRACTION, wp_slash( $extraction->to_array() ) );
		}

		$unpaid_orders = $this->unpaid_orders_provider->get_unpaid_orders();

		if ( 0 === count( $unpaid_orders ) ) {
			$this->logger->info( 'No unpaid orders found for ' . $this->settings->get_plugin_slug() . '; nothing to reconcile the email with.' );
			$this->record_processed( $new_email, __( 'Processed: no unpaid orders to match against.', 'bh-wp-order-email-reconcile' ) );
			return;
		}

		$this->logger->info(
			'{count_unpaid_orders} unpaid orders for {plugin_slug}.',
			array(
				'count_unpaid_orders' => count( $unpaid_orders ),
				'plugin_slug'         => $plugin_slug,
			)
		);

		$parsed_email = $extraction->parsed_email;
		if ( is_null( $parsed_email ) ) {
			return;
		}

		$this->email_reconciler->index_orders( $unpaid_orders );
		$result = $this->email_reconciler->reconcile_email( $parsed_email );

		if ( ! $result['reconciled'] || ! isset( $result['order'] ) ) {
			$this->record_processed( $new_email, __( 'Processed: no unpaid order matched.', 'bh-wp-order-email-reconcile' ), $extraction->get_values() );
			return;
		}

		$this->record_reconciled( $new_email, $result['order'] );
	}

	/**
	 * The email was looked at but matched no order: note it, and mark the email "processed" (which
	 * leaves it subject to bh-wp-mailboxes' automatic deletion).
	 *
	 * @param Email_Controller_Interface $new_email The email.
	 * @param string                     $message   The note.
	 * @param array<string, mixed>       $values    The extracted values, stored with the note.
	 */
	protected function record_processed( Email_Controller_Interface $new_email, string $message, array $values = array() ): void {
		$new_email
			->add_local_note( $message, 'notice', array( 'values' => $values ) )
			->update_local_status( 'bh_email_processed' );
	}

	/**
	 * The email reconciled an order: note it with a link to the order, and mark the email "saved",
	 * which exempts it from bh-wp-mailboxes' automatic deletion.
	 *
	 * @param Email_Controller_Interface $new_email The email.
	 * @param Unpaid_Order               $order     The reconciled order.
	 */
	protected function record_reconciled( Email_Controller_Interface $new_email, Unpaid_Order $order ): void {
		$edit_url = $order->get_edit_url();
		$label    = sprintf(
			/* translators: 1: integration name, e.g. WooCommerce; 2: order id. */
			__( '%1$s order #%2$d', 'bh-wp-order-email-reconcile' ),
			ucfirst( $order->get_integration() ),
			$order->get_order_id()
		);
		$new_email
			->add_local_note(
				sprintf(
					/* translators: %s: the order, linked to its edit screen. */
					__( 'Reconciled: payment matched to %s.', 'bh-wp-order-email-reconcile' ),
					is_null( $edit_url ) ? esc_html( $label ) : '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $label ) . '</a>'
				),
				'info',
				array(
					'integration' => $order->get_integration(),
					'order_id'    => $order->get_order_id(),
				)
			)
			->update_local_status( 'bh_email_saved' );
	}
}
