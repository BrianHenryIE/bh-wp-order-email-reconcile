/**
 * E2E: development-plugin order-edit admin UI.
 *
 * The dev plugin adds, at `woocommerce_admin_order_data_after_billing_address`:
 *   - an editable "Customer payment id" field (saved to the order meta), and
 *   - a "Check emails" link (update dashicon) to the right of the Status label, on unpaid orders only,
 *     which checks the mailbox over REST and reloads the page to show the outcome as an admin notice.
 *
 * Orders are arranged via the dev REST endpoints; the assertions touch the real wp-admin order screen.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';

const orderEditUrl = ( id: number ) =>
	`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ id }`;

const createUnpaidOrder = async ( request: any ): Promise< number > => {
	const res = await request.post( `${ DEV_REST }/orders`, {
		data: { payment_method: 'my-payment-gateway-id', total: '42.00' },
	} );
	expect( res.status() ).toBe( 201 );
	return ( await res.json() ).id;
};

test.describe( 'order admin UI', () => {
	test( 'customer payment id field is editable and persists', async ( {
		page,
		request,
	} ) => {
		const id = await createUnpaidOrder( request );

		await page.goto( orderEditUrl( id ) );

		// Shown as text until the billing address is put into edit mode.
		const display = page.locator( '.bh-wp-oer-customer-payment-id' );
		const field = page.locator( '#customer_payment_id' );
		await expect( display ).toBeVisible();
		await expect( display ).toContainText( 'Customer payment id' );
		await expect( field ).toBeHidden();

		await page.locator( '.order_data_column_billing a.edit_address' ).click();
		await expect( display ).toBeHidden();
		await expect( field ).toBeVisible();

		await field.fill( 'venmo-jane' );
		await page.locator( 'button.save_order' ).click();
		await page.waitForLoadState( 'networkidle' );

		await page.goto( orderEditUrl( id ) );
		await expect( page.locator( '.bh-wp-oer-customer-payment-id__value' ) ).toHaveText( 'venmo-jane' );
		await expect( page.locator( '#customer_payment_id' ) ).toHaveValue( 'venmo-jane' );

		// Clean up: pay the order so it does not linger as unpaid (shared DB / cron state).
		await request.post( `${ DEV_REST }/orders/${ id }/pay` );
	} );

	test( 'check-emails link sits beside the Status label on unpaid orders and not on paid ones', async ( {
		page,
		request,
	} ) => {
		const id = await createUnpaidOrder( request );

		await page.goto( orderEditUrl( id ) );
		const link = page.getByRole( 'link', { name: 'Check emails' } );
		await expect( link ).toBeVisible();
		await expect( link.locator( '.dashicons-update' ) ).toBeAttached();

		// Inside the Status label, to the right of its text; hidden until the script moved it there.
		const label = page.locator( '.wc-order-status label[for="order_status"]' );
		await expect( link ).not.toHaveAttribute( 'hidden', '' );
		await expect( label.getByRole( 'link', { name: 'Check emails' } ) ).toBeVisible();
		const labelBox = ( await label.boundingBox() )!;
		const linkBox = ( await link.boundingBox() )!;
		expect( linkBox.x ).toBeGreaterThan( labelBox.x + labelBox.width / 2 );

		// Icon to the right of the text; no underline until hover, and then only on the text.
		const iconBox = ( await link.locator( '.dashicons-update' ).boundingBox() )!;
		const textBox = ( await link.locator( '.bh-wp-oer-check-emails__text' ).boundingBox() )!;
		expect( iconBox.x ).toBeGreaterThan( textBox.x + textBox.width - 1 );
		expect( await link.evaluate( ( el ) => getComputedStyle( el ).textDecorationLine ) ).toBe( 'none' );
		await link.hover();
		expect( await link.evaluate( ( el ) => getComputedStyle( el ).textDecorationLine ) ).toBe( 'none' );
		expect(
			await link.locator( '.bh-wp-oer-check-emails__text' ).evaluate( ( el ) => getComputedStyle( el ).textDecorationLine )
		).toBe( 'underline' );

		// Clicking checks the mailbox over REST. No account on this site can fetch anything, so this
		// order is not reconciled: the blue "no emails matched" notice is shown in place, without a reload.
		let loads = 0;
		page.on( 'load', () => loads++ );
		const check = page.waitForResponse( ( res ) => res.url().includes( `/orders/${ id }/check-emails` ) );
		await link.click();
		expect( ( await check ).status() ).toBe( 200 );
		const notice = page.locator( '.bh-wp-oer-check-emails-notice' );
		await expect( notice ).toHaveClass( /notice-info/ );
		await expect( notice ).toContainText( '0 new emails downloaded' );
		await expect( notice ).toContainText( 'No emails matched this order' );
		await expect( notice ).toContainText( '0 orders were reconciled' );
		expect( loads ).toBe( 0 );
		await expect( link ).not.toHaveClass( /is-checking/ );

		// Nothing is left over for the next load.
		await page.goto( orderEditUrl( id ) );
		await expect( page.locator( '.bh-wp-oer-check-emails-notice' ) ).toHaveCount( 0 );

		// Pay the order; the link should disappear.
		const pay = await request.post( `${ DEV_REST }/orders/${ id }/pay` );
		expect( pay.ok() ).toBeTruthy();

		await page.goto( orderEditUrl( id ) );
		await expect( page.getByRole( 'link', { name: 'Check emails' } ) ).toHaveCount( 0 );
	} );
} );
