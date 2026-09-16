<?php
/**
 * Admin page listing the orders/donations still waiting for a payment email.
 *
 * The library provides the page; the consumer decides where it lives by calling
 * {@see register_submenu()} on `admin_menu`, e.g.:
 *
 *     $page = new Unreconciled_Orders_Page( $reconcile->get_unpaid_orders_provider(), $settings, $logger );
 *     add_action( 'admin_menu', fn() => $page->register_submenu( 'my-plugin-menu', 'manage_woocommerce' ) );
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Registers and renders the "Unreconciled orders" page.
 */
class Unreconciled_Orders_Page {

	use LoggerAwareTrait;

	const PAGE_SLUG = 'bh-wp-oer-unreconciled-orders';

	/**
	 * The capability the page was registered with; checked again on render.
	 *
	 * @var string
	 */
	protected string $capability = 'manage_options';

	/**
	 * Constructor.
	 *
	 * @param Unpaid_Orders_Provider_Interface   $provider Supplies the unpaid orders (the same aggregate the reconciler uses).
	 * @param Email_Reconcile_Settings_Interface $settings Plugin settings.
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Unpaid_Orders_Provider_Interface $provider,
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Register the page as a submenu of the given menu. Call on `admin_menu`.
	 *
	 * @param string $parent_slug The parent menu's slug.
	 * @param string $capability  The capability required to see the page.
	 */
	public function register_submenu( string $parent_slug, string $capability = 'manage_options' ): void {
		$this->capability = $capability;

		add_submenu_page(
			$parent_slug,
			__( 'Unreconciled Orders', 'bh-wp-order-email-reconcile' ),
			__( 'Unreconciled orders', 'bh-wp-order-email-reconcile' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * The page's admin URL.
	 */
	public function get_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Render the page: a summary line and the list table.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'bh-wp-order-email-reconcile' ) );
		}

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Unreconciled Orders', 'bh-wp-order-email-reconcile' ) . '</h1>';
		echo '<hr class="wp-header-end">';

		if ( ! $this->provider->is_available() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No supported orders integration (WooCommerce, GiveWP) is active.', 'bh-wp-order-email-reconcile' ) . '</p></div>';
			echo '</div>';
			return;
		}

		$orders = $this->provider->get_unpaid_orders();
		$count  = count( $orders );

		echo '<p class="description">';
		echo esc_html(
			sprintf(
				/* translators: %d: number of unreconciled orders. */
				_n( '%d order is waiting for a payment email. Incoming payment emails are matched against these orders.', '%d orders are waiting for a payment email. Incoming payment emails are matched against these orders.', $count, 'bh-wp-order-email-reconcile' ),
				$count
			)
		);
		echo '</p>';

		$table = new Unreconciled_Orders_List_Table( $orders );
		$table->prepare_items();
		$table->display();

		echo '</div>';
	}
}
