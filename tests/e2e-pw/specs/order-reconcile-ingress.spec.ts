/**
 * E2E: full reconciliation loop through the bh-wp-mailboxes REST email ingress endpoint.
 *
 * Creates an unpaid WooCommerce order for a customer (with a customer payment id), POSTs a matching
 * raw MIME payment email to bh-wp-mailboxes' ingress endpoint, and asserts — via the WooCommerce
 * REST API — that the order was marked paid.
 *
 * bh-wp-mailboxes fires `bh_wp_mailboxes_new_email` for each newly stored email — from its fetch
 * path and from the REST ingress endpoint alike — which this library hooks, so the reconciliation
 * here happens synchronously within the ingress POST request.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';
const INGRESS_URL = '/wp-json/test-plugin/v2/test-payment-emails/new';

/** The ingress endpoint and the WooCommerce REST API require cookie auth with a REST nonce. */
async function getRestNonce( request ): Promise< string > {
	const response = await request.get(
		'/wp-admin/admin-ajax.php?action=rest-nonce'
	);
	expect( response.ok() ).toBeTruthy();
	return ( await response.text() ).trim();
}

test.describe( 'order reconciliation via email ingress', () => {
	test( 'an ingressed payment email marks the matching order paid', async ( {
		page,
		request,
	} ) => {
		const nonce = await getRestNonce( request );
		const customerPaymentId = `cust-ingress-${ Date.now() }`;

		// Arrange: an unpaid order for a customer, for the configured payment gateway.
		const create = await request.post( `${ DEV_REST }/orders`, {
			data: {
				payment_method: 'my-payment-gateway-id',
				total: '45.67',
				customer_payment_id: customerPaymentId,
				billing_email: 'customer@example.com',
				billing_first_name: 'Cust',
				billing_last_name: 'Omer',
			},
		} );
		expect( create.status() ).toBe( 201 );
		const order = await create.json();
		expect( order.status ).toBe( 'on-hold' );
		expect( order.total ).toBe( '45.67' );

		// Act: POST the payment email as raw MIME to the bh-wp-mailboxes ingress endpoint.
		const raw =
			'From: payments@example.com\r\n' +
			'To: store@example.com\r\n' +
			'Subject: Payment received\r\n' +
			`Message-ID: <ingress-reconcile-${ Date.now() }@example.com>\r\n` +
			`Date: ${ new Date().toUTCString() }\r\n` +
			'\r\n' +
			'Payment received\r\n' +
			`Customer ID: ${ customerPaymentId }\r\n` +
			'Amount: $45.67\r\n';

		const ingress = await request.post( INGRESS_URL, {
			headers: {
				'Content-Type': 'message/rfc822',
				'X-WP-Nonce': nonce,
			},
			data: raw,
		} );
		expect( ingress.status() ).toBe( 201 );
		const { post_id: emailPostId } = await ingress.json();

		// Assert: the order is paid, per the WooCommerce REST API.
		const wcOrder = await request.get( `/wp-json/wc/v3/orders/${ order.id }`, {
			headers: { 'X-WP-Nonce': nonce },
		} );
		expect( wcOrder.ok() ).toBeTruthy();
		const wcOrderBody = await wcOrder.json();
		expect( wcOrderBody.status ).toBe( 'completed' );
		expect( wcOrderBody.date_paid ).not.toBeNull();

		// Assert: the email is marked "saved" and its log links to the order.
		await page.goto( `/wp-admin/post.php?post=${ emailPostId }&action=edit` );
		await expect(
			page.locator( '#bh-email-local-status input[name="post_status"][value="bh_email_saved"]' )
		).toBeChecked();
		const log = page.locator( '#bh-email-log-notes' );
		await expect( log ).toContainText( `Reconciled: payment matched to Woocommerce order #${ order.id }` );
		await expect( log.getByRole( 'link', { name: `Woocommerce order #${ order.id }` } ) ).toHaveAttribute(
			'href',
			new RegExp( `(page=wc-orders&action=edit&id=${ order.id }|post\\.php\\?post=${ order.id }&action=edit)$` )
		);
	} );
} );
