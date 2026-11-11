# Data Model: Preserved Existing State

This consolidation creates no new business entity, table, or data migration.

## Site configuration

- Existing feature option: `artnovo_checkout_email_otp`.
- Existing native payment settings: `woocommerce_bacs_settings` and
  `woocommerce_bacs_accounts`; retained without changes.
- `active_plugins` contains the installed normal-plugin entries. Replacement changes
  only the four legacy custom entries to `artnovo-core/artnovo-core.php` in one API update.
- Unrelated plugin entries MUST remain unchanged. Source snapshots and activation
  snapshots are private operational files outside the public repository/web root.

## Customer purchase and sign-in context

- Draft session key: `artnovo_wizard_checkout`.
- Account sign-in state key: `artnovo_account_email_auth`.
- Existing hashed transient prefix: `artnovo_otp_`; preserve scopes, expiry, rate
  controls, and lock behavior so valid challenges retain their original meaning.
- Orders, receipts, encrypted service data, and subscription units remain owned and
  stored by their existing WooCommerce/bridge components. Core relocates their UI
  integration and MUST NOT recreate or rewrite the records.

## Release identity

- Plugin slug/root: `artnovo-core` / `artnovo-core/`.
- First plugin version: `1.0.0`; matching source tag: `v1.0.0`.
- Distribution: one installable ZIP containing runtime code/assets and required notices.

## State transitions

Operational replacement is: captured baseline → verified staged package → coordinated
activation replacement → observed live verification. A failed replacement can return
to the captured activation/MU-file state. This does not change any payment, receipt,
service issuance, or customer authentication state machine.
