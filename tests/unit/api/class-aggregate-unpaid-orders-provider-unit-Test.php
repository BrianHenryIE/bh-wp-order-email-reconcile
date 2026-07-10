<?php
/**
 * Unit tests for Aggregate_Unpaid_Orders_Provider.
 *
 * Documents that the aggregate skips providers whose backing plugin is unavailable and merges the
 * orders from those that are available — the mechanism that lets WooCommerce and GiveWP coexist.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use Mockery;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\API\Aggregate_Unpaid_Orders_Provider
 */
class Aggregate_Unpaid_Orders_Provider_Unit_Test extends \Codeception\Test\Unit {

	protected function _tearDown() {
		Mockery::close();
		parent::_tearDown();
	}

	/**
	 * @covers ::get_unpaid_orders
	 * @covers ::is_available
	 */
	public function test_unavailable_providers_are_skipped_and_available_merged(): void {

		$available_order = Mockery::mock( Unpaid_Order::class );

		$available = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$available->shouldReceive( 'is_available' )->andReturn( true );
		$available->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array( $available_order ) );

		$unavailable = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$unavailable->shouldReceive( 'is_available' )->andReturn( false );
		$unavailable->shouldNotReceive( 'get_unpaid_orders' );

		$sut = new Aggregate_Unpaid_Orders_Provider( array( $available, $unavailable ), new NullLogger() );

		$this->assertTrue( $sut->is_available() );

		$result = $sut->get_unpaid_orders();
		$this->assertCount( 1, $result );
		$this->assertSame( $available_order, $result[0] );
	}

	/**
	 * @covers ::is_available
	 */
	public function test_not_available_when_no_provider_is_available(): void {

		$unavailable = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$unavailable->shouldReceive( 'is_available' )->andReturn( false );

		$sut = new Aggregate_Unpaid_Orders_Provider( array( $unavailable ), new NullLogger() );

		$this->assertFalse( $sut->is_available() );
	}

	/**
	 * With no providers registered the aggregate is unavailable and returns no orders.
	 *
	 * @covers ::is_available
	 * @covers ::get_unpaid_orders
	 */
	public function test_empty_provider_list(): void {

		$sut = new Aggregate_Unpaid_Orders_Provider( array(), new NullLogger() );

		$this->assertFalse( $sut->is_available() );
		$this->assertSame( array(), $sut->get_unpaid_orders() );
	}
}
