<?php
/**
 * Unit tests for the core API.
 *
 * The API depends only on the Unpaid_Orders_Provider_Interface and Email_Reconciler, never on a
 * concrete integration. These tests assert that contract through the `bh_wp_mailboxes_new_email`
 * hook callback, the API's only entry point.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Order_Email_Reconcile\API;

use BrianHenryIE\WP_Mailboxes\API\Controller\Email_Controller_Interface;
use BrianHenryIE\WP_Mailboxes\BH_Email_Account;
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

	protected function _before() {
		WP_Mock::setUp();
		WP_Mock::userFunction( 'add_action' );
	}

	protected function _tearDown() {
		WP_Mock::tearDown();
		Mockery::close();
		parent::_tearDown();
	}

	protected function make_settings(): Email_Reconcile_Settings_Interface {
		$settings = Mockery::mock( Email_Reconcile_Settings_Interface::class );
		$settings->shouldReceive( 'get_plugin_slug' )->andReturn( 'test-plugin' );
		$settings->shouldReceive( 'get_emails_cpt_underscored_20' )->andReturn( 'test_payment_emails' );
		$settings->shouldReceive( 'get_patterns' )->andReturn( array() );

		return $settings;
	}

	protected function make_account(): BH_Email_Account {
		return new BH_Email_Account(
			post_id: 12,
			post_type: 'test_email_accounts',
			local_status: 'bh_email_ac_active',
			connection_type_class: 'SomeConnection',
			email_address: 'payments@example.com',
			display_name: 'Payments',
			from_address_regex_filter: null,
			body_identifier_regex_filter: null,
			after_download_remote_email_action: null,
			delete_local_emails_after_n_days: null,
			last_checked_time: null,
			last_successful_login_time: null,
			last_failed_login_time: null,
		);
	}

	/**
	 * A controller wrapping a fixture email.
	 */
	protected function make_new_email(): Email_Controller_Interface {
		$controller = Mockery::mock( Email_Controller_Interface::class );
		$controller->allows( 'get_email' )->andReturn( BH_Email_Fixture::create( body_plain_text: 'Payment of $1.00', body_html: '' ) );

		return $controller;
	}

	/**
	 * The action is global: another instance's emails (other plugin slug or emails post type) are ignored
	 * before the provider is queried.
	 *
	 * @covers ::on_new_email
	 */
	public function test_ignores_other_instances(): void {

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldNotReceive( 'get_unpaid_orders' );

		$reconciler = Mockery::mock( Email_Reconciler::class );
		$reconciler->shouldNotReceive( 'reconcile_email' );

		$sut = new API( $this->make_settings(), $provider, $reconciler, new NullLogger() );

		$sut->on_new_email( 'other-plugin', 'test_payment_emails', $this->make_account(), $this->make_new_email() );
		$sut->on_new_email( 'test-plugin', 'other_emails', $this->make_account(), $this->make_new_email() );
	}

	/**
	 * With no unpaid orders, the email is not reconciled.
	 *
	 * @covers ::on_new_email
	 */
	public function test_no_unpaid_orders(): void {

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->shouldReceive( 'get_unpaid_orders' )->once()->andReturn( array() );

		$reconciler = Mockery::mock( Email_Reconciler::class );
		$reconciler->shouldNotReceive( 'index_orders' );
		$reconciler->shouldNotReceive( 'reconcile_email' );

		$sut = new API( $this->make_settings(), $provider, $reconciler, new NullLogger() );

		$sut->on_new_email( 'test-plugin', 'test_payment_emails', $this->make_account(), $this->make_new_email() );
	}

	/**
	 * With unpaid orders, the email is parsed and handed to the reconciler indexed with those orders.
	 *
	 * @covers ::on_new_email
	 */
	public function test_email_reconciled_against_unpaid_orders(): void {

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

		$sut = new API( $this->make_settings(), $provider, $reconciler, new NullLogger() );

		$sut->on_new_email( 'test-plugin', 'test_payment_emails', $this->make_account(), $this->make_new_email() );
	}

	/**
	 * The provider is exposed for the unreconciled orders page.
	 *
	 * @covers ::get_unpaid_orders_provider
	 */
	public function test_get_unpaid_orders_provider(): void {

		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );

		$sut = new API( $this->make_settings(), $provider, Mockery::mock( Email_Reconciler::class ), new NullLogger() );

		$this->assertSame( $provider, $sut->get_unpaid_orders_provider() );
	}
}
