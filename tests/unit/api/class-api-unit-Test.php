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
	 * With no emails to process, the provider is never queried.
	 *
	 * @covers ::process_new_emails
	 */
	public function test_no_emails_returns_early(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldNotReceive( 'get_unpaid_orders' );

		$reconciler = Mockery::mock( Email_Reconciler::class );

		$sut = new API( $settings, $provider, $reconciler, new NullLogger() );

		$result = $sut->process_new_emails( array() );

		$this->assertSame( 0, $result['num_emails'] );
		$this->assertSame( 0, $result['reconciled'] );
	}

	/**
	 * With emails but no unpaid orders, nothing is reconciled.
	 *
	 * @covers ::process_new_emails
	 */
	public function test_emails_but_no_unpaid_orders(): void {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array() );

		$reconciler = Mockery::mock( Email_Reconciler::class );
		$reconciler->shouldNotReceive( 'reconcile_emails' );

		$sut = new API( $settings, $provider, $reconciler, new NullLogger() );

		// BH_Email is a readonly class and is never inspected on the no-unpaid-orders path; a
		// single placeholder element is enough to exercise the count.
		$result = $sut->process_new_emails( array( 'email-placeholder' ) );

		$this->assertSame( 1, $result['num_emails'] );
		$this->assertSame( 0, $result['num_unpaid_orders'] );
		$this->assertSame( 0, $result['reconciled'] );
	}
}
