# Existing-journey characterization

These are the existing ArtNovo preservation checks, retargeted to Core. They use
synthetic users/orders, process-local WooCommerce sessions, guarded SQL reads,
captured mail, and blocked outbound HTTP. They do not submit a real payment or
save a customer, order, subscription, or receipt. The multipart check parses real
HTTP uploads and invokes native receipt validation, replacing only the synthetic
receipt SQL with harmless `SELECT` statements.

Use the current ArtNovo dependency stack with PHP 8.5, WooCommerce, Pinova, the
independent bridge, its existing schema, and the standard process-local WordPress
object cache. These are site characterization fixtures: configured service product
IDs **25, 26 and 27** are required by the cart checks. They are not a generic clean
WordPress installation suite.

## Candidate and output paths

Run each PHP fixture in a separate WP-CLI process. Supply absolute paths because
WP-CLI changes its working directory when `--path` is used. For example:

```sh
export ARTNOVO_WP_ROOT="/absolute/path/to/wordpress"
export ARTNOVO_CORE_DIR="/absolute/path/to/Artnovo-Core"
export ARTNOVO_TEST_ROOT="/absolute/path/to/Artnovo-Core/tests/integration"
export ARTNOVO_FIXTURE_OUTPUT_DIR="/tmp/artnovo-core-fixtures"
```

`ARTNOVO_CORE_DIR` defaults to this repository root. It may instead point to an
installed `wp-content/plugins/artnovo-core` directory while the tests stay in the
checkout. The PHP/OS user must be able to read both directories and write the
private output directory. Do not change home-directory permissions for these tests;
copy fixtures/runtime to an accessible temporary directory when necessary.

The normal fixtures require the candidate entrypoint and call `Artnovo\Core\boot()`
explicitly after WP-CLI has bootstrapped WordPress. Temporary option filters hide
legacy/Core activation entries only during this call; the database activation set
is never changed. The receipt and template-scope checks deliberately include only
their candidate module. Skip Core and the four legacy plugins in every CLI command
to prevent duplicate functions or testing an already loaded implementation.

## WP-CLI commands

Keep the skip list on every invocation. Skipping Wordfence here affects only the
test CLI process. Leave the external functional dependencies loaded.

```sh
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/account/verify-auth.php"
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/cart/verify.php"
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/account/verify-render.php"
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/received/verify-received.php"
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/received/verify-receipt.php"
wp --path="$ARTNOVO_WP_ROOT" --skip-plugins=artnovo-core,artnovo-account-ui,artnovo-header-ui,artnovo-home-order-ui,artnovo-plan-checkout,wordfence eval-file "$ARTNOVO_TEST_ROOT/received/verify-template-scope.php"
```

If the system `wp` wrapper uses another PHP version, invoke the WP-CLI PHAR with
the host's PHP 8.5 interpreter and `-d memory_limit=512M` instead. Retain the same
WP-CLI arguments and the host's required database socket environment.

The auth check captures its synthetic OTP in memory and verifies incorrect-code,
scope and replay handling. It does not deliver an email. The account/received
checks write synthetic HTML, with local assets inlined, only under
`ARTNOVO_FIXTURE_OUTPUT_DIR` (or `/tmp/artnovo-core-fixtures` by default). Do not
commit these HTML files or operational test logs.

## Actual multipart parsing with synthetic storage

```sh
export ARTNOVO_PHP_BIN="/absolute/path/to/php-8.5"
python3 "$ARTNOVO_TEST_ROOT/received/verify-multipart.py"
```

The runner inherits the paths above, binds PHP only to `127.0.0.1` on an ephemeral
port, checks a valid PNG and an invalid image, requires HTTP 303 back to the same
synthetic order, and always terminates its server. `ARTNOVO_PHP_BIN` defaults to
`php`. Its PHP bootstrap allows only WooCommerce and the bridge, blocks external
mail/HTTP and real writes before WordPress loads, and aborts if the synthetic
identity is already allocated. The server log is written to the external fixture
output directory.
