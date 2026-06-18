<?php
/**
 * Get the unpaid WooCommerce orders for the payment gateway ids specified in the settings.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wc-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Countable;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Uses wc_get_orders() to get pertinent orders.
 *
 * Class Unpaid_Orders
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */
class Unpaid_Orders implements Countable {
	use LoggerAwareTrait;

	/**
	 * The unpaid orders for this payment gateway.
	 *
	 * @var WC_Order[]
	 */
	protected array $unpaid_orders;

	/**
	 *
	 * Unpaid_Orders constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Settings to indicate what payment method ids unpaid orders should be searched for.
	 * @param LoggerInterface                    $logger Logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Get the unpaid orders and sort them into arrays keyed by customer name/email/id.
	 *
	 * @return WC_Order[]
	 */
	public function get_unpaid_orders(): array {

		if(is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$wc_unpaid_order_service = new WC_Unpaid_Orders( $this->settings, $this->logger);
			$wc_unpaid_orders = $wc_unpaid_order_service->get_unpaid_orders();
		}

		/**
		 * Get unpaid orders.
		 *
		 * @var WC_Order[] $unpaid_orders
		 */
		$this->unpaid_orders = wc_get_orders( $args );

		$this->logger->info( 'Found ' . count( $this->unpaid_orders ) . ' unpaid orders.' );

		return $this->unpaid_orders;
	}

	/**
	 * The count of unpaid WooCommerce orders.
	 *
	 * @see Countable
	 *
	 * @return int The number of unpaid orders.
	 */
	public function count(): int {
		if ( ! isset( $this->unpaid_orders ) ) {
			$this->get_unpaid_orders();
		}

		return count( $this->unpaid_orders );
	}
}
