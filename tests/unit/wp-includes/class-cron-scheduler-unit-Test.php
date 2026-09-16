<?php
/**
 * Unit tests for the fetch-emails cron scheduler.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Mockery;
use Psr\Log\NullLogger;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\WP_Includes\Cron_Scheduler
 */
class Cron_Scheduler_Unit_Test extends \Codeception\Test\Unit {

	const HOOK = 'test_payment_emails_fetch_emails_job';

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
		WP_Mock::userFunction( 'sanitize_key' )->andReturnUsing(
			function ( $key ) {
				return $key;
			}
		);
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @param Unpaid_Order[] $unpaid_orders The orders the provider should return.
	 */
	protected function make_sut( array $unpaid_orders = array() ): Cron_Scheduler {

		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldReceive( 'get_unpaid_orders' )->andReturn( $unpaid_orders );

		return new Cron_Scheduler( $settings, $provider, new NullLogger() );
	}

	/**
	 * Flag says unpaid and the cron is not scheduled => schedule it.
	 *
	 * @covers ::enforce_cron_schedule
	 * @covers ::get_fetch_emails_cron_hook_name
	 */
	public function test_enforce_schedules_when_flag_set_and_not_scheduled(): void {

		WP_Mock::userFunction( 'get_option' )->andReturn( 'yes' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->with( self::HOOK )->andReturn( false );
		WP_Mock::onFilter( 'bh_wp_order_email_reconcile_fetch_emails_cron_recurrence' )
			->with( 'hourly', 'test-plugin' )->reply( 'hourly' );
		WP_Mock::userFunction( 'wp_schedule_event' )
			->once()->with( Mockery::type( 'int' ), 'hourly', self::HOOK );
		WP_Mock::userFunction( 'wp_unschedule_hook' )->never();

		$this->make_sut()->enforce_cron_schedule();
	}

	/**
	 * Flag says no unpaid orders and the cron is scheduled => clear it.
	 *
	 * @covers ::enforce_cron_schedule
	 */
	public function test_enforce_clears_when_flag_unset_and_scheduled(): void {

		WP_Mock::userFunction( 'get_option' )->andReturn( 'no' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->with( self::HOOK )->andReturn( 1_700_000_000 );
		WP_Mock::userFunction( 'wp_unschedule_hook' )->once()->with( self::HOOK );
		WP_Mock::userFunction( 'wp_schedule_event' )->never();

		$this->make_sut()->enforce_cron_schedule();
	}

	/**
	 * Calling refresh_unpaid_orders_state() caches the flag and applies the schedule.
	 *
	 * @covers ::refresh_unpaid_orders_state
	 */
	public function test_refresh_caches_flag_and_schedules(): void {

		$option_name = 'bh_wp_oer_test-plugin_has_unpaid_orders';

		WP_Mock::userFunction( 'update_option' )->once()->with( $option_name, 'yes', false );
		WP_Mock::userFunction( 'get_option' )->andReturn( 'yes' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->with( self::HOOK )->andReturn( false );
		WP_Mock::onFilter( 'bh_wp_order_email_reconcile_fetch_emails_cron_recurrence' )
			->with( 'hourly', 'test-plugin' )->reply( 'hourly' );
		WP_Mock::userFunction( 'wp_schedule_event' )->once()->with( Mockery::type( 'int' ), 'hourly', self::HOOK );

		$this->make_sut( array( Mockery::mock( Unpaid_Order::class ) ) )->refresh_unpaid_orders_state();
	}

	/**
	 * Calling refresh_unpaid_orders_state() with no orders caches "no".
	 *
	 * @covers ::refresh_unpaid_orders_state
	 */
	public function test_refresh_caches_no_when_no_orders(): void {

		$option_name = 'bh_wp_oer_test-plugin_has_unpaid_orders';

		WP_Mock::userFunction( 'update_option' )->once()->with( $option_name, 'no', false );
		WP_Mock::userFunction( 'get_option' )->andReturn( 'no' );
		WP_Mock::userFunction( 'wp_next_scheduled' )->with( self::HOOK )->andReturn( false );
		WP_Mock::userFunction( 'wp_schedule_event' )->never();
		WP_Mock::userFunction( 'wp_unschedule_hook' )->never();

		$this->make_sut()->refresh_unpaid_orders_state();
	}

	/**
	 * The is_scheduled() method reports the next run time.
	 *
	 * @covers ::is_scheduled
	 */
	public function test_is_scheduled_reports_next_run(): void {

		WP_Mock::userFunction( 'wp_next_scheduled' )->with( self::HOOK )->andReturn( 1_700_000_000 );

		$result = $this->make_sut()->is_scheduled();

		$this->assertTrue( $result['scheduled'] );
		$this->assertSame( 1_700_000_000, $result['next'] );
	}
}
