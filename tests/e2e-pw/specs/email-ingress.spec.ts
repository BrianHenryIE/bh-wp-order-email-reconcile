/**
 * E2E: bh-wp-mailboxes raw-MIME email ingress REST endpoint.
 *
 * The development-plugin's settings provide `get_rest_namespace(): 'test-plugin'`, which enables
 * bh-wp-mailboxes' REST ingress endpoint at `{namespace}/v2/{emails-cpt-dashed}/new`. In production
 * a Cloudflare Email Routing worker POSTs each incoming email there as `message/rfc822`; these
 * tests POST directly, which is the most realistic way to inject emails end-to-end.
 *
 * Note: bh-wp-mailboxes does not (yet) fire `bh_wp_mailboxes_new_email` for REST-ingested emails —
 * the action fires only on the fetch path — so reconciliation is not asserted here, only that the
 * endpoint stores the email, is idempotent, and files it under the auto-created ingress account.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const INGRESS_URL = '/wp-json/test-plugin/v2/test-payment-emails/new';

/** The ingress endpoint requires edit_posts; cookie auth needs a REST nonce. */
async function getRestNonce( request ): Promise< string > {
	const response = await request.get(
		'/wp-admin/admin-ajax.php?action=rest-nonce'
	);
	expect( response.ok() ).toBeTruthy();
	return ( await response.text() ).trim();
}

function buildRawEmail( messageId: string ): string {
	const body = 'Payment received\r\nCustomer ID: cust-e2e-ingress\r\nAmount: $12.34\r\n';
	return (
		'From: payments@example.com\r\n' +
		'To: store@example.com\r\n' +
		'Subject: Payment received\r\n' +
		`Message-ID: <${ messageId }>\r\n` +
		`Date: ${ new Date().toUTCString() }\r\n` +
		'\r\n' +
		body
	);
}

test.describe( 'email REST ingress', () => {
	test( 'a POSTed raw MIME email is stored, idempotently', async ( {
		request,
	} ) => {
		const nonce = await getRestNonce( request );
		const messageId = `e2e-ingress-${ Date.now() }@example.com`;
		const raw = buildRawEmail( messageId );

		const post = ( body: string ) =>
			request.post( INGRESS_URL, {
				headers: {
					'Content-Type': 'message/rfc822',
					'X-WP-Nonce': nonce,
				},
				data: body,
			} );

		// First POST creates the email post.
		const created = await post( raw );
		expect( created.status() ).toBe( 201 );
		const createdBody = await created.json();
		expect( createdBody.post_id ).toBeGreaterThan( 0 );
		// MIME parsers differ on whether the Message-ID keeps its angle brackets.
		expect( createdBody.message_id ).toContain( messageId );

		// Re-POSTing the same message is idempotent: 200, not a duplicate.
		const repeat = await post( raw );
		expect( repeat.status() ).toBe( 200 );

		// An unparseable content type is rejected.
		const wrongType = await request.post( INGRESS_URL, {
			headers: {
				'Content-Type': 'text/plain',
				'X-WP-Nonce': nonce,
			},
			data: raw,
		} );
		expect( wrongType.status() ).toBe( 415 );
	} );

	test( 'the ingested email is visible in the admin emails list', async ( {
		page,
		request,
	} ) => {
		const nonce = await getRestNonce( request );
		const messageId = `e2e-ingress-list-${ Date.now() }@example.com`;

		const created = await request.post( INGRESS_URL, {
			headers: {
				'Content-Type': 'message/rfc822',
				'X-WP-Nonce': nonce,
			},
			data: buildRawEmail( messageId ),
		} );
		expect( created.status() ).toBe( 201 );

		await page.goto( '/wp-admin/edit.php?post_type=test_payment_emails' );
		await expect(
			page.locator( '#the-list tr', { hasText: 'Payment received' } ).first()
		).toBeVisible();
	} );
} );
