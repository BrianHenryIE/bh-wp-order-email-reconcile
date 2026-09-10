/**
 * E2E: development-plugin admin "Dev Tools" page.
 *
 * Verifies the top-level menu (placed below Dashboard, green, always visible), the emails-list
 * submenu, and the create-order → send-payment-email → reconcile demo loop.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const PAGE_URL = '/wp-admin/admin.php?page=bh-wp-oer-dev';

test.describe( 'dev admin page', () => {
	test( 'top-level menu sits below Dashboard, is green, separated, and shows its submenu', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );

		// The dev menu sits immediately below Dashboard, labelled "OEReconcile".
		const dashboard = page.locator( '#menu-dashboard' );
		const devMenu = page.locator( '#toplevel_page_bh-wp-oer-dev' );
		await expect( devMenu ).toContainText( 'OEReconcile' );

		const dashboardBox = ( await dashboard.boundingBox() )!;
		const dashboardBottom = dashboardBox.y + dashboardBox.height;
		const devMenuTop = ( await devMenu.boundingBox() )!.y;
		expect( devMenuTop ).toBeGreaterThan( dashboardBottom );

		// The top-level item has a green background.
		const background = await devMenu
			.locator( 'a.menu-top' )
			.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
		expect( background ).toBe( 'rgb(70, 180, 80)' );

		// A separator sits between Dashboard and the dev menu (the space above it).
		const separatorTops = await page
			.locator( '#adminmenu li.wp-menu-separator' )
			.evaluateAll( ( els ) =>
				els.map( ( el ) => el.getBoundingClientRect().top )
			);
		expect(
			separatorTops.some(
				( top ) => top >= dashboardBottom && top < devMenuTop
			)
		).toBe( true );

		// The submenu is always expanded inline (not an off-screen hover flyout), even though we are
		// on the Dashboard page rather than the dev page.
		const emailsLink = devMenu.locator(
			'a[href*="edit.php?post_type=test_payment_emails"]'
		);
		await expect( emailsLink ).toBeVisible();

		// A shortcut to the demo gateway's WooCommerce settings screen, where the mailbox fields render.
		const gatewayLink = devMenu.getByRole( 'link', { name: 'Gateway settings' } );
		await expect( gatewayLink ).toBeVisible();
		await expect( gatewayLink ).toHaveAttribute(
			'href',
			/admin\.php\?page=wc-settings&tab=checkout&section=my-payment-gateway-id$/
		);
		const emailsLinkTop = ( await emailsLink.boundingBox() )!.y;
		expect( emailsLinkTop ).toBeGreaterThanOrEqual( 0 );
		const submenuPosition = await devMenu
			.locator( 'ul.wp-submenu' )
			.evaluate( ( el ) => getComputedStyle( el ).position );
		expect( submenuPosition ).toBe( 'relative' );
	} );

	test( 'create order then send payment email reconciles (pays) the order', async ( {
		page,
	} ) => {
		await page.goto( PAGE_URL );
		await expect(
			page.getByRole( 'heading', { name: /Dev Tools/ } )
		).toBeVisible();

		// Click via the element's own click(): WordPress 7.0 admin view transitions overlay the
		// page while animating, so a coordinate-based click (even with force) can hit the
		// transition snapshot instead of the button and silently do nothing.
		await Promise.all( [
			page.waitForURL( /created=\d+/ ),
			page.locator( '#create-demo-order' ).evaluate( ( el: HTMLElement ) => el.click() ),
		] );

		const link = page.locator( '#created-order-link' );
		await expect( link ).toBeVisible();
		const href = ( await link.getAttribute( 'href' ) ) ?? '';
		const orderId = Number(
			new URL( href, 'http://localhost' ).searchParams.get( 'id' )
		);
		expect( orderId ).toBeGreaterThan( 0 );

		await Promise.all( [
			page.waitForURL( /sent=1/ ),
			page
				.locator( '#send-payment-email' )
				.evaluate( ( el: HTMLElement ) => el.click() ),
		] );
		await expect( page.locator( '.notice-success' ) ).toBeVisible();

		// The mock email is persisted and visible in the emails list, which links to the order.
		await page.goto( '/wp-admin/edit.php?post_type=test_payment_emails' );
		await expect( page.locator( '#the-list tr' ).first() ).toBeVisible();
		await expect(
			page.locator(
				`#the-list a[href*="page=wc-orders&action=edit&id=${ orderId }"]`
			)
		).toBeVisible();

		// The mock email should have reconciled the order, so it is now paid.
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`
		);
		const status = await page.locator( '#order_status' ).inputValue();
		expect( [ 'wc-processing', 'wc-completed' ] ).toContain( status );

		// JS turns the email message id in the order notes into a link to the email post.
		const emailLink = page.locator( 'a.bh-wp-oer-email-link' );
		await expect( emailLink ).toBeVisible();
		await expect( emailLink ).toHaveAttribute( 'href', /post=\d+|post\.php/ );
	} );
} );
