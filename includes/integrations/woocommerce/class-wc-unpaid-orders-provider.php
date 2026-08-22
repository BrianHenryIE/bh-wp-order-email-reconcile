<?php
/**
 * Supplies unpaid WooCommerce orders, filtered to the configured payment gateway ids.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Uses wc_get_orders() to fetch orders that are not yet paid for the configured gateways, and wraps
 * each in a WC_Unpaid_Order so the core never sees a WC_Order.
 */
class WC_Unpaid_Orders_Provider implements Unpaid_Orders_Provider_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Indicates the payment method ids to search for and the customer payment id meta key.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Available when WooCommerce is loaded.
	 */
	public function is_available(): bool {
		return function_exists( 'wc_get_orders' );
	}

	/**
	 * Get the unpaid orders for the configured gateways, wrapped as Unpaid_Order objects.
	 *
	 * @return Unpaid_Order[]
	 */
	public function get_unpaid_orders(): array {

		if ( ! $this->is_available() ) {
			$this->logger->debug( 'WooCommerce not available; returning no unpaid orders.' );
			return array();
		}

		$args = array(
			'limit'          => -1,
			'status'         => $this->get_unpaid_order_statuses(),
			'payment_method' => $this->settings->get_payment_method_ids(),
		);

		$this->logger->debug( 'Querying WooCommerce for unpaid orders.', array( 'args' => $args ) );

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var WC_Order[] $wc_orders */
		$wc_orders = wc_get_orders( $args );

		$customer_payment_id_meta_key = $this->settings->get_customer_payment_id_meta_key();

		$unpaid_orders = array();
		foreach ( $wc_orders as $wc_order ) {
			$unpaid_orders[] = new WC_Unpaid_Order( $wc_order, $customer_payment_id_meta_key );
		}

		$this->logger->info( 'Found ' . count( $unpaid_orders ) . ' unpaid WooCommerce orders.' );

		return $unpaid_orders;
	}

	/**
	 * All registered order statuses minus the paid/failed/refunded/cancelled statuses.
	 *
	 * @return string[] Statuses without the `wc-` prefix.
	 */
	protected function get_unpaid_order_statuses(): array {

		// `wc-` prefixed list of statuses.
		$all_order_statuses = array_keys( wc_get_order_statuses() );
		// Not `wc-` prefixed list of statuses.
		$paid_statuses         = wc_get_is_paid_statuses();
		$uninterested_statuses = array_merge( $paid_statuses, array( 'failed', 'refunded', 'cancelled' ) );

		$unpaid_order_statuses = array();
		foreach ( $all_order_statuses as $order_status ) {
			$order_status = substr( $order_status, 3 );
			if ( in_array( $order_status, $uninterested_statuses, true ) ) {
				continue;
			}
			$unpaid_order_statuses[] = $order_status;
		}

		/**
		 * Filter the order statuses considered "unpaid" when searching for orders to reconcile.
		 *
		 * @param string[] $unpaid_order_statuses Statuses without the `wc-` prefix.
		 * @param string   $plugin_slug
		 */
		return apply_filters( 'bh_wp_order_email_reconcile_unpaid_order_statuses', $unpaid_order_statuses, $this->settings->get_plugin_slug() );
	}
}
