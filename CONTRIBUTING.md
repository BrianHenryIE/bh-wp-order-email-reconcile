# Contributing

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
