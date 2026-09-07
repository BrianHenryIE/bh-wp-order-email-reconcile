<?php
/**
 * Unit tests for Aggregate_Unpaid_Orders_Provider.
 *
 * Documents that the aggregate skips providers whose backing plugin is unavailable and merges the
 * orders from those that are available — the mechanism that lets WooCommerce and GiveWP coexist.
 * The provider list is built and filtered on each use, so integrations registered on later hooks
 * are still picked up.
 *
 * The merge/skip tests inject providers by overriding get_providers() (WP_Mock's onFilter cannot
 * match arguments containing unpredictable object instances); the unfiltered default list is
 * exercised by test_default_providers_unavailable_without_backing_plugins().
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
		Mockery::close();
		parent::_tearDown();
	}

	protected function make_settings(): Email_Reconcile_Settings_Interface {
		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );

		return $settings;
	}

	/**
	 * An aggregate whose provider list is the given array, bypassing the default providers and the
	 * filter (whose arguments contain unpredictable object instances WP_Mock cannot match).
	 *
	 * @param Unpaid_Orders_Provider_Interface[] $providers The providers to aggregate.
	 */
	protected function make_sut( array $providers ): Aggregate_Unpaid_Orders_Provider {
		return new class( $providers, $this->make_settings(), new NullLogger() ) extends Aggregate_Unpaid_Orders_Provider {
			/**
			 * Constructor.
			 *
			 * @param Unpaid_Orders_Provider_Interface[] $test_providers The injected provider list.
			 * @param Email_Reconcile_Settings_Interface $settings       Plugin settings.
			 * @param \Psr\Log\LoggerInterface           $logger         PSR-3 logger.
			 */
			public function __construct(
				protected array $test_providers,
				Email_Reconcile_Settings_Interface $settings,
				\Psr\Log\LoggerInterface $logger
			) {
				parent::__construct( $settings, $logger );
			}

			/**
			 * The injected providers, in place of the default + filtered list.
			 *
			 * @return Unpaid_Orders_Provider_Interface[]
			 */
			protected function get_providers(): array {
				return $this->test_providers;
			}
		};
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

		$sut = $this->make_sut( array( $available, $unavailable ) );

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

		$this->assertFalse( $this->make_sut( array( $unavailable ) )->is_available() );
	}

	/**
	 * With the provider list empty, the aggregate is unavailable and returns no orders.
	 *
	 * @covers ::is_available
	 * @covers ::get_unpaid_orders
	 */
	public function test_empty_provider_list(): void {

		$sut = $this->make_sut( array() );

		$this->assertFalse( $sut->is_available() );
		$this->assertSame( array(), $sut->get_unpaid_orders() );
	}

	/**
	 * Unfiltered (WP_Mock's apply_filters passes the value through), the aggregate builds the real
	 * WooCommerce and GiveWP providers; with neither plugin loaded (their function_exists() checks
	 * fail here), it reports unavailable.
	 *
	 * @covers ::get_providers
	 * @covers ::is_available
	 */
	public function test_default_providers_unavailable_without_backing_plugins(): void {

		$sut = new Aggregate_Unpaid_Orders_Provider( $this->make_settings(), new NullLogger() );

		$this->assertFalse( $sut->is_available() );
	}
}
