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
	 * Constructor. Registers the menu and its styling.
	 *
	 * @param Email_Reconcile_Settings_Interface $settings        Provides the emails CPT slug for the submenu.
	 * @param callable                           $render_callback Renders the dev tools page.
	 * @param LoggerInterface                    $logger          PSR-3 logger.
	 */
	public function __construct(
		protected Email_Reconcile_Settings_Interface $settings,
		protected $render_callback,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_head', array( $this, 'print_menu_styles' ) );
	}

	/**
	 * Register the top-level menu (below Dashboard), the emails-list and gateway-settings submenus, and ensure a
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

		// The demo payment gateway's WooCommerce settings screen, where the library's mailbox fields render.
		$gateway_ids = $this->settings->get_payment_method_ids();
		if ( count( $gateway_ids ) > 0 ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Payment Gateway Settings', 'bh-wp-order-email-reconcile' ),
				__( 'Gateway settings', 'bh-wp-order-email-reconcile' ),
				self::MENU_CAPABILITY,
				'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( (string) reset( $gateway_ids ) )
			);
		}

		$this->add_separator_before_menu();
		$this->add_separator_after_menu();
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
		</style>
		<?php
	}
}
