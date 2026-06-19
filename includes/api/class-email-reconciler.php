<?php
/**
 * Matches parsed payment emails to unpaid orders and marks the matched orders paid.
 *
 * Operates entirely on the Unpaid_Order abstraction; it has no knowledge of WooCommerce, GiveWP,
 * or any other integration.
 *
 * @link       https://GitHub.com/BrianHenryIE/bh-wp-order-email-reconcile
 * @since      1.0.0
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Creates an index of orders by id, customer payment id, email and name, then uses that to look up
 * orders matching email details and considers an order paid when the payment amount matches the
 * order's total.
 */
class Email_Reconciler {

	use LoggerAwareTrait;

	/**
	 * Index of unpaid orders keyed by the various values that may appear in a payment email.
	 *
	 * Shape:
	 *  'order_id'       => array<int,    Unpaid_Order>
	 *  'customer_id'    => array<string, array<int, Unpaid_Order>>
	 *  'customer_email' => array<string, array<int, Unpaid_Order>>
	 *  'customer_name'  => array<string, array<int, Unpaid_Order>>
	 *
	 * @var array<string, array<int|string, mixed>>
	 */
	protected array $orders_index = array(
		'order_id'       => array(),
		'customer_id'    => array(),
		'customer_email' => array(),
		'customer_name'  => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings Settings (reserved for future matching rules).
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Index orders by id, customer payment id ($CashTag...), billing email and customer name(s).
	 *
	 * @param Unpaid_Order[] $unpaid_orders Array of unpaid orders to reconcile.
	 */
	public function index_orders( array $unpaid_orders ): void {

		foreach ( $unpaid_orders as $order ) {

			$order_id = $order->get_order_id();

			$this->orders_index['order_id'][ $order_id ] = $order;

			$payment_id = $order->get_customer_payment_id();
			if ( ! empty( $payment_id ) ) {
				$this->orders_index['customer_id'][ $payment_id ][ $order_id ] = $order;
			}

			$customer_email = $order->get_email_address();
			if ( ! empty( $customer_email ) ) {
				$this->orders_index['customer_email'][ $customer_email ][ $order_id ] = $order;
			}

			foreach ( $order->get_customer_names() as $customer_name ) {
				$this->orders_index['customer_name'][ $customer_name ][ $order_id ] = $order;
			}
		}
	}

	/**
	 * Loops through an array of parsed emails and attempts to reconcile each with the unpaid orders.
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
				$result['reconciled_emails'][ (int) $reconciled_email_result['order_id'] ] = $email;
			}
		}

		return $result;
	}

	/**
	 * Searches the order-id (from notes), customer payment id, billing email and customer name
	 * indexes for an unpaid order matching this email.
	 *
	 * @param Parsed_Email $parsed_email The values found in the email body by the regex search.
	 *
	 * @return array{reconciled:bool, order_id:?int}
	 */
	public function reconcile_email( Parsed_Email $parsed_email ): array {

		$this->logger->debug( __FUNCTION__, array( 'parsed_email' => $parsed_email ) );

		// 1. Order id parsed from the payment note.
		foreach ( $parsed_email->get_order_id_from_notes() as $order_id ) {
			if ( isset( $this->orders_index['order_id'][ $order_id ] ) ) {
				$order = $this->orders_index['order_id'][ $order_id ];
				if ( $this->match_email_to_order( $parsed_email, $order ) ) {
					return $this->matched( 'order id', $order );
				}
			}
		}

		// 2. Customer payment id (already lowercase).
		$customer_id = $parsed_email->get_customer_id();
		if ( ! empty( $customer_id ) && isset( $this->orders_index['customer_id'][ $customer_id ] ) ) {
			foreach ( $this->orders_index['customer_id'][ $customer_id ] as $order ) {
				if ( $this->match_email_to_order( $parsed_email, $order ) ) {
					return $this->matched( 'customer id', $order );
				}
			}
		}

		// 3. Customer email (not always present in a payment email).
		$customer_email = strtolower( (string) $parsed_email->get_customer_email() );
		if ( ! empty( $customer_email ) && isset( $this->orders_index['customer_email'][ $customer_email ] ) ) {
			foreach ( $this->orders_index['customer_email'][ $customer_email ] as $order ) {
				if ( $this->match_email_to_order( $parsed_email, $order ) ) {
					return $this->matched( 'customer email', $order );
				}
			}
		}

		// 4. Customer name.
		$customer_name = strtolower( (string) $parsed_email->get_customer_name() );
		if ( ! empty( $customer_name ) && isset( $this->orders_index['customer_name'][ $customer_name ] ) ) {
			foreach ( $this->orders_index['customer_name'][ $customer_name ] as $order ) {
				if ( $this->match_email_to_order( $parsed_email, $order ) ) {
					return $this->matched( 'customer name', $order );
				}
			}
		}

		$this->logger->debug( 'Unable to reconcile email', array( 'parsed_email' => $parsed_email ) );

		return array(
			'reconciled' => false,
			'order_id'   => null,
		);
	}

	/**
	 * Helper to log a successful match and build the result array.
	 *
	 * @param string       $matched_by Human-readable description of which index matched.
	 * @param Unpaid_Order $order      The matched order.
	 *
	 * @return array{reconciled:bool, order_id:int}
	 */
	protected function matched( string $matched_by, Unpaid_Order $order ): array {
		$this->logger->info( "Email matched by {$matched_by} to order " . $order->get_order_id() );
		return array(
			'reconciled' => true,
			'order_id'   => $order->get_order_id(),
		);
	}

	/**
	 * Checks the order is unpaid and the total matches the email amount, then marks the order paid
	 * and records the transaction notes and metadata.
	 *
	 * @param Parsed_Email $parsed_email The payment email needing a matching order.
	 * @param Unpaid_Order $order        A candidate order to match against this email.
	 */
	protected function match_email_to_order( Parsed_Email $parsed_email, Unpaid_Order $order ): bool {

		// TODO: Check the payment was made after the order was placed.

		if ( $order->is_paid() || $order->get_amount() !== $parsed_email->get_amount() ) {
			return false;
		}

		$notes = "Auto-reconciled <br/>\n";

		$transaction_meta_and_notes = $parsed_email->get_notes();

		foreach ( $transaction_meta_and_notes as $name => $note ) {

			$order->add_meta( $name, $note );

			// A '..._href' entry provides the link target for its sibling note; do not print it as its own line.
			if ( false !== strpos( $name, '_href' ) ) {
				continue;
			}

			if ( isset( $transaction_meta_and_notes[ $name . '_href' ] ) ) {
				$href   = $transaction_meta_and_notes[ $name . '_href' ];
				$notes .= "$name: <a target=\"_blank\" href=\"$href\">$note</a><br/>\n";
				$order->add_meta( $name . '_href', $href );
			} else {
				$notes .= "<em>$name</em> $note<br/>\n";
			}
		}

		$transaction_id = $parsed_email->get_transaction_id() ?? '';

		$order->mark_paid( $transaction_id );
		$order->add_note( $notes );

		if ( ! empty( $parsed_email->get_transaction_url() ) ) {
			$order->add_meta( 'transaction_url', $parsed_email->get_transaction_url() );
		}

		$order->save();

		// TODO: $parsed_email->after_reconcile(); (mark email read / delete per settings).

		// Return so a single email cannot mark two orders paid.
		return true;
	}
}
