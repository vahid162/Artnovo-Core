# Current project state

## Mission and authority

On **2026-11-15**, the owner requested fixes to Core's confirmed payment issue,
a short handoff for Bridge's receipt-policy issue, and safe independence when
either plugin is removed. Base: `28ed9a877ac10969cf4f08592f81c2ebea604880` (merged
PR #1). Branch: `work/fix-core-bridge-isolation`.

The [governance policy](development-governance.md) owns delivery rules. This request
authorizes Core development, tests, documentation and a branch/PR. It does not
authorize changing Bridge, merging this PR, releasing a ZIP or installing on the
live site. The earlier merge instruction applied only to PR #1, already merged.

## Plan and risk

**High risk:** payment context and dependency activation change. Preserve owner/key,
OTP, receipt, refund, snapshot and upstream gateway checks. Before implementation,
select isolated regressions for request scoping, nested/error cleanup, missing
Bridge, ordinary-store fallback, old-service payment denial, recovery and a distinct
independent review. Do not exercise real customer payments or deactivate live plugins.

1. Limit the cart-based gateway guard to cart checkout, preserving gateways already
   authorized for payment of an existing order.
2. Make Bridge optional for general Core features. Keep service purchasing/payment
   unavailable without its payment/issuance owner; preserve native WooCommerce.
3. Verify synthetic denial/fallback/recovery cases, review independently, and open
   one PR with read-only CI. Leave production and published 1.0.0 unchanged.

## Behavior and limits

- Selecting a new plan still replaces the eligible hidden cart; it does not delete
  previously created WooCommerce orders. Existing wallet-submission and rollback
  protections remain in place.
- Without Bridge, Core keeps general modules/email-login helpers, uses native
  WooCommerce for signed-in ordinary accounts and checkout, and blocks identifiable
  service orders before payment. Retained order/item snapshots identify old service
  orders even if their product was deleted. No billing or issuance code is copied.
- Bridge has no Core code dependency and retains its native WooCommerce backend,
  account and receipt routes when Core is removed. Core's wizard, header and
  Elementor dynamic tags belong to Core; the existing home design needs replacement
  links before permanently removing it. Identical site presentation after deleting
  the plugin that owns it is not promised.
- Core's stricter receipt policy is preserved. Bridge's native receipt upload/render
  policy must be reviewed/fixed in its own project, without introducing a Core
  dependency. No shared eligibility API exists yet.

## Verification and delivery

Implementation and local verification are complete. Passed: 14 bootstrap scenarios,
43 checkout and 63 independence assertions, 40 migration assertions, 29 PHP lints,
6 documentation regressions, 14 release-stub cases, 5 package cases and 6 external
synthetic hidden-cart checks. A distinct independent review found/fixed one guest
fallback regression and then reported no actionable findings. Isolated source
recovery reproduced the baseline tree exactly. See the source-bound
[verification](evidence/2026-11-15-bridge-isolation/verification.md).

Next delivery step: publish only this branch and open a **draft PR**, then verify
hosted checks on its exact head. Controlled WordPress/browser acceptance has not
run, so draft delivery remains conditional. Earlier PR #1 checks do not cover it.

The plugin remains version **1.0.0** during development. Runtime changes need an
appropriate new version before a separately authorized release. Published tag,
assets and installed source remain unchanged. No live deactivation, real payment,
provider call, migration, customer write or production acceptance is claimed.

Repository enforcement observations from PR #1 remain unchanged: rulesets returned
an empty list and classic branch-protection inspection returned 403. Procedural
review/check gates apply; enforcement is not inferred from that response.
