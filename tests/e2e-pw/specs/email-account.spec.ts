/**
 * E2E: adding an email account.
 *
 * Drives the design of how a payment mailbox is configured. Adding an account is delegated to
 * bh-wp-mailboxes: BH_WP_Mailboxes API::add_email_account(...). This spec adds an account via the
 * dev REST endpoint (which calls that API) and asserts it is persisted and listed.
 *
 * Requires the dev REST endpoints:
 *   POST {DEV_REST}/email-accounts -> 201 { id: number, email_address: string }
 *   GET  {DEV_REST}/email-accounts -> { accounts: Array<{ id, email_address }> }
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';

test.describe( 'email accounts', () => {
	test( 'an added email account is persisted and listed', async ( { request } ) => {
		const email_address = `payments+${ Date.now() }@example.org`;

		const create = await request.post( `${ DEV_REST }/email-accounts`, {
			data: {
				email_address,
				display_name: 'Payments inbox',
				provider_type_class: 'imap',
			},
		} );
		expect( create.status() ).toBe( 201 );

		const created = await create.json();
		expect( created.email_address ).toBe( email_address );

		const list = await request.get( `${ DEV_REST }/email-accounts` );
		expect( list.ok() ).toBeTruthy();
		const body = await list.json();
		expect( body.accounts.map( ( a: { email_address: string } ) => a.email_address ) ).toContain(
			email_address
		);
	} );
} );
