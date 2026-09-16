<?php
/**
 * WPUnit tests for the unreconciled orders page and its list table.
 *
 * The orders come from a mocked provider, so these cover rendering only: rows, links, pagination,
 * the empty state, and the no-integration notice.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Model\Unpaid_Order;
use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use DateTimeImmutable;
use Mockery;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_Page
 */
class Unreconciled_Orders_Page_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		set_current_screen( 'toplevel_page_' . Unreconciled_Orders_Page::PAGE_SLUG );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		unset( $_REQUEST['paged'] );
		set_current_screen( 'front' );
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * A mocked unpaid order.
	 *
	 * @param int $id The order id.
	 */
	protected function make_order( int $id ): Unpaid_Order {
		$order = Mockery::mock( Unpaid_Order::class );
		$order->allows( 'get_order_id' )->andReturn( $id );
		$order->allows( 'get_integration' )->andReturn( 'woocommerce' );
		$order->allows( 'get_edit_url' )->andReturn( "https://example.org/wp-admin/admin.php?page=wc-orders&action=edit&id={$id}" );
		$order->allows( 'get_date_created' )->andReturn( new DateTimeImmutable( '2026-09-01 10:00:00' ) );
		$order->allows( 'get_customer_display_name' )->andReturn( "Customer {$id}" );
		$order->allows( 'get_email_address' )->andReturn( "customer{$id}@example.com" );
		$order->allows( 'get_customer_payment_id' )->andReturn( 0 === $id % 2 ? "venmo-{$id}" : null );
		$order->allows( 'get_amount' )->andReturn( '12.34' );

		return $order;
	}

	/**
	 * A page over a provider returning the given orders.
	 *
	 * @param Unpaid_Order[] $orders    The orders.
	 * @param bool           $available Whether an integration is active.
	 */
	protected function make_sut( array $orders, bool $available = true ): Unreconciled_Orders_Page {
		$provider = Mockery::mock( Unpaid_Orders_Provider_Interface::class );
		$provider->allows( 'is_available' )->andReturn( $available );
		$provider->allows( 'get_unpaid_orders' )->andReturn( $orders );

		return new Unreconciled_Orders_Page( $provider, Mockery::mock( Email_Reconcile_Settings_Interface::class ), new NullLogger() );
	}

	protected function render( Unreconciled_Orders_Page $sut ): string {
		ob_start();
		$sut->render();

		return (string) ob_get_clean();
	}

	/**
	 * Each order is a row linking to its edit screen, with customer, payment id and amount; no
	 * checkbox column or bulk actions are rendered.
	 *
	 * @covers ::render
	 * @covers \BrianHenryIE\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_List_Table
	 */
	public function test_render_lists_orders(): void {
		$html = $this->render( $this->make_sut( array( $this->make_order( 41 ), $this->make_order( 42 ) ) ) );

		$this->assertStringContainsString( '2 orders are waiting for a payment email', $html );
		$this->assertStringContainsString( 'data-order-id="41" data-integration="woocommerce"', $html );
		$this->assertStringContainsString( 'href="https://example.org/wp-admin/admin.php?page=wc-orders&#038;action=edit&#038;id=41">#41</a>', $html );
		$this->assertStringContainsString( 'WooCommerce', $html );
		$this->assertStringContainsString( 'Customer 42', $html );
		$this->assertStringContainsString( 'customer42@example.com', $html );
		$this->assertStringContainsString( '<code>venmo-42</code>', $html );
		$this->assertStringContainsString( '12.34', $html );

		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringNotContainsString( 'bulkactions', $html );
		$this->assertStringContainsString( 'class="wp-list-table widefat striped bh-wp-oer-unreconciled-orders"', $html );
	}

	/**
	 * More than a page of orders is paginated, twenty per page.
	 *
	 * @covers \BrianHenryIE\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_List_Table::prepare_items
	 */
	public function test_render_paginates(): void {
		$orders = array_map( array( $this, 'make_order' ), range( 1, 21 ) );

		$html = $this->render( $this->make_sut( $orders ) );

		$this->assertSame( 20, substr_count( $html, 'data-order-id=' ) );
		$this->assertStringContainsString( '21 items', $html );
		$this->assertStringContainsString( 'next-page', $html );

		$_REQUEST['paged'] = '2';
		$html              = $this->render( $this->make_sut( $orders ) );

		$this->assertSame( 1, substr_count( $html, 'data-order-id=' ) );
		$this->assertStringContainsString( 'data-order-id="21"', $html );
	}

	/**
	 * With nothing to reconcile, the table shows its empty message.
	 *
	 * @covers ::render
	 */
	public function test_render_empty(): void {
		$html = $this->render( $this->make_sut( array() ) );

		$this->assertStringContainsString( '0 orders are waiting for a payment email', $html );
		$this->assertStringContainsString( 'No orders are waiting for a payment email.', $html );
	}

	/**
	 * Without an active integration a notice replaces the table.
	 *
	 * @covers ::render
	 */
	public function test_render_without_integration(): void {
		$html = $this->render( $this->make_sut( array(), false ) );

		$this->assertStringContainsString( 'No supported orders integration', $html );
		$this->assertStringNotContainsString( 'wp-list-table', $html );
	}

	/**
	 * The page registers as a submenu under the given parent.
	 *
	 * @covers ::register_submenu
	 * @covers ::get_url
	 */
	public function test_register_submenu(): void {
		global $submenu, $menu;
		$menu[] = array( 'Parent', 'read', 'test-parent', 'Parent' );

		$sut = $this->make_sut( array() );
		$sut->register_submenu( 'test-parent', 'read' );

		$this->assertArrayHasKey( 'test-parent', $submenu );
		// WordPress copies the parent in as the first submenu entry, so search rather than index.
		$slugs = array_column( $submenu['test-parent'], 2 );
		$this->assertContains( Unreconciled_Orders_Page::PAGE_SLUG, $slugs );
		$this->assertStringEndsWith( 'admin.php?page=' . Unreconciled_Orders_Page::PAGE_SLUG, $sut->get_url() );
	}
}
