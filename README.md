[![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards)

# BH WP Order Email Reconcile

Mark orders as paid by reconciling payment-confirmation emails against unpaid orders.

Emails are fetched and stored by [BH WP Mailboxes](https://github.com/BrianHenryIE/bh-wp-mailboxes/).
When new emails arrive, this library parses them and matches them to unpaid orders by order id
(from the payment note), customer payment id (e.g. Venmo/$CashTag), email address, then name.

The core is **integration-agnostic**: it deals only with `Unpaid_Order` objects supplied by an
`Unpaid_Orders_Provider_Interface`. WooCommerce is supported today; GiveWP is scaffolded as a
second integration. See [`ARCHITECTURE.md`](ARCHITECTURE.md) and [`PLAN.md`](PLAN.md).

## Usage

```php
$settings = new My_Reconcile_Settings(); // implements Email_Reconcile_Settings_Interface
$logger   = Logger::instance( $settings );
BH_WP_Order_Email_Reconcile::make( $settings, $logger );
```

Call `make()` when your plugin file loads. It only registers hooks: the integrations (WooCommerce,
GiveWP) are checked for availability at query time, so it does not matter whether those plugins
have loaded yet. Do not defer instantiation past `plugins_loaded` — the cron jobs are
(un)scheduled from `plugins_loaded` callbacks, which would then never run.

Email accounts (address, display name, filters) and their credentials are owned by BH WP Mailboxes,
which stores credentials encrypted through the WordPress Secrets API; this library never handles
them.

To let store owners manage the mailbox, append the library's settings fields to a WooCommerce
payment gateway's form fields:

```php
$this->form_fields = ( new Credentials_Settings_Fields() )->append_imap_reconcile_fields( $this->form_fields );
```

This adds an "Add account" button to the gateway's settings screen, which opens BH WP Mailboxes'
add/edit IMAP account modal (credentials are saved by that library and the connection is tested),
plus a "Manage accounts" link to the emails list screen. That screen's accounts table is where accounts are enabled/disabled, edited,
checked now, or deleted, and where recent login failures are flagged.

## Development

See [`CONTRIBUTING.md`](CONTRIBUTING.md).
