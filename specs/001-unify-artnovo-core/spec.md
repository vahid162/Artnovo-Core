# Feature Specification: Unified Artnovo Core Release

**Feature Branch**: `feat/artnovo-core-v1`

**Created**: 2026-11-11

**Status**: Ready for planning

**Input**: User description: "Unify ArtNovo's custom plugins into Artnovo Core, keep the bridge independent, preserve current settings, and publish release 1 in vahid162/Artnovo-Core."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Manage One Site Plugin (Priority: P1)

As the site owner, I manage the site's custom features as one versioned plugin while
keeping the existing bridge separately maintainable.

**Why this priority**: Consolidation is the requested outcome and must preserve the
working site before a new release can be useful.

**Independent Test**: Inspect the active site plugins and compare the preserved
settings and bridge source before and after replacement.

**Acceptance Scenarios**:

1. **Given** the existing custom features and settings, **When** Core replaces their
   legacy plugins, **Then** one active custom plugin supplies those features and the
   bridge remains independently active and unchanged.
2. **Given** a recoverable pre-change snapshot, **When** replacement cannot complete
   safely, **Then** the old activation state can be restored without changing other
   plugins or customer records.
3. **Given** a legacy custom plugin is still active, **When** Core is loaded,
   **Then** it avoids executing a second copy of the same feature code.

---

### User Story 2 - Continue Existing Customer Journeys (Priority: P1)

As a customer, I keep using the current purchase, email-code sign-in, subscription
panel, and receipt journey without learning a different process after consolidation.

**Why this priority**: A management improvement must preserve customer access and
payment follow-up.

**Independent Test**: Run the existing representative guest, signed-in, order-status,
and receipt scenarios with controlled data, including mobile layouts.

**Acceptance Scenarios**:

1. **Given** a selected plan, **When** a customer signs in with email and code in the
   third purchase step, **Then** they can continue the same purchase without seeing
   a cart page or being sent through the account dashboard.
2. **Given** a customer opens the account, **When** they sign in, **Then** the first
   view remains My Subscriptions with the current responsive appearance.
3. **Given** a customer's purchase, **When** its payment or subscription preparation
   state changes, **Then** the order-received page shows the corresponding message
   and actions, and accepts an eligible receipt on that same page.
4. **Given** another customer's order link or an invalid private link, **When** it is
   opened, **Then** private order, receipt, and connection details remain inaccessible.

---

### User Story 3 - Install a Traceable Release (Priority: P2)

As the site maintainer, I download release 1.0.0 from the supplied repository and
install or update the same documented plugin version.

**Why this priority**: A single plugin also needs a reproducible distribution and a
clear source history.

**Independent Test**: Download the published installable package, inspect its
contents/version, and compare the runtime files with its tagged source.

**Acceptance Scenarios**:

1. **Given** the verified source, **When** release 1.0.0 is published, **Then** its
   release page includes an installable plugin package, matching source tag, and notes.
2. **Given** the downloaded package, **When** its contents are inspected, **Then**
   runtime files and required notices are present and private or development files are absent.

### Edge Cases

- A required external dependency is absent or not ready.
- A legacy plugin remains active or is reactivated later.
- A customer has an existing purchase draft, valid email code, or receipt.
- A private purchase URL has an invalid key or belongs to another account.
- A subscription is partially ready, expired, refunded, failed, or awaiting review.
- A receipt is invalid, too large, replaced, or submitted twice.
- A source file changes between snapshot and replacement.
- A release already exists or publication fails after source upload.
- A page is viewed at a narrow mobile width.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The site owner MUST manage the current custom header, purchase form,
  checkout behavior, sign-in, customer panel, order follow-up, and sender identity
  through one Artnovo Core plugin version.
- **FR-002**: Customers MUST retain email-code sign-in within purchase step three and
  continue the current purchase without a visible cart journey.
- **FR-003**: The account MUST continue to open on My Subscriptions and preserve its
  current mobile-compatible appearance and actions.
- **FR-004**: The order-received page MUST retain payment/preparation-specific messages,
  actions, private connection delivery, and eligible same-page receipt submission.
- **FR-005**: Replacement MUST preserve existing settings, routes, valid purchase
  drafts and login challenges, receipts, subscriptions, and order/customer records.
- **FR-006**: Existing owner authorization, request validation, private cache behavior,
  and receipt validation MUST remain enforced.
- **FR-007**: The bridge MUST remain a separately installed, unchanged integration.
- **FR-008**: Core MUST avoid duplicate execution when legacy custom plugins remain active.
- **FR-009**: Release 1.0.0 MUST include a matching source tag, release notes, and an
  installable package without private data, credentials, backups, or development files.
- **FR-010**: Replacement MUST preserve a recoverable snapshot and leave unrelated
  plugin activation and business records unchanged.
- **FR-011**: The current sender identity, including the configured orders mailbox,
  MUST remain effective for email-code sign-in.
- **FR-012**: Verification MUST characterize the existing auth, purchase, receipt,
  authorization, and responsive journeys and compare their behavior after replacement.

### Key Entities *(include if feature involves data)*

- **Site configuration**: Existing feature settings and the set of active plugins;
  only the custom-plugin activation entries change for this release.
- **Customer purchase context**: Existing sign-in challenge, purchase draft, order,
  receipt, and subscription owned by the customer; preserved rather than recreated.
- **Release**: A version, tagged source, notes, and its installable package.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After replacement, exactly one active site-specific plugin provides the
  consolidated features; the independent bridge is still active.
- **SC-002**: All selected before/after customer-journey and private-access scenarios
  pass without changing their expected outcomes.
- **SC-003**: Preserved feature/payment settings and bridge source have zero
  differences, and replacement creates or changes zero customer/order records.
- **SC-004**: The published release, package metadata, and source tag all identify
  version 1.0.0; package inspection finds zero private or development files.
- **SC-005**: A rollback rehearsal or isolated check restores the original activation
  set while retaining every unrelated plugin entry.

## Assumptions

- The user-authorized existing site behavior is the baseline; new UX, bridge features,
  and data migrations are outside this consolidation release.
- The supplied repository is the canonical public source and release destination.
- Updates in version 1 use the downloadable package; an in-dashboard private update
  service is outside this release.
- Existing server mail delivery and cache configuration remain in place outside the plugin.
- Controlled tests and synthetic purchases are sufficient; verification does not require
  a real customer payment or modification of a customer's records.
