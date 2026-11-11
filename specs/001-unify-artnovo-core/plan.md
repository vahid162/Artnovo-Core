# Implementation Plan: Unified Artnovo Core Release

**Branch**: `feat/artnovo-core-v1` | **Date**: 2026-11-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-unify-artnovo-core/spec.md`

## Summary

Package the existing custom site features into the normal WordPress plugin
`artnovo-core` version 1.0.0. Preserve existing namespaces, public identifiers,
persistence, templates, assets, and security behavior. Replace the four old custom
plugin activations together; keep the VPN bridge and other third-party plugins
independent. Publish the verified source and an installable release ZIP.

## Technical Context

**Language/Version**: Existing PHP 8.5, JavaScript, CSS; Python standard library for packaging.

**Primary Dependencies**: Existing WordPress, WooCommerce, Elementor/Pro, Pinova,
wallet plugin, and separately installed `wp-vpn-bridge` 0.2.5. Retain current APIs.

**Storage**: Existing WordPress options, WooCommerce sessions, and native order/bridge
storage. No new schema, data rewrite, uninstall routine, or customer-record migration.

**Testing**: PHP syntax checks, focused bootstrap fixtures, existing isolated auth/cart/
account/received/receipt characterization harnesses, package inspection, and controlled
live route/browser checks. Block unintended mail, HTTP, and business-data writes in fixtures.

**Target Platform**: The current ArtNovo Linux WordPress installation and ordinary
WordPress plugin ZIP installation with the current external dependencies.

**Project Type**: One site-specific WordPress plugin with bounded internal modules.

**Performance Goals**: Preserve existing customer interactions and conditional asset
loading. No plugin-count speed improvement or new throughput claim is required.

**Constraints**: Preserve URLs, Elementor IDs/tags, option/session/nonce identifiers,
private owner/key checks, receipt validation, and hook/asset ordering. No secrets in Git.

**Scale/Scope**: Four custom plugin directories plus one sender MU file; the existing
three-step purchase, customer panel, header, and order-received journey.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Pre-research and post-design decision |
|---|---|
| Preserve customer journeys | Pass: relocation and bootstrap only; existing contracts retained. |
| Protect private data | Pass: retain authorizers/cache rules and isolate fixtures; package excludes private data. |
| Independent bridge | Pass: use existing classes/assets; no bridge source is copied or edited. |
| Smallest complete change | Pass: keep existing feature code; no framework, module toggles, or update service. |
| Verified release and rollback | Pass: source/activation snapshot, focused verification, single versioned package. |

No constitution exception is required. Recheck these gates against the final diff and
actual verification evidence before publication.

## Project Structure

### Documentation (this feature)

```text
specs/001-unify-artnovo-core/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/compatibility.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
artnovo-core.php
modules/
├── auth/             # Existing email-code helper and account handler; original namespaces
├── checkout/         # Existing plan selection, hidden cart, payment and error templates
├── home/             # Existing wizard, dynamic tags, CSS and JavaScript
├── account/          # Existing account renderer, templates, assets and local QR notices
├── order-received/   # Existing state renderer, receipt handling and assets
├── header/           # Existing header navigation enhancement
└── mail/             # Existing default-sender filter

tests/
├── bootstrap.php
└── integration/      # Retargeted synthetic characterization harnesses

tools/
├── package.py
└── migrate.php

.github/workflows/release.yml
README.md
CHANGELOG.md
```

**Structure Decision**: One root plugin entrypoint with the agreed feature directories.
Keep old feature file basenames where useful. Moving auth and received files requires
only their include, asset, and fixture-path corrections. Do not merge feature code into
one large file or rename existing runtime contracts.

## Implementation Units

### U1. Package the existing runtime

- Copy the characterized feature sources and required QR notice into their module paths.
- Preserve existing namespaces, Elementor dynamic tags, script/style handles, query/POST
  names, nonce actions, options, session keys, and hook priorities.
- Load the sender filter early when the legacy sender was not included in that request. Load feature modules
  after dependencies are ready at `plugins_loaded` priority 30, preserving the original
  same-priority callback registration order established by the activation snapshot.
- Fail safely before duplicate module includes when a legacy plugin remains active;
  report missing dependencies without removing unrelated features or settings.
- Retarget moved includes/assets/templates and retain the external bridge asset reference.

### U2. Preserve and verify behavior

- Reuse existing isolated auth, cart, account, received-state, and receipt checks.
- Characterize the existing baseline; then retarget fixture paths and skip lists without
  weakening authorization, write/mail blocking, or synthetic-record boundaries.
- Add focused bootstrap checks for dependency readiness, legacy collisions, single module
  loading, and sender behavior. New branch logic gets a failing assertion before its fix;
  mechanical relocation is validated by baseline comparison rather than duplicate tests.
- Check installed asset URLs, native financial/account return routes, and desktop/mobile
  presentation. A controlled mailbox may receive an OTP; real customer payments are excluded.

### U3. Replace the live custom activation set

- Record private source/activation snapshots outside the web root and repository.
- Recheck source hashes before replacement; abort rather than overwrite concurrent edits.
- Guard the native activation update with a prepared binary expected-value condition so a later activation change cannot be overwritten.
- Install the verified runtime package. Through WordPress APIs, replace only the four
  legacy activation entries with Core in one `active_plugins` update, preserving every
  third-party entry. Do not uninstall old plugins or delete their settings.
- Remove the legacy sender MU file after confirming Core supplies the sender filter;
  preserve the file externally for rollback. Retain server mail/cache configuration.
- Verify settings and bridge hashes, live routes, and effective sender behavior. Provide
  a rollback path for activation and the old MU file without rewriting business data.

### U4. Publish release 1.0.0

- Build a deterministic installable ZIP with one `artnovo-core/` root and runtime files
  plus required notices. Exclude tests, tools, Spec Kit, Git metadata, and private artifacts.
- Publish one source version and release notes to the supplied repository. The release
  workflow uses GitHub's scoped runner token, creates tag `v1.0.0` at the verified commit,
  and attaches the installable ZIP. It must not overwrite an existing release silently.
- Document ZIP-based installation/update; the plugin has no private update service in v1.

## Verification Contract

| Gate | Required evidence |
|---|---|
| Runtime | PHP syntax passes; bootstrap fixtures cover normal, missing-dependency and legacy-conflict paths. |
| Compatibility | Existing auth/cart/panel/state/receipt/private-link assertions retain their expectations. |
| Real upload parsing | Controlled valid/invalid multipart receipt checks keep synthetic storage and reject invalid images. |
| Presentation | Installed assets resolve and representative desktop/mobile pages keep current layout and actions. |
| Preservation | Feature/payment settings and bridge source compare unchanged; only intended custom activation entries change. |
| Rollback | Isolated migration/rollback check preserves unrelated plugin entries and restores the prior set. |
| Distribution | ZIP contents, version and source agree; public repository/release contain no private artifacts. |
| Live completion | Observe the deployed plugin and the published release; Git cleanliness alone does not prove either. |

## Definition of Done

All three user stories and FR-001 through FR-012 are satisfied. Required gates have
observed results, or a concrete limitation is reported without claiming success.
No customer/order data or bridge/server configuration is changed by consolidation.
Source and versioned installable package are published, and the live custom plugin
replacement is verified. Progress belongs in tasks and verification records, not this plan.

## Complexity Tracking

No constitution violations or additional architectural layer require justification.
