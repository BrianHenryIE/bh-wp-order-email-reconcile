[![PHPCS WPCS](https://img.shields.io/badge/PHPCS-WordPress%20Coding%20Standards-8892BF.svg)](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards)

# BH WP Order Email Reconcile

Mark orders as paid by reconciling payment-confirmation emails against unpaid orders.

Emails are fetched and stored by [BH WP Mailboxes](https://github.com/BrianHenryIE/bh-wp-mailboxes/).
When new emails arrive, this library parses them and matches them to unpaid orders by order id
(from the payment note), customer payment id (e.g. Venmo/$CashTag), email address, then name.

The core is **integration-agnostic**: it deals only with `Unpaid_Order` objects supplied by an
`Unpaid_Orders_Provider_Interface`. WooCommerce is supported today; GiveWP is scaffolded as a
second integration. See [`ARCHITECTURE.md`](ARCHITECTURE.md) and [`PLAN.md`](PLAN.md).

## Usagew

```php
add_action( 'plugins_loaded', function () {
    $settings = new My_Reconcile_Settings(); // implements Email_Reconcile_Settings_Interface
    $logger   = Logger::instance( $settings );
    BH_WP_Order_Email_Reconcile::make( $settings, $logger );
} );
```

Email accounts (server, credentials, filters) are configured through the BH WP Mailboxes API; this
library never stores credentials.

## Development

```bash
composer install
composer dump-autoload          # after adding/renaming classes

# Lint + static analysis
composer lint                   # phpcbf + phpcs + phpstan

# Unit + wpunit tests (needs the wp-env DB on port 33066)
npm install
npm run wp-env:start
vendor/bin/codecept run unit
vendor/bin/codecept run wpunit

# End-to-end (Playwright, against wp-env on :8888)
npm run test:e2e
```

The development plugin provides a fake WooCommerce gateway, places a fake order, configures the
library with test mailbox credentials, and sends a fake payment email to verify reconciliation.
