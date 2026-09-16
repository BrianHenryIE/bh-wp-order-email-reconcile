<?php
/**
 * Unit tests for Email_Reconciler, exercised entirely against the Unpaid_Order abstraction.
 *
 * These tests document the contract the abstraction must satisfy: the reconciler reads matching
 * keys off Unpaid_Order and, on a match, calls mark_paid()/add_note()/save() — with no knowledge
 * of WooCommerce or any other integration.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Mailboxes\Models\BH_Email_Fixture;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Mailboxes\API\Model\BH_Email;
use Mockery;
use Psr\Log\NullLogger;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\API\Email_Reconciler
 */
class Email_Reconciler_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'esc_html' )->andReturnUsing(
			function ( $text ) {
				return $text;
			}
		);
		WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * A payment email whose customer payment id and amount match an unpaid order is reconciled,
	 * and the order is marked paid and saved.
	 *
	 * @covers ::index_orders
	 * @covers ::reconcile_email
	 */
	public function test_match_by_customer_payment_id(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$sut      = new Email_Reconciler( $settings, new NullLogger() );

		$order = Mockery::mock( Unpaid_Order::class );
		$order->shouldReceive( 'get_order_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_customer_payment_id' )->andReturn( 'my_cashtag' );
		$order->shouldReceive( 'get_email_address' )->andReturn( 'customer@example.org' );
		$order->shouldReceive( 'get_customer_names' )->andReturn( array( 'firstname lastname' ) );
		$order->shouldReceive( 'is_paid' )->andReturn( false );
		$order->shouldReceive( 'get_amount' )->andReturn( '99.99' );
		$order->shouldReceive( 'get_integration' )->andReturn( 'woocommerce' );
		// The reconciliation side effects we expect on a match.
		$order->shouldReceive( 'mark_paid' )->once()->with( 'transaction_id_axby' );
		$order->shouldReceive( 'add_note' )->once();
		$order->shouldReceive( 'add_meta' );
		$order->shouldReceive( 'save' )->once();

		$sut->index_orders( array( $order ) );

		$parsed_email = Mockery::mock( Parsed_Email::class );
		$parsed_email->shouldReceive( 'get_order_id_from_notes' )->andReturn( array() );
		$parsed_email->shouldReceive( 'get_customer_id' )->andReturn( 'my_cashtag' );
		$parsed_email->shouldReceive( 'get_customer_email' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_customer_name' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_amount' )->andReturn( '99.99' );
		$parsed_email->shouldReceive( 'get_notes' )->andReturn( array() );
		$parsed_email->shouldReceive( 'get_transaction_id' )->andReturn( 'transaction_id_axby' );
		$parsed_email->shouldReceive( 'get_transaction_url' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_bh_email' )->andReturn( BH_Email_Fixture::create() );

		$result = $sut->reconcile_email( $parsed_email );

		$this->assertTrue( $result['reconciled'] );
		$this->assertSame( 123, $result['order_id'] );
	}

	/**
	 * An order already paid is never reconciled, even when the keys match.
	 *
	 * @covers ::reconcile_email
	 */
	public function test_paid_order_is_not_reconciled(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$sut      = new Email_Reconciler( $settings, new NullLogger() );

		$order = Mockery::mock( Unpaid_Order::class );
		$order->shouldReceive( 'get_order_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_customer_payment_id' )->andReturn( 'my_cashtag' );
		$order->shouldReceive( 'get_email_address' )->andReturn( 'customer@example.org' );
		$order->shouldReceive( 'get_customer_names' )->andReturn( array() );
		$order->shouldReceive( 'is_paid' )->andReturn( true );
		$order->shouldReceive( 'get_amount' )->andReturn( '99.99' );
		$order->shouldNotReceive( 'mark_paid' );
		$order->shouldNotReceive( 'save' );

		$sut->index_orders( array( $order ) );

		$parsed_email = Mockery::mock( Parsed_Email::class );
		$parsed_email->shouldReceive( 'get_order_id_from_notes' )->andReturn( array() );
		$parsed_email->shouldReceive( 'get_customer_id' )->andReturn( 'my_cashtag' );
		$parsed_email->shouldReceive( 'get_customer_email' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_customer_name' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_amount' )->andReturn( '99.99' );

		$result = $sut->reconcile_email( $parsed_email );

		$this->assertFalse( $result['reconciled'] );
		$this->assertNull( $result['order_id'] );
	}

	/**
	 * Method `::reconcile_emails()` returns the reconciled emails keyed by order id.
	 *
	 * @covers ::reconcile_emails
	 */
	public function test_reconcile_emails_returns_stats_keyed_by_order_id(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$sut      = new Email_Reconciler( $settings, new NullLogger() );

		$order = Mockery::mock( Unpaid_Order::class );
		$order->shouldReceive( 'get_order_id' )->andReturn( 123 );
		$order->shouldReceive( 'get_customer_payment_id' )->andReturn( 'my_cashtag' );
		$order->shouldReceive( 'get_email_address' )->andReturn( 'customer@example.org' );
		$order->shouldReceive( 'get_customer_names' )->andReturn( array() );
		$order->shouldReceive( 'is_paid' )->andReturn( false );
		$order->shouldReceive( 'get_amount' )->andReturn( '99.99' );
		$order->shouldReceive( 'get_integration' )->andReturn( 'woocommerce' );
		$order->shouldReceive( 'mark_paid' )->once();
		$order->shouldReceive( 'add_note' )->once();
		$order->shouldReceive( 'add_meta' );
		$order->shouldReceive( 'save' )->once();

		$sut->index_orders( array( $order ) );

		$parsed_email = Mockery::mock( Parsed_Email::class );
		$parsed_email->shouldReceive( 'get_order_id_from_notes' )->andReturn( array() );
		$parsed_email->shouldReceive( 'get_customer_id' )->andReturn( 'my_cashtag' );
		$parsed_email->shouldReceive( 'get_customer_email' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_customer_name' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_amount' )->andReturn( '99.99' );
		$parsed_email->shouldReceive( 'get_notes' )->andReturn( array() );
		$parsed_email->shouldReceive( 'get_transaction_id' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_transaction_url' )->andReturn( null );
		$parsed_email->shouldReceive( 'get_bh_email' )->andReturn( BH_Email_Fixture::create() );

		$result = $sut->reconcile_emails( array( $parsed_email ) );

		$this->assertArrayHasKey( 'reconciled_emails', $result );
		$this->assertCount( 1, $result['reconciled_emails'] );
		$this->assertArrayHasKey( 123, $result['reconciled_emails'] );
	}
}
