/**
 * E2E: "Add account" on the payment gateway's WooCommerce settings screen.
 *
 * The library adds a `bh_wp_oer_mailbox_accounts` field to the gateway's form fields. It renders
 * bh-wp-mailboxes' "Add account" button (which opens that library's add/edit IMAP account modal,
 * printed in the admin footer) and a "Manage accounts" link to the emails list screen, whose
 * accounts table handles enable/disable, edit, check now and delete.
 *
 * Saving goes through bh-wp-mailboxes, which stores the credentials itself (encrypted, via the
 * Secrets API); the accounts table's `data-has-credentials` attribute reflects that.
 *
 * The IMAP server used here (127.0.0.1:1) refuses connections immediately, so the connection test
 * fails fast.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';
const GATEWAY_SETTINGS_URL =
	'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=my-payment-gateway-id';
const EMAILS_LIST_PATH = 'edit.php?post_type=test_payment_emails';

test.describe( 'add account from gateway settings', () => {
	test( 'the gateway settings show an Add account button, a Manage accounts link, and the mailboxes modal', async ( {
		page,
	} ) => {
		await page.goto( GATEWAY_SETTINGS_URL );

		// No server/username/password settings fields; the modal replaces them.
		await expect( page.locator( '#woocommerce_my-payment-gateway-id_email_password' ) ).toHaveCount( 0 );

		await expect( page.locator( 'a.bh-wp-oer-manage-accounts' ) ).toHaveAttribute(
			'href',
			new RegExp( EMAILS_LIST_PATH.replace( '?', '\\?' ) )
		);

		const dialog = page.locator( '#bh-mailboxes-account-dialog' );
		await expect( dialog ).toBeHidden();

		await page.getByRole( 'button', { name: 'Add account' } ).click();
		await expect( dialog ).toBeVisible();
		await expect( dialog.getByRole( 'heading', { name: 'Add IMAP account' } ) ).toBeVisible();
		await expect( dialog.getByLabel( 'Email address' ) ).toBeVisible();
		await expect( dialog.getByLabel( 'IMAP server' ) ).toBeVisible();
		await expect( dialog.getByLabel( 'Password' ) ).toBeVisible();

		await dialog.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( dialog ).toBeHidden();
	} );

	test( 'an IMAP account added from the gateway settings is stored with its credentials and listed in the accounts table', async ( {
		page,
		request,
	} ) => {
		const emailAddress = `gateway+${ Date.now() }@example.org`;

		await page.goto( GATEWAY_SETTINGS_URL );
		await page.getByRole( 'button', { name: 'Add account' } ).click();

		const dialog = page.locator( '#bh-mailboxes-account-dialog' );
		await dialog.getByLabel( 'Account name' ).fill( 'Gateway inbox' );
		await dialog.getByLabel( 'Email address' ).fill( emailAddress );
		await dialog.getByLabel( 'IMAP server' ).fill( '127.0.0.1:1' );
		await dialog.getByLabel( 'Password' ).fill( 'not-a-real-password' );
		await dialog.getByLabel( 'Encryption' ).selectOption( '' );
		await dialog.getByRole( 'button', { name: 'Add account' } ).click();
		await expect( dialog ).toBeHidden();

		// Saved even though the (unreachable) server fails the connection test; reported in a notice.
		await expect( page.locator( '.bh-check-notice' ).last() ).toContainText(
			'Account saved, but the connection test failed'
		);

		// Persisted in bh-wp-mailboxes.
		const list = await request.get( `${ DEV_REST }/email-accounts` );
		const body = await list.json();
		expect( body.accounts.map( ( a: { email_address: string } ) => a.email_address ) ).toContain(
			emailAddress
		);

		// Listed in the accounts table on the emails list, with credentials found (stored by bh-wp-mailboxes).
		await page.goto( `/wp-admin/${ EMAILS_LIST_PATH }` );
		const row = page.locator( `.bh-mailboxes-account[data-email-address="${ emailAddress }"]` );
		await expect( row ).toBeVisible();
		await expect( row ).toContainText( 'Gateway inbox' );
		await expect( row ).toHaveAttribute( 'data-has-credentials', '1' );
		await expect( row ).toHaveAttribute( 'data-server', '127.0.0.1:1' );
		await expect( row.locator( '.bh-mailboxes-no-credentials' ) ).toHaveCount( 0 );
	} );
} );
