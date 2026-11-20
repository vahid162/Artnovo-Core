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

Before an executable change, the agent MUST identify the capability's owner and
check that owner's existing settings, components and supported APIs. The required
order is: **identify the owner, use sufficient native facilities, then write the
smallest necessary code in the responsible component**.

Page banners, header content and page layout MUST use the existing Elementor or
WordPress facilities when sufficient. Product prices, stock and gateway settings
MUST use WooCommerce and the responsible extension's native configuration. Lack of
panel access, an MCP connection or another editing tool is not evidence that native
facilities are insufficient and MUST NOT justify a replacement in Core.

A Core code change MUST address a capability owned by Core; needing code or
appearing in the customer interface does not establish ownership. Step navigation,
preserving the selected plan through sign-in, and presenting an owner's actual
payment or delivery state belong to Core's presentation and purchase coordination.
Amounts, payment/retry eligibility, authentication proof, receipt access and service
readiness MUST come from their owners' supported integration points. Core MUST NOT
copy or bypass their financial, authentication, wallet or issuance rules.

Existing behavior and native WordPress/WooCommerce facilities MUST be reused. Code
MUST remain organized in bounded modules under one plugin entrypoint. New settings,
frameworks, dependencies, and update services require a concrete task need. Adopting
this rule does not authorize rewriting existing templates or moving existing
behavior between components without a scoped task.

### V. Verify Releases and Preserve Rollback

Each release MUST have one plugin version, a matching source tag, release notes, and
an installable package. Before a live replacement, source and activation state MUST
be recoverable. Verification MUST cover affected dependency loading, routes, auth,
purchase state, and receipt behavior. Results MUST distinguish synthetic checks
from real customer transactions.

## Scope and Dependencies

| Owner | Responsibility |
| --- | --- |
| Artnovo Core | Custom purchase/account presentation and coordination, selected-plan context, existing header behavior, email-code entry and sender-address integration. |
| Elementor / WordPress | Editable page content, banners, header/footer content and native page/template layout. |
| WooCommerce | Cart, order totals and adjustments, stock, registered gateways and the native order/payment lifecycle. |
| Pay Bridge ArtNovo / its payment service | Bank attempts and verification, matching payment evidence, recording verified payment through WooCommerce, and bank retry eligibility. |
| WP VPN Bridge | Service purchase/payment authorization, private receipt policy/storage and service issuance/delivery. |
| Pinova | Its native registration/sign-in and authentication proof; Core reuses its supported integration and retains its existing email-code flow. |
| Wallet extension | Balance, funding, transfers and the transaction ledger. |

Core MUST use these owners' existing public integration points and preserve their
authorization. Its existing email-code flow is not a mandate to build another
authentication engine. Packaging MUST NOT alter external settings, customer
records, orders, or server configuration.

Optional integrations MUST remain independent. Removing Wallet MUST NOT break
bank-transfer purchases or access to previously approved subscriptions that the
issuer still authorizes. An absent optional integration MUST limit only its
dependent capability safely, preserving independent features and existing native
fallbacks; Core MUST NOT invent a replacement ledger, verifier or issuer. This
does not remove the required runtime dependencies documented in the [README](../../README.md).

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

Amendment 1.1.0 makes native-first implementation and capability ownership explicit
to prevent unrelated presentation work from expanding Core into other components.
It preserves existing journeys, the Core email-code flow, required dependencies and
independent release cycles. It changes development rules, not runtime behavior or
merge, release or installation authority.

**Version**: 1.1.0 | **Ratified**: 2026-11-11 | **Last Amended**: 2026-11-20
