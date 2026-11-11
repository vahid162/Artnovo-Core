# Artnovo Core Constitution

## Core Principles

### I. Preserve Existing Customer Journeys

Changes MUST preserve the agreed purchase, email-code sign-in, subscription panel,
order confirmation, and receipt journeys unless the user authorizes a behavior change.
Existing routes, settings, session identifiers, nonces, and Elementor identifiers are
compatibility contracts. Moving code MUST NOT invalidate them.

### II. Protect Private Purchase Data

Private order, receipt, and subscription output MUST retain its owner and request
authorization checks and cache protections. Tests MUST use synthetic records or an
explicitly controlled test account. Publication MUST exclude credentials, customer
data, operational backups, and private infrastructure configuration.

### III. Keep the Bridge Independent

The VPN bridge MUST remain a separately installed dependency. Artnovo Core MUST use
its existing public integration points and MUST NOT embed or modify bridge source
as part of packaging. Existing third-party plugins keep their own release cycles.

### IV. Use the Smallest Complete Change

Existing behavior and native WordPress/WooCommerce facilities MUST be reused. Code
MUST remain organized in bounded modules under one plugin entrypoint. New settings,
frameworks, dependencies, and update services require a concrete task need.

### V. Verify Releases and Preserve Rollback

Each release MUST have one plugin version, a matching source tag, release notes, and
an installable package. Before a live replacement, source and activation state MUST
be recoverable. Verification MUST cover affected dependency loading, routes, auth,
purchase state, and receipt behavior. Results MUST distinguish synthetic checks
from real customer transactions.

## Scope and Dependencies

Artnovo Core owns the site's custom presentation, purchase flow, email-code entry,
customer panel, order confirmation, and sender-address integration. WooCommerce,
Elementor, Pinova, the wallet plugin, and the VPN bridge remain external. Packaging
MUST NOT alter their settings, customer records, orders, or server configuration.

## Delivery and Verification

Use the project's feature specification and tasks to bound implementation. Keep
changes reviewable, run focused checks, and record unresolved failures honestly.
Legacy and replacement modules MUST NOT execute together. Release packages MUST
contain runtime files and required licenses, excluding development and private files.

## Governance

This constitution governs project specifications and reviews. Amendments MUST state
their rationale and compatibility impact; user instructions take precedence. Use
semantic versioning for constitution amendments independently of plugin releases.
Review each change against the applicable principles before publication.

**Version**: 1.0.0 | **Ratified**: 2026-11-11 | **Last Amended**: 2026-11-11
