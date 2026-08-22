/**
 * E2E: library instantiation.
 *
 * Drives the design of how the library is set up: the development-plugin calls
 * BH_WP_Order_Email_Reconcile::make( $settings, $logger ) on `plugins_loaded`, which boots
 * bh-wp-mailboxes and registers the reconcile hook. This spec asserts that booted state via REST.
 *
 * Requires: `npm run wp-env:start`, WooCommerce + development-plugin active, and the dev REST
 * endpoints below (to be ported from bh-wp-mailboxes development-plugin/rest/).
 *
 *   GET  {DEV_REST}/status -> { library_loaded: bool, mailboxes_loaded: bool, hook_registered: bool }
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';

test.describe( 'instantiation', () => {
	test( 'make() boots the library, mailboxes, and registers the reconcile hook', async ( {
		request,
	} ) => {
		const response = await request.get( `${ DEV_REST }/status` );
		expect( response.ok() ).toBeTruthy();

		const body = await response.json();
		expect( body.library_loaded ).toBe( true );
		expect( body.mailboxes_loaded ).toBe( true );
		// API constructor adds the bh_wp_mailboxes_new_email action.
		expect( body.hook_registered ).toBe( true );
	} );
} );
