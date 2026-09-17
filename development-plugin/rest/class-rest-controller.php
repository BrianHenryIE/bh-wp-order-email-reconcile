<?php
/**
 * Development-only REST endpoints that let the Playwright e2e tests arrange/assert via HTTP.
 *
 * Namespace: bh-wp-order-email-reconcile-dev/v1. These endpoints are intentionally open
 * (permission_callback __return_true) because this plugin only runs in the test environment.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\REST;

use BrianHenryIE\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin\Check_Emails_Notice;
use BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin\Order_UI;
use BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes\Cron_Scheduler;
use BrianHenryIE\WP_Mailboxes\API\API as Mailboxes_API;
use BrianHenryIE\WP_Mailboxes\BH_WP_Mailboxes;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and handles the development REST routes.
 */
class REST_Controller {

	use LoggerAwareTrait;

	const REST_NAMESPACE = 'bh-wp-order-email-reconcile-dev/v1';

	/**
	 * Short provider-type names accepted by the email-accounts endpoint.
	 *
	 * @var array<string, class-string>
	 */
	const PROVIDER_CLASSES = array(
		'imap' => \BrianHenryIE\WP_Mailboxes\Connections\Imap\ImapEngine_Imap_Email_Connection::class,
	);

	/**
	 * Constructor. Hooks route registration.
	 *
	 * @param Mailboxes_API                      $mailboxes_api  Used to add/list email accounts.
	 * @param Cron_Scheduler                     $cron_scheduler Reports the fetch-emails cron state.
	 * @param Email_Reconcile_Settings_Interface $settings       Plugin settings.
	 * @param LoggerInterface                    $logger         PSR-3 logger.
	 */
	public function __construct(
		protected Mailboxes_API $mailboxes_api,
		protected Cron_Scheduler $cron_scheduler,
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the development REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {

		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/cron/fetch-emails',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cron' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)/check-emails',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_emails_for_order' ),
				'permission_callback' => fn() => current_user_can( 'edit_shop_orders' ), // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/orders/(?P<id>\d+)/pay',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pay_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/email-accounts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_email_accounts' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_email_account' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Report whether the library, mailboxes, and reconcile hook are loaded.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'library_loaded'   => class_exists( BH_WP_Order_Email_Reconcile::class ),
				'mailboxes_loaded' => class_exists( BH_WP_Mailboxes::class ),
				'hook_registered'  => false !== has_action( 'bh_wp_mailboxes_new_email' ),
			),
			200
		);
	}

	/**
	 * Report the fetch-emails cron schedule state.
	 *
	 * @return WP_REST_Response
	 */
	public function get_cron(): WP_REST_Response {
		return new WP_REST_Response( $this->cron_scheduler->is_scheduled(), 200 );
	}

	/**
	 * Create an unpaid WooCommerce order for the given payment method.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function create_order( WP_REST_Request $request ): WP_REST_Response {

		$payment_method      = (string) ( $request->get_param( 'payment_method' ) ?? 'my-payment-gateway-id' );
		$total               = (string) ( $request->get_param( 'total' ) ?? '0' );
		$customer_payment_id = $request->get_param( 'customer_payment_id' );
		$billing_email       = $request->get_param( 'billing_email' );
		$billing_first_name  = $request->get_param( 'billing_first_name' );
		$billing_last_name   = $request->get_param( 'billing_last_name' );

		$order = wc_create_order();

		if ( $order instanceof \WP_Error ) {
			return new WP_REST_Response( array( 'error' => $order->get_error_message() ), 500 );
		}

		$fee = new \WC_Order_Item_Fee();
		$fee->set_name( 'Test charge' );
		$fee->set_total( $total );
		$order->add_item( $fee );

		$order->set_payment_method( $payment_method );

		if ( is_string( $billing_email ) && '' !== $billing_email ) {
			$order->set_billing_email( $billing_email );
		}
		if ( is_string( $billing_first_name ) && '' !== $billing_first_name ) {
			$order->set_billing_first_name( $billing_first_name );
		}
		if ( is_string( $billing_last_name ) && '' !== $billing_last_name ) {
			$order->set_billing_last_name( $billing_last_name );
		}

		$customer_payment_id_meta_key = $this->settings->get_customer_payment_id_meta_key();
		if ( is_string( $customer_payment_id ) && '' !== $customer_payment_id && ! empty( $customer_payment_id_meta_key ) ) {
			$order->update_meta_data( $customer_payment_id_meta_key, $customer_payment_id );
		}

		$order->calculate_totals();
		$order->set_status( 'on-hold' );
		$order->save();

		return new WP_REST_Response(
			array(
				'id'     => $order->get_id(),
				'status' => $order->get_status(),
				'total'  => $order->get_total(),
			),
			201
		);
	}

	/**
	 * Mark an order paid.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function pay_order( WP_REST_Request $request ): WP_REST_Response {

		$order_id = (int) $request->get_param( 'id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return new WP_REST_Response( array( 'error' => 'Order not found.' ), 404 );
		}

		$order->payment_complete();

		return new WP_REST_Response(
			array(
				'id'     => $order->get_id(),
				'status' => $order->get_status(),
			),
			200
		);
	}

	/**
	 * Check the mailbox now, from an order's edit screen ("Check emails"), and summarise the outcome
	 * for that order.
	 *
	 * Returns the summary with its rendered notice. When this order was reconciled the summary is also
	 * stored in a transient (per order and user) for the order screen the JS then reloads
	 * ({@see Order_UI::print_check_emails_notice()}).
	 *
	 * @param WP_REST_Request $request The REST request; `id` is the order id.
	 * @return WP_REST_Response
	 */
	public function check_emails_for_order( WP_REST_Request $request ): WP_REST_Response {

		$order_id = (int) $request->get_param( 'id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return new WP_REST_Response( array( 'error' => 'Order not found.' ), 404 );
		}

		$result = $this->mailboxes_api->check_email();

		$reconciled_emails = 0;
		$matched_order_ids = array();
		$matched_email_id  = 0;
		foreach ( $result->get_emails() as $new_email ) {
			$email_post_id       = $new_email->get_email()->get_post_id();
			$reconciled_order_id = (int) get_post_meta( $email_post_id, Email_Reconciler::EMAIL_META_ORDER_ID, true );
			if ( $reconciled_order_id <= 0 ) {
				continue;
			}
			++$reconciled_emails;
			$matched_order_ids[ $reconciled_order_id ] = true;
			if ( $reconciled_order_id === $order_id ) {
				$matched_email_id = $email_post_id;
			}
		}

		$summary = array(
			'order_id'          => $order_id,
			'new_emails'        => count( $result->get_emails() ),
			'reconciled_emails' => $reconciled_emails,
			'matched_orders'    => count( $matched_order_ids ),
			'matched_email_id'  => $matched_email_id,
			'failed_accounts'   => count( $result->get_failures() ),
		);

		$this->logger->info( 'Check emails: triggered from order admin.', $summary );

		// The JS reloads the order screen only when this order was reconciled; the notice for the
		// reloaded page is left in a transient. Otherwise it shows the notice in place.
		if ( $matched_email_id > 0 ) {
			set_transient( Order_UI::check_emails_result_transient( $order_id ), $summary, MINUTE_IN_SECONDS * 5 );
		}
		$summary['notice_html'] = new Check_Emails_Notice( $this->logger )->render( $summary );

		return new WP_REST_Response( $summary, 200 );
	}

	/**
	 * List configured email accounts.
	 *
	 * @return WP_REST_Response
	 */
	public function list_email_accounts(): WP_REST_Response {

		$accounts = array();
		foreach ( $this->mailboxes_api->get_email_accounts() as $account ) {
			$accounts[] = array(
				'id'            => $account->get_post_id(),
				'email_address' => $account->get_account_email_address(),
			);
		}

		return new WP_REST_Response( array( 'accounts' => $accounts ), 200 );
	}

	/**
	 * Add an email account via the bh-wp-mailboxes API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function create_email_account( WP_REST_Request $request ): WP_REST_Response {

		$email_address  = (string) $request->get_param( 'email_address' );
		$display_name   = (string) ( $request->get_param( 'display_name' ) ?? $email_address );
		$provider_param = (string) ( $request->get_param( 'provider_type_class' ) ?? 'imap' );
		$provider_class = self::PROVIDER_CLASSES[ $provider_param ] ?? $provider_param;

		// Test-only: start from a known state. bh-wp-mailboxes' duplicate-account check is unreliable
		// once any account exists (it filters by post_name, which WP_Query ignores), so remove any
		// existing accounts before adding this one.
		foreach ( $this->mailboxes_api->get_email_accounts() as $existing ) {
			wp_delete_post( $existing->get_post_id(), true );
		}

		try {
			$account = $this->mailboxes_api->configure_email_account(
				$email_address,
				$display_name,
				$provider_class,
				null,
				null,
				null,
				null
			);
		} catch ( \Exception $e ) {
			return new WP_REST_Response( array( 'error' => $e->getMessage() ), 400 );
		}

		return new WP_REST_Response(
			array(
				'id'            => $account->get_post_id(),
				'email_address' => $account->get_account_email_address(),
			),
			201
		);
	}
}
