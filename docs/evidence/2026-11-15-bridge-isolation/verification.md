# Core / Bridge isolation verification — 2026-11-15

Base: `28ed9a877ac10969cf4f08592f81c2ebea604880`.
Candidate branch: `work/fix-core-bridge-isolation`.
Risk: high (payment context and optional dependency activation).

## Source identity and scope

[source-manifest.json](source-manifest.json) binds each changed implementation,
test and stable-document file by SHA-256. Current-state and evidence receipts are
excluded from that manifest to avoid self-reference; their final commit is verified
separately. Remote/local commit and hosted-check identities are recorded at delivery.

Bridge source, production WordPress, plugin activation state, customer records,
provider/payment state and published v1.0.0 assets were not changed. Core's stricter
refund/snapshot receipt policy remains; the Bridge-side discrepancy is a separate
handoff. The development ZIP is not a replacement for the published release.

## Actual checks

Environment: PHP 8.5.12 CLI, Python 3.10.12. Commands ran from the Core checkout.

| Status | Check | Observation |
| --- | --- | --- |
| passed | PHP lint entrypoint + modules/tests/tools | 29 PHP files |
| passed | `php tests/bootstrap.php` | 14 scenarios; optional Bridge, mandatory dependencies, legacy collision, sender and repeated boot |
| passed | `php tests/checkout-context.php` | 43 assertions; existing-order vs cart, nested/batch cleanup, upstream denial, route normalization, missing-Bridge classic/REST service denial and ordinary native fallback |
| passed | `php tests/independence.php` | 63 assertions; account/template, native guest flow, service identity, owner/key and missing-Bridge receipt denial |
| passed | `php tests/migration.php` | 40 synthetic assertions; initial sandbox run rejected its temporary backup as inside Git, then the isolated fixture passed outside that sandbox; no live migration |
| passed | `python3 tools/check.py` / `python3 tests/governance.py` | Canonical links and 6 structural regression cases |
| passed | `python3 tests/release.py` | 14 stubbed publication cases; no real dispatch/tag/release |
| passed | `python3 tests/package.py` | 5 deterministic/exclusion/rejection checks |
| passed | `python3 tools/package.py` | 28-entry unpublished development ZIP; version still 1.0.0; SHA-256 `5b4e6cf0c031da19d7f3b9d9d271aedddcaa1087896c47cce8c1cfee71c36794` |
| passed | External hidden-cart candidate harness | 6 checks: new plan only, old order session pointers cleared, quantity update, gateway/add rollback, submitted-wallet protection |
| passed | Isolated source recovery | Reverting the candidate in a disposable Git repository restored the exact baseline tree; see [recovery](recovery.json) |
| not run | Site-dependent integration fixtures and browser checkout/payment | No controlled staging WordPress/Bridge environment was allocated; do not bootstrap/deactivate customer production to obtain this evidence |
| not run | Real payment, debit, approval or provider issuance | Outside authorized development scope; mocks prove none of these outcomes |

The external cart harness SHA-256 was
`498d2019f942e75f4f07562d0e157748cd98c6e08b12969ff7fd0bc822bf1845`.
It loaded candidate checkout source plus installed Bridge wallet source with
synthetic objects; it did not bootstrap WordPress or access database/mail/network.
The permanent site-dependent cart fixture remains available for controlled staging.

Regression sensitivity: the initial checkout test produced four expected failures
before its context fix; route-normalization cases then exposed/fixed case and
leading-zero gaps. The independence suite against isolated baseline source produced
24 failures. These are callback tests, not end-to-end WooCommerce dispatch proof.

## Independent review and recovery

Distinct reviewer: `/root/account_receipt_audit`, which did not implement runtime
or tests for this change. Its first review found a genuine ordinary-guest regression
in the new no-Bridge fallback. The implementer fixed forced login and preserved
native Woo guest confirmation only with a valid key. Private Core output still
requires a signed-in owner and key; guest service data is never rendered by Core.
New guest regressions cover this finding. Final re-review found no actionable
findings in the implementation; the reviewer independently passed bootstrap 14,
checkout 43, independence 63, documentation and whitespace checks. All 15 manifest
hashes matched; manifest SHA-256 is
`f76d3728e0a467c753be2c5b908ffa8a16fa48c97fa60f9ff160ea9e746a41f3`.
Combined sorted path/NUL/file-hash/newline digest is
`a9b14d7603f3b948a90291c597a2fdef4e317501b03f6e8eb8150475e398954c`.
This agent review is not a GitHub approving review.

Recovery is code-only. A source revert cannot undo payment, provisioning, settings
or customer writes; none were performed here. Before live installation, preserve
activation/configuration/data recovery separately and validate the candidate in a
controlled environment. Release needs an appropriate new plugin version first.

## Delivery gate

Keep this PR **draft/conditional** pending controlled WordPress acceptance for the
changed payment/optional-dependency paths. Hosted standalone validation is a
separate gate and must match the actual PR head. No merge, publication, installation
or production acceptance is implied by the branch or its local passes.
