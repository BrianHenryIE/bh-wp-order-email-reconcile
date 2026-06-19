<?php
/**
 * Test-only login shortcut: `?login_as_user=<user_login>` authenticates the browser without the UI.
 *
 * Used by the Playwright auth setup. Development-plugin only; never ship this.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin;

/**
 * Logs the browser in as the requested user when `?login_as_user=` is present.
 */
class Authentication {

	/**
	 * Constructor. Hooks the login shortcut.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_login_as_user' ) );
	}

	/**
	 * If `?login_as_user=<login>` is set, authenticate as that user and redirect home.
	 *
	 * @return void
	 */
	public function maybe_login_as_user(): void {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Test-only login shortcut; no nonce.
		if ( ! isset( $_GET['login_as_user'] ) ) {
			return;
		}

		$login = sanitize_user( wp_unslash( $_GET['login_as_user'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$user = get_user_by( 'login', $login );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID );
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
