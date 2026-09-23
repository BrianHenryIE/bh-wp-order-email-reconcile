* /opt/homebrew/opt/php@8.4/bin/php
* Prefer Mockery in unit tests.
* Always run `phpcbf` + `phpcs` + `phpstan` on new and edited code
* UI changes should have Playwright tests
* Run Playwright tests before pushing or opening a PR
* Do not auto-commit unless explicitly requested by the user
* Run `composer dump-autoload` after creating new classes or changing namespaces
* Use `declare(strict_types=1);` in all PHP files
* Don't add PhpDoc return type when it is the same as the PHP function signature return type
* Do not abbreviate references to development plugin
* Prefer underscores in meta/option keys

