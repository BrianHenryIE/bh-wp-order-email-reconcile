<?php
/**
 * Supplies unpaid GiveWP donations as Unpaid_Order objects.
 *
 * SCAFFOLD: this demonstrates the extension point opened up by the Unpaid_Orders_Provider_Interface.
 * The GiveWP query API has not been exercised here and needs verification + wpunit coverage before
 * it can be relied on. See ARCHITECTURE.md "Adding an integration".
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\GiveWP;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Supplies unpaid GiveWP donations as Unpaid_Order objects.
 */
class Give_Unpaid_Orders_Provider implements Unpaid_Orders_Provider_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Settings.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Available when GiveWP is loaded.
	 */
	public function is_available(): bool {
		return function_exists( 'give' );
	}

	/**
	 * Get the pending GiveWP donations wrapped as Unpaid_Order objects.
	 *
	 * @return Unpaid_Order[]
	 */
	public function get_unpaid_orders(): array {

		if ( ! $this->is_available() || ! function_exists( 'give_get_payments' ) ) {
			$this->logger->debug( 'GiveWP not available; returning no unpaid orders.' );
			return array();
		}

		$args = array(
			'status' => $this->get_unpaid_donation_statuses(),
			'number' => -1,
		);

		$this->logger->debug( 'Querying GiveWP for unpaid donations.', array( 'args' => $args ) );

		// phpcs:ignore Generic.Commenting.DocComment.MissingShort
		/** @var array<object{ID:int}> $payments */
		$payments = give_get_payments( $args );

		$customer_payment_id_meta_key = $this->settings->get_customer_payment_id_meta_key();

		$unpaid_orders = array();
		foreach ( $payments as $payment ) {
			$unpaid_orders[] = new Give_Unpaid_Order(
				new \Give_Payment( (int) $payment->ID ),
				$customer_payment_id_meta_key
			);
		}

		$this->logger->info( 'Found ' . count( $unpaid_orders ) . ' unpaid GiveWP donations.' );

		return $unpaid_orders;
	}

	/**
	 * Donation statuses considered "unpaid" when searching for donations to reconcile.
	 *
	 * @return string[]
	 */
	protected function get_unpaid_donation_statuses(): array {

		$unpaid_donation_statuses = array( 'pending', 'abandoned' );

		/**
		 * Filter the GiveWP donation statuses considered "unpaid".
		 *
		 * @param string[] $unpaid_donation_statuses GiveWP donation status keys.
		 */
		return apply_filters( 'bh_wp_order_email_reconcile_give_unpaid_donation_statuses', $unpaid_donation_statuses );
	}
}
