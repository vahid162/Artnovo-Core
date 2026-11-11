# Validation Guide: Artnovo Core 1.0.0

## Prerequisites

Use PHP 8.5 and the current WordPress/WooCommerce/Elementor/Pinova/bridge installation.
Keep private snapshots outside the repository/web root. Use only controlled accounts
and synthetic order/receipt records during verification. See
[compatibility contracts](contracts/compatibility.md) for invariants.

## Local runtime and package checks

From the repository root, run:

```sh
php tests/bootstrap.php
php tests/migration.php
python3 tests/release.py
python3 tools/package.py
```

Expect the bootstrap assertions to pass and an installable
`dist/artnovo-core-1.0.0.zip` with one `artnovo-core/` root. Inspect the ZIP to confirm
no Git, Spec Kit, tests, tools, private snapshots, or credentials are included.
Run PHP syntax checks over the entrypoint and module files with PHP 8.5.

## Existing journey characterization

Retarget and run the integration harnesses under `tests/integration/` with WP-CLI.
Use their documented skip lists so legacy/Core functions are never included twice.
Keep write/mail/HTTP guards active. Cover email-code sign-in, draft/cart replacement,
account rendering, received-page states, private-link denials, receipt validation,
and real multipart parsing with synthetic storage.

Expect the same assertions to pass before and after relocation. Record observed
results separately; this guide is not a test receipt.

## Live replacement and preservation

1. Capture current custom source checksums, activation entries, and feature/payment
   setting fingerprints in the private external snapshot.
2. Review the documented dry-run output of `tools/migrate.php` through WP-CLI. Confirm
   only the four custom activation entries are replaced and unrelated entries remain.
3. Install the verified package and apply the coordinated replacement. Preserve the
   old MU sender externally after Core's sender filter is confirmed active.
4. Compare preserved settings and bridge checksums. Check anonymous login/return/cart
   routes, installed assets, and representative account/received fixtures at desktop
   and mobile widths. Verify email sending only with a controlled recipient.
5. Validate the rollback interface with an isolated activation fixture or rehearsal;
   do not alter customer/order records to test rollback.

## Published release

Open the supplied repository's v1.0.0 release after publication. Download its plugin
ZIP and compare version/runtime contents to the verified tagged source and installed
Core. A successful workflow alone does not establish the deployed site state.
