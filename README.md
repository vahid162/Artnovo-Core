# Artnovo Core

The site-specific WordPress plugin for ArtNovo. Version 1.0.0 brings the existing
purchase wizard, hidden-cart checkout, email-code entry, customer panel, order
confirmation, receipt form, header behavior and default sender into one package.

## Requirements

- WordPress 7.0+, PHP 8.5.x and WooCommerce 11.2.1+.
- The site's existing Elementor and Pinova.
- Independently installed WP VPN Bridge for service purchasing, receipts and delivery.
- Keep Elementor Pro and TeraWallet installed where the current site uses them.

Core preserves the existing routes, Elementor identifiers, nonce actions, options,
session keys, payment/receipt authorization and script dependencies. The bridge
continues to own service issuance, encrypted receipt storage and private delivery.

In the development source, Bridge is an optional integration: without it, Core's
header and email sign-in remain loaded, signed-in accounts use native WooCommerce,
and service purchases/payments stop safely. Ordinary WooCommerce checkout stays
available. This behavior is pending a new release; the published 1.0.0 ZIP is unchanged.
Removing Core leaves Bridge's native WooCommerce routes and backend independent,
but removes Core's wizard and Elementor dynamic tags. A page designed around those
tags needs replacement purchase links before Core is permanently removed.

## Install and update

Download `artnovo-core-1.0.0.zip` from the GitHub release. It contains an
`artnovo-core/` plugin directory; GitHub's automatic source archives contain the
development project and are not the WordPress installation package.

For an existing Core installation, upload the new versioned plugin ZIP through
WordPress Plugins → Add Plugin → Upload Plugin and replace the installed copy.
Keep a backup before replacing it. Version 1 has no automatic private update feed.

For the first migration from the legacy ArtNovo plugins, install Core's runtime
files without activating it alongside the old plugins. Follow the WP-CLI migration
instructions below. Core detects legacy activation conflicts before loading modules.

## Modules

| Directory | Responsibility |
| --- | --- |
| `modules/auth` | Shared email-code helpers and account sign-in |
| `modules/checkout` | Plan selection, internal cart and payment |
| `modules/home` | Three-step form and Elementor integration |
| `modules/account` | Subscription panel, help and private account views |
| `modules/order-received` | Payment/delivery state and same-page receipt form |
| `modules/header` | Header navigation behavior |
| `modules/mail` | Default WordPress sender address |

## Migrate and roll back

Run the development checkout's `tools/migrate.php` through WP-CLI as the WordPress
OS user. Use an absolute private backup directory outside WordPress and this
repository. The tool documents its modes in its usage error; inspect `dry-run`
before applying it. It preserves third-party activations and uses WordPress APIs
without uninstalling the old plugins or rewriting their settings.

```sh
mkdir -m 700 /private/artnovo-core-v1
wp --path=/absolute/path/to/wordpress eval-file /absolute/path/to/Artnovo-Core/tools/migrate.php dry-run /private/artnovo-core-v1
wp --path=/absolute/path/to/wordpress eval-file /absolute/path/to/Artnovo-Core/tools/migrate.php apply /private/artnovo-core-v1
# If live validation fails, use the same snapshot:
wp --path=/absolute/path/to/wordpress eval-file /absolute/path/to/Artnovo-Core/tools/migrate.php rollback /private/artnovo-core-v1
```

The migration snapshots the legacy sources and activation state, rejects concurrent
changes and replaces the four custom activation entries together. The old sender
MU file is retained externally for rollback. After in-flight requests finish and live
checks pass, archive legacy PHP code outside the web root. Retain byte-identical
CSS/JavaScript/license files at their old URLs while cached pages or browser history
may reference them. These static directories contain no plugin PHP entrypoint and
do not appear as separate WordPress plugins.

Mail transport and Nginx cache configuration remain server-managed. Moving the
sender filter does not change SMTP credentials or delivery routing.

## Verify and build

```sh
python3 tools/check.py
python3 tests/governance.py
php tests/bootstrap.php
php tests/checkout-context.php
php tests/independence.php
php tests/migration.php
python3 tests/release.py
python3 tests/package.py
python3 tools/package.py
```

Use PHP 8.5 for the production checks. The integration fixtures and guarded multipart
instructions are in [tests/integration/README.md](https://github.com/vahid162/Artnovo-Core/blob/v1.0.0/tests/integration/README.md).
They use synthetic records and do not authorize real customer payments or receipts.

The package builder includes only runtime files, this README, the changelog and
third-party notices. It excludes tests, tools, Git, Spec Kit and operational backups.
`dist/SHA256SUMS` identifies the versioned ZIP. Read-only GitHub Actions checks run
for pushes and pull requests with PHP 8.5. Publication is a separate manual workflow
on `main` with explicit confirmation; a push or PR does not publish a release.

## Development

Start with [AGENTS.md](https://github.com/vahid162/Artnovo-Core/blob/main/AGENTS.md),
the [development governance](https://github.com/vahid162/Artnovo-Core/blob/main/docs/development-governance.md)
and [current project state](https://github.com/vahid162/Artnovo-Core/blob/main/docs/project-state.md).
They define technical ownership,
verification, document ownership, release authority and the current delivery status.
The standalone development checks require PHP 8.5.x and Python 3.10+; no WordPress
installation or real customer transaction is needed for them.

The specification and compatibility contracts are in
[specs/001-unify-artnovo-core](https://github.com/vahid162/Artnovo-Core/tree/v1.0.0/specs/001-unify-artnovo-core).
