/**
 * E2E: cron starts when there are unpaid orders and stops when there are none.
 *
 * This spec encodes the key behavioural design goal: the email-fetch cron should only run while
 * there is reconciliation work to do. Creating an unpaid order for a configured gateway schedules
 * the fetch cron; once no unpaid orders remain (the order is paid/cancelled), the cron is cleared.
 *
 * Requires the dev REST endpoints:
 *   POST   {DEV_REST}/orders                 -> 201 { id, status }   (create unpaid WC order for configured gateway)
 *   POST   {DEV_REST}/orders/{id}/pay        -> 200 { id, status }   (mark the order paid)
 *   GET    {DEV_REST}/cron/fetch-emails      -> { scheduled: bool, next: number|null }
 *
 * The cron hook name is `{emails_cpt_underscored}_fetch_emails_job` (see bh-wp-mailboxes Cron).
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const DEV_REST = '/wp-json/bh-wp-order-email-reconcile-dev/v1';

test.describe( 'fetch-emails cron start/stop', () => {
	test( 'creating an unpaid order schedules the cron; paying it clears the cron', async ( {
		request,
	} ) => {
		// Baseline: no cron when there are no unpaid orders.
		let cron = await ( await request.get( `${ DEV_REST }/cron/fetch-emails` ) ).json();
		expect( cron.scheduled ).toBe( false );

		// Arrange: create an unpaid order for a configured gateway.
		const create = await request.post( `${ DEV_REST }/orders`, {
			data: { payment_method: 'my-payment-gateway-id', total: '99.99' },
		} );
		expect( create.status() ).toBe( 201 );
		const order = await create.json();

		// Assert: the fetch-emails cron is now scheduled.
		cron = await ( await request.get( `${ DEV_REST }/cron/fetch-emails` ) ).json();
		expect( cron.scheduled ).toBe( true );
		expect( typeof cron.next ).toBe( 'number' );

		// Act: pay the order so no unpaid orders remain.
		const pay = await request.post( `${ DEV_REST }/orders/${ order.id }/pay` );
		expect( pay.ok() ).toBeTruthy();

		// Assert: the cron is cleared.
		cron = await ( await request.get( `${ DEV_REST }/cron/fetch-emails` ) ).json();
		expect( cron.scheduled ).toBe( false );
	} );
} );
