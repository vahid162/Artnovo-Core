# Current project state

## Mission and authority

On **2026-11-15**, the owner requested fixes to Core's confirmed payment issue,
a short handoff for Bridge's receipt-policy issue, and safe independence when
either plugin is removed. Base: `28ed9a877ac10969cf4f08592f81c2ebea604880` (merged
PR #1). Branch: `work/fix-core-bridge-isolation`.

The [governance policy](development-governance.md) owns delivery rules. The initial request authorized Core development, tests, documentation and a
branch/PR. The owner subsequently annotated the staging gate: “نیازی به تست روی
محیط ازمایشی نیست. اگه تست های ریپو کامل شده مراحل بعدی رو انجام بده”. This
current instruction waives staging as a merge prerequisite and authorizes the next
PR #2 delivery steps: mark ready and squash-merge after current repository checks
pass. It does not grant authority to modify Bridge or publish/install a new release.
The separate role-boundary request is read-only analysis.

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

Published as [draft PR #2](https://github.com/vahid162/Artnovo-Core/pull/2).
Implementation local commit `274d2d30798c65d556916cd1efff1b5706dfa2ce` maps to
GitHub commit `e3e7e86149cfb1a00ef9484dbc0579e03bd50d22`; both have source tree
`50baebce2ae99c4a338d463b0b8ebff063a189df`. This later receipt changes only state
and evidence. Exact current-head hosted results are owned by
[PR #2 checks](https://github.com/vahid162/Artnovo-Core/pull/2/checks); verify that
live record rather than treating an earlier head's run as evidence for this one.

Controlled WordPress/browser acceptance has not run. The owner explicitly waived
it as a delivery gate; it is not relabelled as passed. The existing repository
checks and distinct independent implementation review remain required. Recheck
current base/head, all hosted results and unchanged runtime/test hashes immediately
before marking PR #2 ready and squash-merging. Squash keeps one scoped recovery
commit for this coherent fix. No production acceptance is inferred from this waiver.
Actual merge outcome is owned by [PR #2](https://github.com/vahid162/Artnovo-Core/pull/2),
and final main validation by
[main CI](https://github.com/vahid162/Artnovo-Core/actions/workflows/validate.yml?query=branch%3Amain).

The plugin remains version **1.0.0** during development. Runtime changes need an
appropriate new version before a separately authorized release. Published tag,
assets and installed source remain unchanged. No live deactivation, real payment,
provider call, migration, customer write or production acceptance is claimed.

Repository enforcement observations from PR #1 remain unchanged: rulesets returned
an empty list and classic branch-protection inspection returned 403. Procedural
review/check gates apply; enforcement is not inferred from that response.
