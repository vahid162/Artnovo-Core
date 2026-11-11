# Compatibility Contracts

## Bootstrap and dependency boundaries

- Exactly one copy of each custom feature runs. A legacy activation collision is
  detected before Core includes its namespaced modules.
- Feature callbacks retain their priorities and source registration order where the
  same hook priority is shared. The bridge remains an external active dependency.
- The sender module preserves the existing default-address replacement predicate;
  it does not replace the mail transport or force unrelated custom sender addresses.

## Purchase and account interface

- Preserve `artnovo_plan`, `artnovo_category`, `artnovo_checkout`, wizard/account form
  fields, nonce actions, and validated return destinations.
- Preserve the three-step home purchase and internal-cart behavior; selecting a new
  plan silently replaces the old eligible selection.
- Preserve the first signed-in account view, My Subscriptions, and existing account/
  financial endpoint behavior.
- Preserve Elementor dynamic tag names `artnovo-plan-checkout`,
  `artnovo-order-preview`, `artnovo-wizard-plan`, current page/element/query IDs,
  existing asset handles, data selectors, and script dependency order.

## Private order and receipt interface

- Keep current order-owner and key checks, receipt eligibility, nonce validation,
  image validation, size limits, encrypted storage, and private download authorization.
- Same-page receipt POST and result return keep their existing fields and destination.
- Pending, receipt-received, issuing, ready, failed, expired/refunded/closed, review,
  and partial-delivery views retain their existing meaning and actions.
- Private account/purchase output retains no-store behavior and server cache bypass.

## Migration interface

- `tools/migrate.php` runs through WP-CLI with an explicit external snapshot location
  and documented dry-run/apply/rollback modes.
- It uses WordPress option APIs, never direct SQL, to replace only custom activation
  entries in one update. It does not invoke uninstall or delete legacy settings.
- It rejects an unexpected baseline instead of overwriting newer source/state.
- Operational mail/cache settings and all customer/order records are outside its writes.

## Release interface

- `python3 tools/package.py` builds the versioned artifact under `dist/`.
- The package has one `artnovo-core/` root and includes only the plugin entrypoint,
  runtime modules/assets, and required distribution notices/documentation.
- The release workflow publishes `v1.0.0` from the verified commit and attaches the
  installable package. Existing release/tag conflicts are reported, not overwritten.
- The README documents package installation/update and separate bridge requirements.
