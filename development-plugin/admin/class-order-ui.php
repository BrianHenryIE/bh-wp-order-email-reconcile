<?php
/**
 * Admin order-edit UI for the development/test plugin.
 *
 * Adds an editable "customer payment id" field to the order billing panel and, for unpaid orders, a
 * "Fetch emails now" button that runs the bh-wp-mailboxes fetch immediately.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\API as Mailboxes_API;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Renders and saves the order-edit additions.
 */
class Order_UI {

	use LoggerAwareTrait;

	const FETCH_EMAILS_ACTION = 'bh_wp_oer_fetch_emails';

	/**
	 * Constructor. Registers the admin hooks.
	 *
	 * @param Mailboxes_API                      $mailboxes_api Runs the email fetch.
	 * @param Email_Reconcile_Settings_Interface $settings      Provides the customer-payment-id meta key.
	 * @param LoggerInterface                    $logger        PSR-3 logger.
	 */
	public function __construct(
		protected Mailboxes_API $mailboxes_api,
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'print_order_fields' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_order_fields' ) );
		add_action( 'admin_post_' . self::FETCH_EMAILS_ACTION, array( $this, 'handle_fetch_emails' ) );
	}

	/**
	 * Print the editable customer payment id field and, for unpaid orders, the fetch-emails button.
	 *
	 * @hooked woocommerce_admin_order_data_after_billing_address
	 *
	 * @param WC_Order $order The order being edited.
	 * @return void
	 */
	public function print_order_fields( WC_Order $order ): void {

		$meta_key = $this->settings->get_customer_payment_id_meta_key();

		if ( ! empty( $meta_key ) ) {
			woocommerce_wp_text_input(
				array(
					'id'            => 'customer_payment_id',
					'label'         => __( 'Customer payment id', 'bh-wp-order-email-reconcile' ),
					'description'   => __( 'e.g. Venmo username / $CashTag, used to match payment emails.', 'bh-wp-order-email-reconcile' ),
					'desc_tip'      => true,
					'value'         => (string) $order->get_meta( $meta_key ),
					'wrapper_class' => 'form-field-wide',
				)
			);
		}

		if ( ! $order->is_paid() ) {
			$this->print_fetch_emails_button( $order );
		}
	}

	/**
	 * Print a "Fetch emails now" action link.
	 *
	 * Rendered as a nonce-protected link rather than a nested <form> (the order screen is already one
	 * big form, and nesting forms is invalid HTML that breaks the order save).
	 *
	 * @param WC_Order $order The unpaid order being edited.
	 * @return void
	 */
	protected function print_fetch_emails_button( WC_Order $order ): void {

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::FETCH_EMAILS_ACTION . '&order_id=' . $order->get_id() ),
			self::FETCH_EMAILS_ACTION
		);
		?>
		<p class="form-field form-field-wide">
			<a class="button" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'Fetch emails now', 'bh-wp-order-email-reconcile' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Save the customer payment id field.
	 *
	 * @hooked woocommerce_process_shop_order_meta
	 *
	 * @param int $order_id The order being saved.
	 * @return void
	 */
	public function save_order_fields( int $order_id ): void {

		$meta_key = $this->settings->get_customer_payment_id_meta_key();
		if ( empty( $meta_key ) ) {
			return;
		}

		// Nonce is verified by WooCommerce before this hook fires as part of the order save.
		if ( ! isset( $_POST['customer_payment_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$value = sanitize_text_field( wp_unslash( $_POST['customer_payment_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$order->update_meta_data( $meta_key, $value );
		$order->save();
	}

	/**
	 * Run the email fetch and redirect back to the order.
	 *
	 * @hooked admin_post_bh_wp_oer_fetch_emails
	 *
	 * @return void
	 */
	public function handle_fetch_emails(): void {

		check_admin_referer( self::FETCH_EMAILS_ACTION );

		$result = $this->mailboxes_api->check_email();

		$this->logger->info(
			'Fetch emails now: triggered from order admin.',
			array(
				'success'           => $result->success,
				'saved_email_count' => count( $result->new_emails ),
			)
		);

		$redirect = wp_get_referer();
		wp_safe_redirect( false !== $redirect ? $redirect : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}
}
