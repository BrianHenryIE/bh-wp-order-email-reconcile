/**
 * E2E: the "Unreconciled orders" admin page (a WP_List_Table of orders waiting for a payment email).
 *
 * The library provides the page; the development plugin registers it under its "OEReconcile" menu.
 * Orders are arranged via the dev REST endpoints.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';
const PAGE_URL = '/wp-admin/admin.php?page=bh-wp-oer-unreconciled-orders';

test.describe( 'unreconciled orders page', () => {
	test( 'an unpaid order is listed with a link to it, and disappears once paid', async ( {
		page,
		request,
	} ) => {
		const create = await request.post( `${ DEV_REST }/orders`, {
			data: {
				payment_method: 'my-payment-gateway-id',
				total: '23.45',
				billing_email: 'unreconciled@example.com',
				billing_first_name: 'Una',
				billing_last_name: 'Reconciled',
				customer_payment_id: 'venmo-una',
			},
		} );
		expect( create.status() ).toBe( 201 );
		const { id } = await create.json();

		await page.goto( PAGE_URL );
		await expect( page.getByRole( 'heading', { name: 'Unreconciled Orders' } ) ).toBeVisible();

		const row = page.locator( `tr[data-order-id="${ id }"]` );
		await expect( row ).toBeVisible();
		await expect( row ).toHaveAttribute( 'data-integration', 'woocommerce' );
		// WooCommerce's own edit URL: HPOS (`page=wc-orders&action=edit&id=`) or posts (`post.php?post=`).
		await expect( row.getByRole( 'link', { name: `#${ id }` } ) ).toHaveAttribute(
			'href',
			new RegExp( `(page=wc-orders&action=edit&id=${ id }|post\\.php\\?post=${ id }&action=edit)$` )
		);
		await expect( row ).toContainText( 'WooCommerce' );
		await expect( row ).toContainText( 'Una Reconciled' );
		await expect( row ).toContainText( 'unreconciled@example.com' );
		await expect( row ).toContainText( 'venmo-una' );
		await expect( row ).toContainText( '23.45' );

		// A WP_List_Table without checkboxes or bulk actions.
		await expect( page.locator( '.wp-list-table input[type="checkbox"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.bulkactions' ) ).toHaveCount( 0 );

		// Paying the order removes it from the list.
		const pay = await request.post( `${ DEV_REST }/orders/${ id }/pay` );
		expect( pay.ok() ).toBeTruthy();

		await page.goto( PAGE_URL );
		await expect( page.locator( `tr[data-order-id="${ id }"]` ) ).toHaveCount( 0 );
	} );
} );
