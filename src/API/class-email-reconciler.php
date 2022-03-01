<?php
/**
 * Updates WooCommerce orders' statuses.
 * TODO Updates emails' read/unread status.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wc-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */

namespace BrianHenryIE\WC_Order_Email_Reconcile\API;

use BrianHenryIE\WC_Order_Email_Reconcile\API\Model\Email;
use BrianHenryIE\WC_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Creates an index of orders by customer id, email, name, then uses that to look up orders matching
 * email details and considers the order paid if the payment amount matches the order's total.
 *
 * Class Email_Reconciler
 *
 * @package brianhenryie/bh-wc-order-email-reconcile
 */
class Email_Reconciler {

	use LoggerAwareTrait;

	/**
	 * Try matching the orders by
	 * 1. The Venmo/Cash App... user id they provided at checkout.
	 * 2. The order email address. (not always in the email receipt).
	 * 3. The customer name
	 *
	 * Array(
	 *  'order_id' => array( $order_id => WC_Order )
	 *  'customer_id' => array( '$app customer id' => WC_Order[] )
	 *  'customer_email' => array( 'customer@example.org' => WC_Order[] )
	 *  'customer_name' => array( 'customer name' => WC_Order[] )
	 * )
	 *
	 * @var array
	 */
	protected array $orders_index = array();

	/**
	 * Required to get the customers id meta-key (Venmo username, $CashTag...) for matching.
	 *
	 * @var Email_Reconcile_Settings_Interface
	 */
	protected Email_Reconcile_Settings_Interface $settings;

	/**
	 * Email_Reconciler constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Settings, used to get the customer id.
	 * @param LoggerInterface                    $logger Logger.
	 */
	public function __construct( Email_Reconcile_Settings_Interface $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->settings = $settings;

		$this->orders_index['order_id']       = array();
		$this->orders_index['customer_id']    = array();
		$this->orders_index['customer_name']  = array();
		$this->orders_index['customer_email'] = array();
	}

	/**
	 * Loops through an array of emails, and attempts to reconcile them with the unpaid orders.
	 *
	 * @param Parsed_Email[] $emails Array of emails to reconcile.
	 *
	 * @return array{reconciled_emails:array<int,Parsed_Email>}
	 */
	public function reconcile_emails( array $emails ): array {

		$result = array(
			'reconciled_emails' => array(),
		);

		foreach ( $emails as $email ) {
			$reconciled_email_result = $this->reconcile_email( $email );
			if ( $reconciled_email_result['reconciled'] ) {
				$result['reconciled_emails'][ $reconciled_email_result['order_id'] ] = $email;
			}
		}

		return $result;
	}

	/**
	 * Index orders by each of:
	 * * billing_email
	 * * customer payment_id ($CashTag...)
	 * * customer name
	 *
	 * @param WC_Order[] $unpaid_orders Array of unpaid orders for this payment gateway.
	 */
	public function index_orders( array $unpaid_orders ): void {

		// Put the unpaid orders into an associative array, index by billing email.

		foreach ( $unpaid_orders as $order ) {

			// Order id, which they hopefully put the transaction note..
			$order_id                                    = $order->get_id();
			$this->orders_index['order_id'][ $order_id ] = $order;

			// Customer payment id ($CashTag...).
			$payment_id = $order->get_meta( $this->settings->get_customer_payment_id_meta_key() );
			if ( ! empty( $payment_id ) ) {
				$payment_id = strtolower( $payment_id );
				if ( ! isset( $this->orders_index['customer_id'][ $payment_id ] ) ) {
					$this->orders_index['customer_id'][ $payment_id ] = array();
				}
				$this->orders_index['customer_id'][ $payment_id ][ $order_id ] = $order;
			}

			// Customer email.
			$customer_email = strtolower( $order->get_billing_email() );
			if ( ! isset( $this->orders_index['customer_email'][ $customer_email ] ) ) {
				$this->orders_index['customer_email'][ $customer_email ] = array();
			}
			$this->orders_index['customer_email'][ $customer_email ][ $order_id ] = $order;

			// Customer name (from shipping).
			$customer_name_shipping = strtolower( "{$order->get_shipping_first_name()} {$order->get_shipping_last_name()}" );
			if ( ! isset( $this->orders_index['customer_name'][ $customer_name_shipping ] ) ) {
				$this->orders_index['customer_name'][ $customer_name_shipping ] = array();
			}
			$this->orders_index['customer_name'][ $customer_name_shipping ][ $order_id ] = $order;

			// Customer name (from billing).
			$customer_name_billing = strtolower( "{$order->get_billing_first_name()} {$order->get_billing_last_name()}" );
			if ( $customer_name_billing !== $customer_name_shipping ) {
				if ( ! isset( $this->orders_index['customer_name'][ $customer_name_billing ] ) ) {
					$this->orders_index['customer_name'][ $customer_name_billing ] = array();
				}
				$this->orders_index['customer_name'][ $customer_name_billing ][ $order_id ] = $order;
			}
		}
	}


	/**
	 * Takes an email and searches its customer_id (e.g. Venmo username), billing email, and customer name
	 * for unpaid orders.
	 *
	 * @param Email $email Array with values found in the email body by the regex search as `email_details` key.
	 *
	 * @return array{reconciled:bool, order_id?:int}
	 */
	public function reconcile_email( Parsed_Email $parsed_email ): array {

		$result = array(
			'reconciled' => false,
			'order_id'   => null,
		);

		$email = $parsed_email; // ->get_bh_email();

		$this->logger->debug( __FUNCTION__, array( 'parsed_email' => $parsed_email ) );

		foreach ( $parsed_email->get_order_id_from_notes() as $order_id ) {
			if ( isset( $this->orders_index['order_id'][ $order_id ] ) ) {
				/** @var WC_Order $order */
				$order               = $this->orders_index['order_id'][ $order_id ];
				$is_matched_to_order = $this->match_email_to_order( $email, $order );

				if ( $is_matched_to_order ) {
					$this->logger->info( 'Email matched by order id to order ' . $order->get_id() );

					$result = array(
						'reconciled' => true,
						'order_id'   => $order->get_id(),
					);

					return $result;
				}
			}
		}

		// Already lowercase.
		$customer_id = $parsed_email->get_customer_id();
		if ( ! empty( $customer_id ) && isset( $this->orders_index['customer_id'][ $customer_id ] ) ) {

			foreach ( $this->orders_index['customer_id'][ $customer_id ] as $key => $order ) {

				$is_matched_to_order = $this->match_email_to_order( $email, $order );

				if ( $is_matched_to_order ) {

					$this->logger->info( 'Email matched by customer id to order ' . $order->get_id() );

					$result = array(
						'reconciled' => true,
						'order_id'   => $order->get_id(),
					);

					return $result;             }
			}
		}

		// Not all payment emails will have the customer's email (just their username on that platform).
		$customer_email = $parsed_email->get_customer_email();
		if ( ! empty( $customer_email ) && isset( $this->orders_index['customer_email'][ $customer_email ] ) ) {

			foreach ( $this->orders_index['customer_email'][ $customer_email ] as $key => $order ) {

				$is_matched_to_order = $this->match_email_to_order( $email, $order );

				if ( $is_matched_to_order ) {

					$this->logger->info( 'Email matched by customer email to order ' . $order->get_id() );

					$result = array(
						'reconciled' => true,
						'order_id'   => $order->get_id(),
					);

					return $result;             }
			}
		}

		$customer_name = strtolower( $parsed_email->get_customer_name() );
		if ( ! empty( $customer_name ) && isset( $this->orders_index['customer_name'][ $customer_name ] ) ) {

			foreach ( $this->orders_index['customer_name'][ $customer_name ] as $key => $order ) {

				$is_matched_to_order = $this->match_email_to_order( $email, $order );

				if ( $is_matched_to_order ) {

					$this->logger->info( 'Email matched by customer name to order ' . $order->get_id() );

					$result = array(
						'reconciled' => true,
						'order_id'   => $order->get_id(),
					);

					return $result;
				}
			}
		}

		$this->logger->debug(
			'Unable to reconcile email',
			array(
				'parsed_email' => $parsed_email,
			)
		);

		return $result;
	}

	/**
	 * Checks the order is unpaid, the total matches the email's total, then marks the order
	 * paid and adds the meta data, finally marking the email read (/delete/nothing).
	 *
	 * @param Parsed_Email $parsed_email The payment email needing a matching order.
	 * @param WC_Order     $order An email that potentially matches this email.
	 *
	 * @return bool
	 */
	protected function match_email_to_order( Parsed_Email $parsed_email, WC_Order $order ): bool {

		// TODO: Check the payment was made after the order.

		if ( ! $order->is_paid() && $order->get_total() === $parsed_email->get_amount() ) {

			$notes = "Auto-reconciled <br/>\n";

			$transaction_meta_and_notes = $parsed_email->get_notes();

			foreach ( $transaction_meta_and_notes as $name => $note ) {

				$order->add_meta_data( $name, $note );

				// If you want a notes ['item'] string to link somewhere, add a corresponding ['item_href'] regex.
				if ( false !== strpos( $name, '_href' ) ) {
					continue;
				}

				if ( isset( $transaction_meta_and_notes[ $name . '_href' ] ) ) {
					$href   = $transaction_meta_and_notes[ $name . '_href' ];
					$notes .= "$name: <a target=\"_blank\" href=\"$href\">$note</a><br/>\n";

					$order->add_meta_data( $name . '_href', $href );

				} else {
					$notes .= "<em>$name</em> $note<br/>\n";
				}
			}

			$transaction_id = $parsed_email->get_transaction_id() ?? '';

			$order->payment_complete( $transaction_id );

			$order->add_order_note( $notes );

			/**
			 * @see WC_Payment_Gateway::get_transaction_url() This function should be overridden to return this meta data.
			 * @see WC_Payment_Gateway::$view_transaction_url This is a template for sprintf.
			 * @see woocommerce_get_transaction_url Or this filter can be used.
			 */
			if ( ! empty( $parsed_email->get_transaction_url() ) ) {
				$order->add_meta_data( 'transaction_url', $parsed_email->get_transaction_url() );
			}

			$order->save();

			// TODO:
			// $parsed_email->after_reconcile();

			// Return here so two are not marked as paid by one email!

			return true;
		}

		return false;
	}

}
