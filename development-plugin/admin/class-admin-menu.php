<?php
/**
 * Registers the development/test plugin's admin menu.
 *
 * Owns everything menu related: the top-level menu entry, its emails-list submenu, the menu's
 * position (immediately below "Dashboard", with a trailing separator), its visibility, and the
 * green styling of the top-level item. Page rendering itself lives in {@see Admin_Page}.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\Admin;

use BrianHenryIE\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_Page;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Registers and styles the dev menu.
 */
class Admin_Menu {

	use LoggerAwareTrait;

	const MENU_SLUG = 'bh-wp-oer-dev';

	/**
	 * Menu position. WordPress places "Dashboard" at position 2 and its separator at position 4, so
	 * position 3 lands the menu immediately below Dashboard with that core separator as the space after.
	 */
	const MENU_POSITION = 3;

	/**
	 * Position of the separator placed above the menu, between Dashboard (position 2) and this menu
	 * (position 3). A non-integer string key slots it between the two without colliding with either.
	 */
	const SEPARATOR_ABOVE_POSITION = '2.5';

	/**
	 * Capability required to see the menu. `read` is held by every logged-in user, so the menu is
	 * always visible.
	 */
	const MENU_CAPABILITY = 'read';

	/**
	 * Slug prefix of the non-clickable placeholder items shown for inactive integrations.
	 */
	const INACTIVE_SLUG_PREFIX = 'bh-wp-oer-dev-inactive-';

	/**
	 * Constructor. Registers the menu and its styling.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings                 Provides the emails CPT slug for the submenu.
	 * @param callable                           $render_callback          Renders the dev tools page.
	 * @param Unreconciled_Orders_Page           $unreconciled_orders_page The library's unreconciled orders page, registered as a submenu.
	 * @param LoggerInterface                    $logger                   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		protected $render_callback,
		protected Unreconciled_Orders_Page $unreconciled_orders_page,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_head', array( $this, 'print_menu_styles' ) );
	}

	/**
	 * Register the top-level menu (below Dashboard), the emails-list, unreconciled-orders and gateway submenus, and ensure a
	 * separator sits both above and below the menu so it is visually spaced from its neighbours.
	 *
	 * @hooked admin_menu
	 *
	 * @return void
	 */
	public function register_menu(): void {

		add_menu_page(
			__( 'Order Email Reconcile Dev', 'bh-wp-order-email-reconcile' ),
			__( 'OEReconcile', 'bh-wp-order-email-reconcile' ),
			self::MENU_CAPABILITY,
			self::MENU_SLUG,
			$this->render_callback,
			'dashicons-email-alt',
			self::MENU_POSITION
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Payment Emails', 'bh-wp-order-email-reconcile' ),
			__( 'Emails', 'bh-wp-order-email-reconcile' ),
			self::MENU_CAPABILITY,
			'edit.php?post_type=' . $this->settings->get_emails_cpt_underscored_20()
		);

		// The library's list of orders still waiting for a payment email.
		$this->unreconciled_orders_page->register_submenu( self::MENU_SLUG, self::MENU_CAPABILITY );

		// The demo payment gateway's settings screens in each integration, where the library's mailbox
		// fields render. Listed even when the integration's plugin is inactive, but then not clickable.
		$gateway_ids = $this->settings->get_payment_method_ids();
		$gateway_id  = count( $gateway_ids ) > 0 ? rawurlencode( (string) reset( $gateway_ids ) ) : '';

		$this->add_gateway_submenu(
			'woocommerce',
			__( 'WooCommerce Gateway Settings', 'bh-wp-order-email-reconcile' ),
			__( 'WooCommerce Gateway', 'bh-wp-order-email-reconcile' ),
			function_exists( 'wc_get_orders' ),
			'admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id
		);
		$this->add_gateway_submenu(
			'givewp',
			__( 'GiveWP Gateway Settings', 'bh-wp-order-email-reconcile' ),
			__( 'GiveWP Gateway', 'bh-wp-order-email-reconcile' ),
			function_exists( 'give' ),
			'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=' . $gateway_id
		);

		$this->add_separator_before_menu();
		$this->add_separator_after_menu();
	}

	/**
	 * A submenu link to an integration's gateway settings screen. When the integration's plugin is
	 * inactive the item is still listed, but as a non-clickable placeholder (see print_menu_styles()),
	 * whose page just says the plugin is inactive should it be reached directly.
	 *
	 * @param string $integration  Integration key, used in the placeholder slug.
	 * @param string $page_title   The page title.
	 * @param string $menu_title   The menu label.
	 * @param bool   $is_active    Whether the integration's plugin is active.
	 * @param string $settings_url The gateway settings screen, relative to wp-admin.
	 */
	protected function add_gateway_submenu( string $integration, string $page_title, string $menu_title, bool $is_active, string $settings_url ): void {
		if ( $is_active ) {
			add_submenu_page( self::MENU_SLUG, $page_title, $menu_title, self::MENU_CAPABILITY, $settings_url );
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			$page_title,
			$menu_title,
			self::MENU_CAPABILITY,
			self::INACTIVE_SLUG_PREFIX . $integration,
			function () use ( $menu_title ): void {
				echo '<div class="wrap"><h1>' . esc_html( $menu_title ) . '</h1><p>' . esc_html__( 'The plugin for this integration is not active.', 'bh-wp-order-email-reconcile' ) . '</p></div>';
			}
		);
	}

	/**
	 * Insert a menu separator immediately above the menu so it is visually spaced from Dashboard.
	 *
	 * @return void
	 */
	protected function add_separator_before_menu(): void {

		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		if ( isset( $menu[ self::SEPARATOR_ABOVE_POSITION ] ) ) {
			return;
		}

		$menu[ self::SEPARATOR_ABOVE_POSITION ] = array( '', self::MENU_CAPABILITY, 'separator-bh-wp-oer-dev-top', '', 'wp-menu-separator' );
	}

	/**
	 * Insert a menu separator immediately after the menu so it is visually spaced from what follows.
	 *
	 * WordPress core adds its first separator at position 4; this guards the space in case another
	 * plugin claims that slot.
	 *
	 * @return void
	 */
	protected function add_separator_after_menu(): void {

		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$separator_position = (string) ( self::MENU_POSITION + 1 );

		if ( isset( $menu[ $separator_position ] ) ) {
			return;
		}

		$menu[ $separator_position ] = array( '', self::MENU_CAPABILITY, 'separator-bh-wp-oer-dev', '', 'wp-menu-separator' );
	}

	/**
	 * Give the top-level menu item a green background and keep its submenu always expanded inline.
	 *
	 * The submenu rules mirror WordPress's own "current/open" state CSS so the submenu renders inline
	 * regardless of which admin page is open, rather than as an off-screen hover flyout.
	 *
	 * @hooked admin_head
	 *
	 * @return void
	 */
	public function print_menu_styles(): void {
		$menu_id = 'toplevel_page_' . self::MENU_SLUG;
		?>
		<style id="bh-wp-oer-dev-menu-style">
			#adminmenu #<?php echo esc_html( $menu_id ); ?> > a.menu-top,
			#adminmenu #<?php echo esc_html( $menu_id ); ?> > a.menu-top:hover,
			#adminmenu #<?php echo esc_html( $menu_id ); ?>.wp-has-current-submenu > a.menu-top {
				background: #46b450;
				color: #fff;
			}
			#adminmenu #<?php echo esc_html( $menu_id ); ?> .wp-submenu {
				position: relative;
				top: auto;
				left: auto;
				right: auto;
				bottom: auto;
				margin-top: 0;
				box-shadow: none;
				min-width: auto;
			}
			#adminmenu #<?php echo esc_html( $menu_id ); ?> .wp-submenu a[href*="<?php echo esc_attr( self::INACTIVE_SLUG_PREFIX ); ?>"] {
				pointer-events: none;
				color: rgba( 240, 246, 252, 0.4 );
			}
		</style>
		<script id="bh-wp-oer-dev-menu-script">
			// Placeholder items for inactive integrations: listed, but not links. (admin_head runs before the menu.)
			document.addEventListener( 'DOMContentLoaded', function () {
			document.querySelectorAll( '#<?php echo esc_js( $menu_id ); ?> .wp-submenu a[href*="<?php echo esc_js( self::INACTIVE_SLUG_PREFIX ); ?>"]' ).forEach( function ( link ) {
				link.setAttribute( 'aria-disabled', 'true' );
				link.setAttribute( 'tabindex', '-1' );
				link.setAttribute( 'title', '<?php echo esc_js( __( 'Plugin not active', 'bh-wp-order-email-reconcile' ) ); ?>' );
			} );
			} );
		</script>
		<?php
	}
}
