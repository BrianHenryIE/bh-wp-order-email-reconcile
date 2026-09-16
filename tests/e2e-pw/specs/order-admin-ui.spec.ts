/**
 * E2E: development-plugin order-edit admin UI.
 *
 * The dev plugin adds, at `woocommerce_admin_order_data_after_billing_address`:
 *   - an editable "Customer payment id" field (saved to the order meta), and
 *   - a "Fetch emails now" button shown only on unpaid orders.
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

	test( 'fetch-emails button shows on unpaid orders and not on paid ones', async ( {
		page,
		request,
	} ) => {
		const id = await createUnpaidOrder( request );

		await page.goto( orderEditUrl( id ) );
		await expect(
			page.getByRole( 'link', { name: 'Fetch emails now' } )
		).toBeVisible();

		// Pay the order; the button should disappear.
		const pay = await request.post( `${ DEV_REST }/orders/${ id }/pay` );
		expect( pay.ok() ).toBeTruthy();

		await page.goto( orderEditUrl( id ) );
		await expect(
			page.getByRole( 'link', { name: 'Fetch emails now' } )
		).toHaveCount( 0 );
	} );
} );
