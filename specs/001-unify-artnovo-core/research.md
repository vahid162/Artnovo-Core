# Research: Unified Artnovo Core Release

All design questions in the supplied scope are resolved. This document records
packaging decisions; it does not claim implementation or test completion.

## Existing coupling and module boundaries

- **Decision**: Preserve the current feature namespaces and shared helper contracts,
  while grouping files under auth, checkout, home, account, order-received, header,
  and mail directories in one plugin.
- **Rationale**: Account email sign-in calls checkout OTP helpers; the home wizard
  and checkout also call each other. Their current identifiers already form one
  customer flow. Module relocation avoids an unnecessary behavior rewrite.
- **Alternatives considered**: Keep independent plugins (retains separate releases
  and coupled activation); combine every function into one file (loses boundaries).

## Loading and collisions

- **Decision**: Use one dependency-aware bootstrap after external dependencies are
  ready. Preserve existing callback priorities and registration order from the
  captured activation set. Register the sender early unless the old MU sender
  was included in that request; file existence alone is insufficient during rollback.
- **Rationale**: Copying active plugin files into Core without a coordinated switch
  would redeclare namespaced functions or register feature callbacks twice.
- **Alternatives considered**: Simultaneous activation (unsafe); invasive namespace
  rewrite (breaks compatibility and broadens scope).

## Data and live replacement

- **Decision**: Keep option/session/nonce names and use one WordPress API update of
  the custom activation entries. Back up code and activation state outside the web
  root; keep unrelated entries and every business record unchanged.
- **Rationale**: The existing custom source has no schema or uninstall migration to
  execute. Renaming persistence keys would interrupt valid drafts and login codes.
  The native option write retains WordPress hooks and caches; a narrowly scoped
  prepared binary comparison rejects a concurrent activation change at the write itself.
- **Alternatives considered**: Reset settings or uninstall old plugins (unnecessary
  data risk); direct SQL changes (bypasses the normal option API).

## Independent bridge and external dependencies

- **Decision**: Keep the bridge installed separately, including its native receipt
  storage, service authorization, and copy-script integration.
- **Rationale**: The requested work concerns site-specific packaging; bridge/PasarGuard
  development and server mail/cache configuration have their own scope.
- **Alternatives considered**: Vendor the bridge into Core (couples its updates and
  duplicates integration code).

## Release and verification

- **Decision**: Reuse synthetic characterization tests, add only bootstrap/migration/
  packaging checks needed by the change, and publish a runtime-only ZIP as v1.0.0.
  Use a GitHub Actions release workflow with its scoped token; document manual ZIP updates.
- **Rationale**: This tests the actual relocation and replacement contracts without
  creating customer payments or adding an unrequested update service.
- **Alternatives considered**: A private updater in v1 (outside scope); publishing
  a source archive containing development files as the installable artifact (wrong package).

## Evidence used

The current custom source and a bounded migration inventory identified the shared
helpers, include/asset paths, stable persistence identifiers, existing fixture paths,
external bridge asset, and absence of custom schema/uninstall/updater hooks. The
live activation snapshot remains the authority for the original registration order.
