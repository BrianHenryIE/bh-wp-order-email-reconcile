<?php
/**
 * Development/test plugin admin page: a top-level menu with tools to drive a reconciliation demo.
 *
 * - "Create order" makes an unpaid order with a unique customer_payment_id and a random total.
 * - "Send payment email" injects a mock payment email containing that id and total and fires the
 *   bh-wp-mailboxes saved-emails action so the library reconciles (and pays) the order.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use BrianHenryIE\WP_Mailboxes\API\Factories\BH_Email_Factory;
use BrianHenryIE\WP_Mailboxes\Connections\Imap\ImapEngine_Imap_Email_Connection;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;
use BrianHenryIE\WP_Mailboxes\API\Model\Fetched_Email;
use BrianHenryIE\WP_Mailboxes\API\Model\Remote_Email_Coordinates;
use BrianHenryIE\WP_Mailboxes\API\Repositories\Email_WP_Post_Repository;
use BrianHenryIE\WP_Mailboxes\BH_Email_Account;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use ZBateson\MailMimeParser\Message;

/**
 * Registers the dev menu/page and handles its actions.
 */
class Admin_Page {

	use LoggerAwareTrait;

	const CREATE_ORDER_ACTION = 'bh_wp_oer_create_demo_order';
	const SEND_EMAIL_ACTION   = 'bh_wp_oer_send_payment_email';
	const LAST_ORDER_OPTION   = 'bh_wp_oer_demo_last_order';

	/**
	 * Constructor. Registers the action handlers.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Provides slugs and the payment-id meta key.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'admin_post_' . self::CREATE_ORDER_ACTION, array( $this, 'handle_create_order' ) );
		add_action( 'admin_post_' . self::SEND_EMAIL_ACTION, array( $this, 'handle_send_payment_email' ) );
	}

	/**
	 * Render the dev tools page.
	 *
	 * @return void
	 */
	public function render_page(): void {

		$last       = get_option( self::LAST_ORDER_OPTION );
		$created_id = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$email_sent = isset( $_GET['sent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Email Reconcile — Dev Tools', 'bh-wp-order-email-reconcile' ); ?></h1>

			<?php if ( $email_sent ) : ?>
				<div class="notice notice-success"><p>
					<?php esc_html_e( 'Mock payment email sent and reconciled.', 'bh-wp-order-email-reconcile' ); ?>
				</p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Create unpaid order', 'bh-wp-order-email-reconcile' ); ?></h2>
			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ORDER_ACTION ); ?>" />
					<?php wp_nonce_field( self::CREATE_ORDER_ACTION ); ?>
					<button type="submit" class="button button-primary" id="create-demo-order">
						<?php esc_html_e( 'Create order', 'bh-wp-order-email-reconcile' ); ?>
					</button>
				</form>
				<?php if ( $created_id > 0 ) : ?>
					<a id="created-order-link" href="<?php echo esc_url( $this->order_edit_url( $created_id ) ); ?>">
						<?php
						/* translators: %d: order id. */
						echo esc_html( sprintf( __( 'Order #%d', 'bh-wp-order-email-reconcile' ), $created_id ) );
						?>
					</a>
				<?php endif; ?>
			</p>

			<?php if ( is_array( $last ) ) : ?>
				<h2><?php esc_html_e( 'Send payment email', 'bh-wp-order-email-reconcile' ); ?></h2>
				<p>
					<?php
					/* translators: 1: customer payment id, 2: order total. */
					$summary_template = __( 'A mock email for customer id "%1$s" and amount $%2$s.', 'bh-wp-order-email-reconcile' );
					$summary          = sprintf( $summary_template, (string) $last['payment_id'], (string) $last['total'] );
					echo esc_html( $summary );
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SEND_EMAIL_ACTION ); ?>" />
					<?php wp_nonce_field( self::SEND_EMAIL_ACTION ); ?>
					<button type="submit" class="button" id="send-payment-email">
						<?php esc_html_e( 'Send payment email', 'bh-wp-order-email-reconcile' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Create an unpaid order with a unique customer payment id and a random total.
	 *
	 * @hooked admin_post_bh_wp_oer_create_demo_order
	 *
	 * @return void
	 */
	public function handle_create_order(): void {

		check_admin_referer( self::CREATE_ORDER_ACTION );

		$payment_id = 'cust-' . substr( md5( uniqid( '', true ) ), 0, 8 );
		$total      = number_format( wp_rand( 500, 50000 ) / 100, 2, '.', '' );

		$order = wc_create_order();
		if ( $order instanceof \WP_Error ) {
			wp_safe_redirect( $this->menu_url() );
			exit;
		}

		$fee = new \WC_Order_Item_Fee();
		$fee->set_name( 'Demo charge' );
		$fee->set_total( $total );
		$order->add_item( $fee );

		$order->set_payment_method( 'my-payment-gateway-id' );
		$order->calculate_totals();

		$meta_key = $this->settings->get_customer_payment_id_meta_key();
		if ( ! empty( $meta_key ) ) {
			$order->update_meta_data( $meta_key, $payment_id );
		}

		$order->set_status( 'on-hold' );
		$order->save();

		update_option(
			self::LAST_ORDER_OPTION,
			array(
				'id'         => $order->get_id(),
				'payment_id' => $payment_id,
				'total'      => $order->get_total(),
			),
			false
		);

		wp_safe_redirect( add_query_arg( 'created', $order->get_id(), $this->menu_url() ) );
		exit;
	}

	/**
	 * Build a mock payment email for the last-created order and run it through reconciliation.
	 *
	 * @hooked admin_post_bh_wp_oer_send_payment_email
	 *
	 * @return void
	 */
	public function handle_send_payment_email(): void {

		check_admin_referer( self::SEND_EMAIL_ACTION );

		$last = get_option( self::LAST_ORDER_OPTION );
		if ( ! is_array( $last ) ) {
			wp_safe_redirect( $this->menu_url() );
			exit;
		}

		$payment_id = (string) $last['payment_id'];
		$total      = (string) $last['total'];

		$body = "Payment received\nCustomer ID: {$payment_id}\nAmount: \${$total}\n";

		$message_id = uniqid( 'demo-', true ) . '@example.com';
		$raw        = "From: payments@example.com\r\nTo: store@example.com\r\nSubject: Payment received\r\n"
			. "Message-ID: <{$message_id}>\r\nDate: " . gmdate( 'r' ) . "\r\n\r\n{$body}";

		// Persist the mock email to the emails CPT (so it is visible in the list) and reconcile it.
		$saved_email = $this->save_email( $raw, $message_id );

		do_action(
			'bh_wp_mailboxes_fetch_emails_saved_' . $this->settings->get_plugin_slug(),
			array( $saved_email )
		);

		wp_safe_redirect( add_query_arg( 'sent', '1', $this->menu_url() ) );
		exit;
	}

	/**
	 * Save a raw mock email to the emails CPT via the bh-wp-mailboxes repository.
	 *
	 * @param string $raw        The raw MIME message.
	 * @param string $message_id The Message-ID header value.
	 * @return BH_Email
	 */
	protected function save_email( string $raw, string $message_id ): BH_Email {

		$repository = new Email_WP_Post_Repository(
			$this->settings->get_emails_cpt_underscored_20(),
			new BH_Email_Factory( $this->logger ),
			$this->logger
		);

		$fetched_email = new Fetched_Email(
			message: Message::from( $raw, false ),
			coordinates: new Remote_Email_Coordinates( message_id: $message_id ),
			is_remote_read: false,
		);

		return $repository->save_new( $fetched_email, $this->settings, $this->make_demo_account(), null );
	}

	/**
	 * A throwaway BH_Email_Account to associate the mock email with.
	 *
	 * @return BH_Email_Account
	 */
	protected function make_demo_account(): BH_Email_Account {
		return new BH_Email_Account(
			post_id: 0,
			post_type: $this->settings->get_email_accounts_cpt_underscored_20(),
			local_status: 'publish',
			connection_type_class: ImapEngine_Imap_Email_Connection::class,
			email_address: 'payments@example.com',
			display_name: 'Demo mailbox',
			from_address_regex_filter: null,
			body_identifier_regex_filter: null,
			after_download_remote_email_action: null,
			delete_local_emails_after_n_days: null,
			last_checked_time: null,
			last_successful_login_time: null,
			last_failed_login_time: null,
		);
	}

	/**
	 * URL of the dev tools page.
	 *
	 * @return string
	 */
	protected function menu_url(): string {
		return admin_url( 'admin.php?page=' . Admin_Menu::MENU_SLUG );
	}

	/**
	 * HPOS-aware order edit URL.
	 *
	 * @param int $order_id The order id.
	 * @return string
	 */
	protected function order_edit_url( int $order_id ): string {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
	}
}
