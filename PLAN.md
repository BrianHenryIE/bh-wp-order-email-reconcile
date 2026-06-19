
# Plan / status

Modernisation of `bh-wp-order-email-reconcile` to match `bh-wp-mailboxes`, plus abstracting the
WooCommerce coupling so the core deals only with unpaid orders.

## Phase 1 — dev environment (done)

- Added `.wp-env.json` (WordPress 7.0, PHP 8.4, WooCommerce + development-plugin activated on start).
- Added `bin/sync-composer-wpenv.php` and `bin/modify-autoload-patchwork.php`; wired as composer
  `post-autoload-dump` scripts (replacing the Google apiclient `pre-autoload-dump`, which this
  library does not need).
- Added `package.json`, `playwright.config.ts`, `tsconfig.json`, `patchwork.json`.
- Updated `phpcs.xml`, `phpstan.neon`, `composer.json` (modern require-dev incl. mockery,
  woocommerce-stubs, wp-crontrol, rector), `.gitignore`.
- Modernised `tests/wpunit.suite.yml` (`lucatume\WPBrowser\Module\WPLoader`, dbUrl, port 33066),
  `codeception.dist.yml` (coverage over `includes/`), `.env.testing`, `tests/bootstrap.php`.

## Phase 2 — WooCommerce abstraction (done)

- `API\Model\Unpaid_Order` enriched: id, amount, paid status, payment id, email, name variants, and
  mutations (`add_meta`, `add_note`, `mark_paid`, `save`).
- New `API\Unpaid_Orders_Provider_Interface` and `API\Aggregate_Unpaid_Orders_Provider`.
- `Email_Reconciler` and `API` now depend only on the abstraction — no `WC_Order`, no `wc_get_orders`.
- WooCommerce moved under `includes/integrations/woocommerce/`:
  `WC_Unpaid_Orders_Provider` + `WC_Unpaid_Order`; `Credentials_Settings_Fields` namespace fixed.
- GiveWP scaffold added under `includes/integrations/givewp/` to demonstrate the extension point.
- `BH_WP_Order_Email_Reconcile::make()` rewired; `::instance()` alias added; file renamed to
  `class-bh-wp-order-email-reconcile.php`.
- Removed the broken `API\Unpaid_Orders` and the superseded `API\WC_Unpaid_Orders`.

## Phase 3 — tests + docs (done)

- Mockery unit tests: `Email_Reconciler`, `Aggregate_Unpaid_Orders_Provider` (incl. empty list),
  `API`, `Email_Parser` (rewritten with a `BH_Email` fixture), `Cron_Scheduler`.
- WooCommerce wpunit tests: `WC_Unpaid_Orders_Provider` + `WC_Unpaid_Order` mapping/mark-paid and
  the customer-payment-id meta-key path; `BH_WP_Order_Email_Reconcile::make()` asserts the reconcile
  hook is registered.
- Playwright e2e specs all pass: `instantiation`, `email-account`, `cron-start-stop`.
- `ARCHITECTURE.md` updated: cron start/stop and the GiveWP scaffold caveat.
- Lint/tests now run green locally: `phpcs`, `phpstan` (level 8), `codecept run unit`,
  `codecept run wpunit`, `npx playwright test`.

## Resolved open items

1. **Cron start/stop — implemented (this library only).** `WP_Includes\Cron_Scheduler` +
   `Integrations\WooCommerce\WC_Order_Status_Listener`. mailboxes' own `Cron::add_cron_jobs()`
   unschedules `fetch_emails` on `plugins_loaded:20` (we omit it from `get_cron_schedules()`), so the
   scheduler re-asserts the schedule on `plugins_loaded:21` from a cached flag, refreshed by the
   order listener on `shutdown`. See ARCHITECTURE.md.
2. **development-plugin REST endpoints — added** under `development-plugin/rest/` (namespace
   `bh-wp-order-email-reconcile-dev/v1`): `/status`, `/cron/fetch-emails`, `/orders`,
   `/orders/{id}/pay`, `/email-accounts` (GET/POST), plus `class-authentication.php`
   (`?login_as_user=`). `BH_WP_Order_Email_Reconcile::make()` now exposes the mailboxes API via
   `get_mailboxes_api()` so consumers can manage accounts.
3. **development-plugin drift — fixed.** `Settings` re-based on the current
   `BH_WP_Mailboxes_Settings_Interface` (+ defaults trait) and `Logger_Settings_Interface`;
   `Mailbox_Settings` removed; `Extraction_Settings` now uses the helper trait; gateway id
   reconciled to `my-payment-gateway-id` and registered; autoload path made wp-env-aware.

## Still open

- **GiveWP** remains an explicit, unverified scaffold (per decision): excluded from PHPStan (no
  stubs) and documented in ARCHITECTURE.md. Needs an installed GiveWP + `scanDirectories` entry +
  wpunit coverage to graduate.
- **bh-wp-mailboxes account query bug** (out of scope here — "this library only"): the duplicate
  check in `add_email_account()` filters by `post_name`, which WP_Query ignores, so it false-positives
  once any account exists. The dev REST endpoint resets accounts before creating to work around it.
  Worth fixing upstream (proper `meta_query` on `email_address`).
- **wp-env requires the sibling `bh-wp-mailboxes` repo**: `vendor/brianhenryie/bh-wp-mailboxes` is a
  relative symlink; `.wp-env.json` maps `wp-content/uploads/bh-wp-mailboxes` → `../bh-wp-mailboxes`
  so the in-container symlink resolves.
