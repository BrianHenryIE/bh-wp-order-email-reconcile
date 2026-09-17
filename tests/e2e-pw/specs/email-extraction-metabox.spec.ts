/**
 * E2E: the development plugin's "Extraction patterns" metabox on the single email view.
 *
 * Lists, per Email_Extract_Settings_Interface pattern set, each regex and the value it matched in the
 * email, read from the extraction result the library saved on the email when it was processed. The
 * dev plugin's Extraction_Settings matches `$12.34`-style amounts and `Customer ID: …`.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const INGRESS_URL = '/wp-json/test-plugin/v2/test-payment-emails/new';

async function getRestNonce( request ): Promise< string > {
	const response = await request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' );
	expect( response.ok() ).toBeTruthy();
	return ( await response.text() ).trim();
}

test.describe( 'extraction patterns metabox', () => {
	test( 'lists the matched values for the email', async ( { page, request } ) => {
		const nonce = await getRestNonce( request );
		const raw =
			'From: payments@example.com\r\nTo: store@example.com\r\nSubject: Payment received\r\n' +
			`Message-ID: <e2e-extraction-${ Date.now() }@example.com>\r\nDate: ${ new Date().toUTCString() }\r\n\r\n` +
			'Payment received\r\nCustomer ID: cust-extraction\r\nAmount: $56.78\r\n';
		const ingress = await request.post( INGRESS_URL, {
			headers: { 'Content-Type': 'message/rfc822', 'X-WP-Nonce': nonce },
			data: raw,
		} );
		expect( ingress.status() ).toBe( 201 );
		const { post_id: emailPostId } = await ingress.json();

		await page.goto( `/wp-admin/post.php?post=${ emailPostId }&action=edit` );

		const metabox = page.locator( '#bh-wp-oer-extraction-patterns' );
		await expect( metabox ).toBeVisible();
		await expect( metabox.locator( '.bh-wp-oer-extraction-patterns__set' ).first() ).toContainText( 'Extraction_Settings' );

		const amount = metabox.locator( 'tr[data-pattern="amount"]' );
		// The regex is shown as configured, backslashes intact.
		await expect( amount ).toContainText( '~\\$(\\d+\\.\\d{2})~' );
		await expect( amount.locator( '.bh-wp-oer-extraction-patterns__match' ) ).toContainText( '56.78' );

		const customerId = metabox.locator( 'tr[data-pattern="customer_id"]' );
		await expect( customerId.locator( '.bh-wp-oer-extraction-patterns__match' ) ).toContainText( 'cust-extraction' );

		// The dev pattern set has no customer email regex; the email has no order id to match.
		await expect( metabox.locator( 'tr[data-pattern="customer_email"]' ) ).toContainText( 'Not set' );
		await expect( metabox.locator( 'tr[data-pattern="order_id"]' ) ).toContainText( 'No match' );

		// The merged values the reconciler used.
		await expect( metabox.locator( '.bh-wp-oer-extraction-patterns__values' ) ).toContainText( '56.78' );

		// No order matched, so the email was marked processed (not saved).
		await expect(
			page.locator( '#bh-email-local-status input[name="post_status"][value="bh_email_processed"]' )
		).toBeChecked();
		await expect( page.locator( '#bh-email-log-notes' ) ).toContainText( 'Processed' );
	} );
} );
