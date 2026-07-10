/**
 * Playwright configuration for bh-wp-order-email-reconcile end-to-end tests.
 *
 * Tests run against a wp-env site (default http://localhost:8888). The development-plugin supplies
 * REST endpoints and a `?login_as_user=` shortcut so tests arrange/assert via REST and touch the UI
 * only for the part actually under test.
 *
 * @see https://playwright.dev/docs/test-configuration
 */
import { defineConfig, devices } from '@playwright/test';

require( 'dotenv' ).config();

const WP_BASE_URL =
	process.env.BASEURL || process.env.WP_BASE_URL || 'http://localhost:8888';

// So @wordpress/e2e-test-utils-playwright uses the same base URL.
process.env.WP_BASE_URL = WP_BASE_URL;

export default defineConfig( {
	testDir: './tests/e2e-pw/specs',
	// The tests share one wp-env database (orders, the cron flag/schedule, email accounts), so they
	// must run serially to avoid cross-test interference.
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
	reporter: 'html',
	// wp-admin pages in wp-env are slow; the order/admin specs do several full page loads.
	timeout: 60_000,
	use: {
		baseURL: WP_BASE_URL,
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'setup',
			testDir: './tests/e2e-pw/setup',
			testMatch: /.*\.setup\.ts/,
		},
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: 'tests/e2e-pw/.auth/user.json',
			},
			dependencies: [ 'setup' ],
		},
	],
} );
