<?php
/**
 * Unit tests for Aggregate_Unpaid_Orders_Provider.
 *
 * Documents that the aggregate skips providers whose backing plugin is unavailable and merges the
 * orders from those that are available — the mechanism that lets WooCommerce and GiveWP coexist.
 * The provider list is built and filtered on each use, so integrations registered on later hooks
 * are still picked up.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Mockery;
use Psr\Log\NullLogger;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\API\Aggregate_Unpaid_Orders_Provider
 */
class Aggregate_Unpaid_Orders_Provider_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		WP_Mock::setUp();
	}

	protected function _tearDown() {
		WP_Mock::tearDown();
		// WP_Mock::tearDown() does not reset the static used by onFilter()->withAnyArgs(), which
		// would otherwise leak the stubbed filter into later tests.
		$filters_with_any_args = new \ReflectionProperty( \WP_Mock\Filter::class, 'filtersWithAnyArgs' );
		$filters_with_any_args->setValue( null, array() );
		Mockery::close();
		parent::_tearDown();
	}

	protected function make_sut(): Aggregate_Unpaid_Orders_Provider {
		return new Aggregate_Unpaid_Orders_Provider(
			Mockery::mock( Email_Reconcile_Settings_Interface::class ),
			new NullLogger()
		);
	}

	/**
	 * @covers ::get_unpaid_orders
	 * @covers ::is_available
	 * @covers ::get_providers
	 */
	public function test_unavailable_providers_are_skipped_and_available_merged(): void {

		$available_order = Mockery::mock( Unpaid_Order::class );

		$available = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$available->shouldReceive( 'is_available' )->andReturn( true );
		$available->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array( $available_order ) );

		$unavailable = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$unavailable->shouldReceive( 'is_available' )->andReturn( false );
		$unavailable->shouldNotReceive( 'get_unpaid_orders' );

		WP_Mock::onFilter( 'bh_wp_order_email_reconcile_unpaid_orders_providers' )
			->withAnyArgs()
			->reply( array( $available, $unavailable ) );

		$sut = $this->make_sut();

		$this->assertTrue( $sut->is_available() );

		$result = $sut->get_unpaid_orders();
		$this->assertCount( 1, $result );
		$this->assertSame( $available_order, $result[0] );
	}

	/**
	 * @covers ::is_available
	 * @covers ::get_providers
	 */
	public function test_not_available_when_no_provider_is_available(): void {

		$unavailable = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$unavailable->shouldReceive( 'is_available' )->andReturn( false );

		WP_Mock::onFilter( 'bh_wp_order_email_reconcile_unpaid_orders_providers' )
			->withAnyArgs()
			->reply( array( $unavailable ) );

		$this->assertFalse( $this->make_sut()->is_available() );
	}

	/**
	 * With the provider list filtered empty, the aggregate is unavailable and returns no orders.
	 *
	 * @covers ::is_available
	 * @covers ::get_unpaid_orders
	 * @covers ::get_providers
	 */
	public function test_empty_provider_list(): void {

		WP_Mock::onFilter( 'bh_wp_order_email_reconcile_unpaid_orders_providers' )
			->withAnyArgs()
			->reply( array() );

		$sut = $this->make_sut();

		$this->assertFalse( $sut->is_available() );
		$this->assertSame( array(), $sut->get_unpaid_orders() );
	}

	/**
	 * Unfiltered, the aggregate builds the WooCommerce and GiveWP providers; with neither plugin
	 * loaded (their function_exists() checks fail here), it reports unavailable.
	 *
	 * @covers ::get_providers
	 * @covers ::is_available
	 */
	public function test_default_providers_unavailable_without_backing_plugins(): void {

		$this->assertFalse( $this->make_sut()->is_available() );
	}
}
