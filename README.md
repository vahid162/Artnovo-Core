# Artnovo Core

The site-specific WordPress plugin for ArtNovo. It combines the purchase wizard,
hidden-cart checkout, sign-in presentation, customer panel, order confirmation,
receipt form, header behavior and default sender in one package.

Customer-facing transient notices share a bottom-left five-second toast with a
close control and countdown. Hover/focus and a hidden tab pause its reading time.
Native validation nodes and handlers remain in place; order/payment state, field
errors and permanent guidance remain accessible in their local context. The
post-order service screen keeps the private native URL, receipt and delivery APIs
inside a compact layout; no payment or issuance is moved into Core.

## Requirements

- WordPress 7.0+, PHP 8.5.x and WooCommerce 11.2.1+.
- The site's existing Elementor and Pinova.
- Independently installed WP VPN Bridge for service purchasing, receipts and delivery.
- Keep Elementor Pro and TeraWallet installed where the current site uses them.

Core preserves the existing routes, Elementor identifiers, nonce actions, options,
session keys, payment/receipt authorization and script dependencies. The bridge
continues to own service issuance, encrypted receipt storage and private delivery.

Bridge is an optional integration: without it, Core's
header and email sign-in remain loaded, signed-in accounts use native WooCommerce,
and service purchases/payments stop safely. Ordinary WooCommerce checkout stays
available.
Removing Core leaves Bridge's native WooCommerce routes and backend independent,
but removes Core's wizard and Elementor dynamic tags. A page designed around those
tags needs replacement purchase links before Core is permanently removed.

## Optional integrations and sign-in

Core, WP VPN Bridge and TeraWallet remain independently installed and released.
No one of these three is a required plugin dependency of another. Their common
WooCommerce dependency and Core's existing Elementor/Pinova requirements remain.

| Owner | Responsibility | When the optional integration is absent |
| --- | --- | --- |
| Core | Purchase presentation, Pinova entry, email-code fallback and account views | Bridge and Wallet keep their native WooCommerce screens and backend. Pages using Core dynamic tags need native replacement links when Core is removed. |
| Bridge | Service purchase terms, payment authorization, receipt policy, issuance and service notifications | Core's general sign-in/account and ordinary WooCommerce checkout stay available; service payments stop safely without an issuer. |
| TeraWallet | Balance, funding and transaction ledger | Bank-transfer service purchases remain available; the optional wallet payment method is unavailable. |

The compact purchase form accepts email, mobile or username in one field.
Mobile registration and username/password proof use Pinova; email signup/login retains Core's existing email-code engine. Without
JavaScript the form preserves the purchase and opens the native sign-in route.
Pinova 1.3 does not register a new customer from an email identifier alone, and a
username is not an OTP destination. The chosen plan and quantity survive sign-in.

The standalone guest account and public customer `/login/` screens reuse Pinova's
native component in Core's minimal skin. Pinova keeps its proof engine, administrator
and third-party return routes. When its component is unavailable, Core retains its
existing email sign-in fallback.

The protected, authenticated virtual-service wizard needs no checkout contact fields
or email address. An existing valid account email is optional order metadata;
phone-only customers receive their account details in their private customer panel.
Ordinary checkout, taxable products and wallet funding keep their native fields.

An order email is separate from the sign-in identifier. An email address must not
silently merge accounts or change their owner. Bridge's ready notification uses
the customer's account email, so entering a billing address alone must not be
treated as verified account-email completion.

Core owns account presentation and plan-checkout context. The selected My Accounts
layout uses a responsive right-side navigation and native subscription rows, with
copy/QR and private purchase details. Usage and renewal appear only with actual
integration data; email completion is optional. Registered order, profile and wallet
endpoints share the presentation frame while their native WooCommerce/extension
handlers keep processing, authorization and data ownership. Without Bridge the
signed-in account retains the native WooCommerce fallback. Ordinary carts remain
with their native handlers. Core does not maintain a
second wallet balance or issue services; receipt eligibility belongs to Bridge.

Service shopping keeps one current plan without a customer-facing cart. Selecting
another eligible plan silently replaces the previous unpaid selection, including
its quantity; sign-in and browser return must not resurrect a stale selection.
This never deletes an existing order, a submitted wallet attempt or payment evidence.

## Install and update

After release approval, install the verified `artnovo-core-1.5.1.zip` package from
the published release. The command below builds a local candidate; a successful
build alone does not authorize installation. The package contains an
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
php tests/account-native-login.php
php tests/checkout-context.php
node tests/compact-auth.js
node tests/compact-checkout.js
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
