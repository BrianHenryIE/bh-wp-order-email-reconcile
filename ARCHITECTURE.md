# Architecture

`bh-wp-order-email-reconcile` marks orders as paid by reconciling payment-confirmation emails
against unpaid orders. It builds on [`bh-wp-mailboxes`](https://github.com/BrianHenryIE/bh-wp-mailboxes),
which does the email fetching and storage.

## The core is integration-agnostic

The core API and `Email_Reconciler` have **no knowledge of WooCommerce, GiveWP, or any other order
source**. They depend only on two abstractions:

- `API\Model\Unpaid_Order` — one order/donation awaiting payment. Exposes the values needed to
  match a payment email (id, amount, customer payment id, email, name variants, paid status) and
  the mutations performed on a match (`add_meta`, `add_note`, `mark_paid`, `save`).
- `API\Unpaid_Orders_Provider_Interface` — supplies the `Unpaid_Order[]` to reconcile, plus an
  `is_available()` check so an inactive integration is skipped rather than fatal.

Each integration lives under `includes/integrations/<name>/` and provides concrete implementations:

| Integration | Provider | Order wrapper |
|-------------|----------|---------------|
| WooCommerce | `Integrations\WooCommerce\WC_Unpaid_Orders_Provider` | `Integrations\WooCommerce\WC_Unpaid_Order` (wraps `WC_Order`) |
| GiveWP (scaffold) | `Integrations\GiveWP\Give_Unpaid_Orders_Provider` | `Integrations\GiveWP\Give_Unpaid_Order` (wraps `Give_Payment`) |

`API\Aggregate_Unpaid_Orders_Provider` composites every integration's provider into one. The core
API is injected this single aggregate, so it still depends only on the interface while multiple
integrations can be active simultaneously.

> **GiveWP is an unverified scaffold.** It demonstrates the extension point but has not been run
> against an installed GiveWP, has no test coverage, and is **excluded from PHPStan** (no GiveWP
> stubs are available — see `phpstan.neon` `excludePaths`). To rely on it, install GiveWP, add it to
> PHPStan's `scanDirectories`, verify the `Give_Payment` query/mapping, and add wpunit coverage
> mirroring the WooCommerce provider test.

## Data flow

```
bh-wp-mailboxes (cron)                       this library
─────────────────────                        ────────────
fetch + save emails ── or ── REST email ingress
   │                            │
   └────────────┬───────────────┘
                └─ do_action( 'bh_wp_mailboxes_new_email', $plugin_slug, $emails_post_type, BH_Email_Account, New_Email_Interface )  (per email)
                                              │
                                  API::process_new_email()
                                              │
                          Unpaid_Orders_Provider_Interface::get_unpaid_orders()  ── Unpaid_Order[]
                                              │
                                   Email_Parser::extract()  ── Extraction_Result (saved to the email's meta)
                                              │
                                   Email_Reconciler::index_orders() + reconcile_email()
                                              │
                                   email log note + status: bh_email_saved (matched) / bh_email_processed
                                              │
                  match by: order id (note) → customer payment id → email → name
                                              │
                       on match: Unpaid_Order::mark_paid() / add_note() / add_meta() / save()
```

## Adding an integration

1. Create `includes/integrations/<name>/`.
2. Implement `Unpaid_Order` (wrap the integration's order object).
3. Implement `Unpaid_Orders_Provider_Interface` (`is_available()` + `get_unpaid_orders()`).
4. Register the provider, either by adding it in `Aggregate_Unpaid_Orders_Provider::get_providers()`
   or, from outside the library, via the `bh_wp_order_email_reconcile_unpaid_orders_providers` filter.

No core code changes are required to support a new order source.

## Instantiation

A consumer plugin calls `BH_WP_Order_Email_Reconcile::make( $settings, $logger )` when its plugin
file loads (at latest, early on `plugins_loaded` — the cron jobs are (un)scheduled from
`plugins_loaded` callbacks at priorities 20/21, so instantiation must not be deferred past that).
`make()`:

1. boots `bh-wp-mailboxes` (`BH_WP_Mailboxes::make`), which registers the email CPTs and cron;
2. constructs the aggregate unpaid-orders provider, which resolves the available integrations at
   query time;
3. constructs the `Email_Reconciler` and the `API`, which hooks the global
   `bh_wp_mailboxes_new_email` action (guarded by plugin slug and emails post type).

`$settings` implements `Email_Reconcile_Settings_Interface`, which extends
`BH_WP_Mailboxes_Settings_Interface`.

## Adding email accounts

Email accounts (address, display name, connection class, filters, status) and their credentials are
owned by `bh-wp-mailboxes`, not this library. Accounts are added through the mailboxes API
(`configure_email_account()`, `set_email_account_active()`, `delete_email_account()`); credentials
are saved with `save_account_credentials()`, stored encrypted through the WordPress Secrets API
(bh-wp-mailboxes bundles the `wordpress/secrets-api` feature plugin), and read back by the library
itself when it connects. This library never sees them.

### "Add account" on the gateway settings screen

`Integrations\WooCommerce\Credentials_Settings_Fields::append_imap_reconcile_fields()` adds a
`bh_wp_oer_mailbox_accounts` field to a gateway's WooCommerce Settings API form fields.
`Mailbox_Settings_Field` renders that field type (via `woocommerce_generate_{type}_html`) as
bh-wp-mailboxes' "Add account" button plus a "Manage accounts" link to the emails list screen, and
prints bh-wp-mailboxes' `Email_Account_Modal` in `admin_footer` (so the password never enters the
settings form). Saving goes through mailboxes' own AJAX handler, which upserts the account, saves
the credentials, and tests the connection. Enabling/disabling, editing, checking and deleting
accounts happen in the accounts table on the emails list screen, which mailboxes renders.

## Extraction patterns metabox

`Admin\Email_Extraction_Metabox`, registered by `make()` in wp-admin on the emails post type,
renders the `Extraction_Result` saved to the email's `bh_wp_oer_extraction` meta: per pattern set,
each regex with what it matched and in which body, then the merged values the reconciler used. It
never runs the regexes itself.

## Unreconciled orders page

`Admin\Unreconciled_Orders_Page` renders the orders the reconciler is waiting to match, using the
same `Unpaid_Orders_Provider_Interface` the reconciler uses (exposed by
`API::get_unpaid_orders_provider()`, i.e. the aggregate of every integration). The consumer decides
where the page lives by calling `register_submenu( $parent_slug, $capability )` on `admin_menu`.
`Admin\Unreconciled_Orders_List_Table` is a `WP_List_Table` over pre-fetched `Unpaid_Order`s with
no checkbox column or bulk actions, paginated in memory (the providers return every unpaid order).
For display it uses the `Unpaid_Order` methods `get_date_created()`, `get_customer_display_name()`
and `get_edit_url()`, which each integration implements alongside the matching-oriented getters.

## Cron start/stop

The email-fetch cron runs **only while there is reconciliation work to do**: it is scheduled when an
unpaid order exists for a configured gateway and cleared when none remain.

This is implemented entirely within this library (`WP_Includes\Cron_Scheduler`), with no changes to
`bh-wp-mailboxes`:

- The cron hook is mailboxes' own `{emails_cpt_underscored}_fetch_emails_job` (see `bh-wp-mailboxes`
  `Cron`), so when it fires it triggers the normal mailboxes fetch. The settings deliberately **omit**
  `fetch_emails` from `get_cron_schedules()`, so mailboxes does not keep it always-on.
- On every `plugins_loaded`, mailboxes' `Cron::add_cron_jobs()` (priority 20) unschedules any job not
  in `get_cron_schedules()` — including ours. So `Cron_Scheduler::enforce_cron_schedule()` hooks
  `plugins_loaded` at priority 21 (after mailboxes) and re-asserts the schedule from a cheap cached
  flag (`bh_wp_oer_{slug}_has_unpaid_orders`).
- That flag is refreshed (a real unpaid-orders query) only when an order changes. Each integration
  provides an order listener — `Integrations\WooCommerce\WC_Order_Status_Listener` hooks
  `woocommerce_new_order` / `woocommerce_order_status_changed` and, on `shutdown` (after the HPOS
  order transaction commits), calls `Cron_Scheduler::refresh_unpaid_orders_state()`.

The behaviour is covered by unit tests (`Cron_Scheduler_Unit_Test`) and the e2e spec
`tests/e2e-pw/specs/cron-start-stop.spec.ts` (driven through the development-plugin REST endpoints).
