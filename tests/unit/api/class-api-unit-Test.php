<?php
/**
 * Unit tests for the core API.
 *
 * The API depends only on the Unpaid_Orders_Provider_Interface and Email_Reconciler, never on a
 * concrete integration. These tests assert that contract.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Mailboxes\Models\BH_Email_Fixture;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Parsed_Email;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Mockery;
use Psr\Log\NullLogger;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\API\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'add_action' );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * With no unpaid orders, the email is not parsed or reconciled.
	 *
	 * @covers ::process_new_email
	 */
	public function test_no_unpaid_orders(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array() );

		$reconciler = Mockery::mock( Email_Reconciler::class );
		$reconciler->shouldNotReceive( 'reconcile_email' );

		$sut = new API( $settings, $provider, $reconciler, new NullLogger() );

		$result = $sut->process_new_email( BH_Email_Fixture::create( body_plain_text: 'Payment of $1.00', body_html: '' ) );

		$this->assertSame( 0, $result['num_unpaid_orders'] );
		$this->assertFalse( $result['reconciled'] );
		$this->assertNull( $result['order_id'] );
	}

	/**
	 * With unpaid orders, the email is parsed and handed to the reconciler, whose result is reported.
	 *
	 * @covers ::process_new_email
	 */
	public function test_email_reconciled_against_unpaid_orders(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );
		$settings->shouldReceive( 'get_patterns' )->andReturn( array() );

		$order = Mockery::mock( Unpaid_Order::class );

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array( $order ) );

		$reconciler = Mockery::mock( Email_Reconciler::class );
		$reconciler->shouldReceive( 'index_orders' )->once()->with( array( $order ) );
		$reconciler->shouldReceive( 'reconcile_email' )->once()->with( Mockery::type( Parsed_Email::class ) )->andReturn(
			array(
				'reconciled' => true,
				'order_id'   => 123,
			)
		);

		$sut = new API( $settings, $provider, $reconciler, new NullLogger() );

		$result = $sut->process_new_email( BH_Email_Fixture::create( body_plain_text: 'Payment of $1.00', body_html: '' ) );

		$this->assertSame( 1, $result['num_unpaid_orders'] );
		$this->assertTrue( $result['reconciled'] );
		$this->assertSame( 123, $result['order_id'] );
	}
}
