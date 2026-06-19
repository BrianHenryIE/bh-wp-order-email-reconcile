<?php
/**
 * Integration tests for the WooCommerce provider and order wrapper. Requires WooCommerce active.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Orders_Provider
 */
class WC_Unpaid_Orders_Provider_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * Only unpaid orders for the configured gateways are returned, wrapped as Unpaid_Order objects.
	 *
	 * @covers ::get_unpaid_orders
	 */
	public function test_returns_only_unpaid_orders_for_configured_gateways(): void {

		$settings = $this->makeEmpty(
			Email_Reconcile_Settings_Interface::class,
			array(
				'get_payment_method_ids'           => array( 'my-gateway-id', 'my-gateway-id-2' ),
				'get_customer_payment_id_meta_key' => null,
			)
		);

		$sut = new WC_Unpaid_Orders_Provider( $settings, new ColorLogger() );

		// Wrong gateway → excluded.
		$o1 = new \WC_Order();
		$o1->set_status( 'on-hold' );
		$o1->save();

		// Correct gateway, on-hold → included.
		$o2 = new \WC_Order();
		$o2->set_status( 'on-hold' );
		$o2->set_payment_method( 'my-gateway-id' );
		$o2->save();

		// Correct gateway, pending → included.
		$o3 = new \WC_Order();
		$o3->set_status( 'pending' );
		$o3->set_payment_method( 'my-gateway-id-2' );
		$o3->save();

		// Completed (paid) → excluded.
		$o4 = new \WC_Order();
		$o4->set_status( 'completed' );
		$o4->set_payment_method( 'my-gateway-id' );
		$o4->save();

		// Failed → excluded.
		$o5 = new \WC_Order();
		$o5->set_status( 'failed' );
		$o5->set_payment_method( 'my-gateway-id' );
		$o5->save();

		$result = $sut->get_unpaid_orders();

		$this->assertCount( 2, $result );
		$this->assertContainsOnlyInstancesOf( Unpaid_Order::class, $result );
	}

	/**
	 * The WC_Unpaid_Order wrapper maps a WC_Order onto the Unpaid_Order interface, and mark_paid()
	 * makes WooCommerce treat the order as paid.
	 *
	 * @covers \BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Order
	 */
	public function test_wc_unpaid_order_maps_and_marks_paid(): void {

		$order = new \WC_Order();
		$order->set_status( 'on-hold' );
		$order->set_billing_email( 'Customer@Example.org' );
		$order->set_billing_first_name( 'First' );
		$order->set_billing_last_name( 'Last' );
		$order->set_total( '12.34' );
		$order->save();

		$wrapped = new WC_Unpaid_Order( $order, null );

		$this->assertSame( 'woocommerce', $wrapped->get_integration() );
		$this->assertSame( $order->get_id(), $wrapped->get_order_id() );
		$this->assertSame( '12.34', $wrapped->get_amount() );
		$this->assertSame( 'customer@example.org', $wrapped->get_email_address() );
		$this->assertContains( 'first last', $wrapped->get_customer_names() );
		$this->assertFalse( $wrapped->is_paid() );

		$wrapped->mark_paid( 'txn_123' );
		$wrapped->save();

		$reloaded = wc_get_order( $order->get_id() );
		$this->assertTrue( $reloaded->is_paid() );
		$this->assertSame( 'txn_123', $reloaded->get_transaction_id() );
	}

	/**
	 * get_customer_payment_id() reads the configured meta key, lowercased, and is null when absent.
	 *
	 * @covers \BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce\WC_Unpaid_Order::get_customer_payment_id
	 */
	public function test_get_customer_payment_id_from_meta_key(): void {

		$order = new \WC_Order();
		$order->set_status( 'on-hold' );
		$order->update_meta_data( '_venmo_username', 'JaneDoe' );
		$order->save();

		// Configured meta key present → returned lowercased.
		$with_meta = new WC_Unpaid_Order( $order, '_venmo_username' );
		$this->assertSame( 'janedoe', $with_meta->get_customer_payment_id() );

		// No meta key configured → null.
		$no_key = new WC_Unpaid_Order( $order, null );
		$this->assertNull( $no_key->get_customer_payment_id() );

		// Configured key but no value on the order → null.
		$empty_order = new \WC_Order();
		$empty_order->set_status( 'on-hold' );
		$empty_order->save();
		$missing = new WC_Unpaid_Order( $empty_order, '_venmo_username' );
		$this->assertNull( $missing->get_customer_payment_id() );
	}
}
