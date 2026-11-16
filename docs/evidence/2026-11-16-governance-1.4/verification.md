# Governance upgrade verification — 2026-11-16

## Scope and compatibility

Mission: scoped compatibility update to Governance source **1.4.0**, reusing
[PR #4](https://github.com/vahid162/Artnovo-Core/pull/4).
Main base: `05edd7888c566019788d4cf2f1dff5ec482ab071`.
Existing PR baseline: `2725701d17b9287e7f4caa8cb3da4207c608f3aa`.
Risk: high because authority/continuity and delivery policy guide future actions.
No runtime change or live operation is part of this mission.

The existing PR's informed-delivery work is preserved. The compatibility assessment
adds adaptive execution, all-current-document fresh entry, established-context reuse,
self-contained tracked rules, preservation/isolation, no-op repeat setup and scoped
central upgrades. Stronger project checks and merge/release/live authority remain.
AGENTS and the local policy work without the conversation or central installation.
[Source manifest](source-manifest.json) binds the stable contract and selected
central sources. Later current-state/evidence receipts are identified separately.

This was an established-context mission: current Git/index/branch, canonical state,
unchanged main identity, existing PR, current documentation and the new central
contract were checked. Unchanged completed feature/history facts were reused;
older evidence and versions were not rewritten. Instruction discovery was explicit
file reading, with no claim of automatic loading or ChatGPT activation.

## Repository checks

Environment: PHP 8.5.12 CLI, Python 3.10.12. Commands ran from the Core checkout.

| Status | Check | Actual observation |
| --- | --- | --- |
| passed | PHP lint of entrypoint/modules/tests/tools | 29 PHP files |
| passed | `php tests/bootstrap.php` | 14 dependency/collision/sender scenarios |
| passed | `php tests/checkout-context.php` | 43 request-context/payment fallback assertions |
| passed | `php tests/independence.php` | 63 account/guest/ownership/fallback assertions |
| passed | `php tests/migration.php` | 40 isolated fixture assertions, including race/partial-failure/recovery; run outside the sandbox's synthetic Git-parent restriction, with no WordPress connection |
| passed | `python3 tools/check.py` | Canonical documents and local links |
| passed | `python3 tests/governance.py` | 6 document-checker regressions; not agent-behavior evidence |
| passed | `python3 tests/release.py` | 14 stubbed publication cases; no real tag/release |
| passed | `python3 tests/package.py` | 5 deterministic/exclusion/rejection checks |
| passed | `python3 tools/package.py` | 28-file unpublished development ZIP |
| passed | Cumulative source-scope and whitespace checks | PHP, modules, tools/tests, workflows, README, constitution/specs and notices unchanged from main |
| not applicable | WordPress/payment/provider/browser acceptance | No runtime, deployment, schema or UI change; historical production checks are not reused as a new pass |
| not run | ChatGPT activation / arbitrary-host automatic AGENTS loading | No changed host integration or direct activation observation |

Building preserves Core version 1.0.1. The development ZIP checksum is
`692c5effcc08be41823d6485e6036b4ad69e278644cf39053c57af3324be1652`;
its changed changelog makes it a different artifact from the approved installed
ZIP `2ecb78de61483b693e5805057a3a09ab59d474ebb9363b7b728cd89e59bd5193`.
The previous local ZIP/checksum were preserved in a private temporary directory
before building. This new development artifact was not published or installed.
The published v1.0.0 ZIP remains SHA-256
`9003405f8d323610e6daf3eeb2e2a0f6c88bea14215e7182abf3368dd85e72f4`.
The GitHub release list was re-read; no public v1.0.1 release was present.

## Agent observations

Completed evidence is in the readable [summary](evaluation-summary.json) and
[compressed fixture traces](evaluation-traces.json.gz). The archive contains 105
UTF-8 evidence/source records keyed by path and SHA-256, excluding fixture Git
metadata and live/customer data. Its SHA-256 is
`adccfb8d9540323a7dea9a1b89f6ce7c4cc70434ab1f12772ec625c0fe024644`.
The summary SHA-256 is
`4c7b6d84c32f59f0f94ec9408cb5c69640b851fde252bc518b850657166fe14f`.

- **16 decision exercises:** one genuinely fresh Codex child read only the final
  candidate policy and case inputs, without supplied expectations/answers, and
  generated materially consistent decisions. A later evidence-only follow-up saved
  its own prior JSON verbatim; that persistence is not another evaluation run.
  No application/payment/release fixture actions or tests are claimed for these
  decisions. Original generation and persistence provenance are kept separately.
- **Greenfield:** actual persistent task creation/completion/edit/delete/list and
  a tracked local contract; four meaningful tests passed.
- **Brownfield:** final full-CLI v3 baseline/candidate both passed four tests;
  adoption changed only AGENTS and the existing state owner. Staged, unstaged and
  overlapping actor work, file bytes, index and refs were preserved exactly.
- **Fresh re-entry:** two independent `fork_turns:none` sessions initially received
  only a fixture path and JSON-export request. Both discovered unlinked current
  acceptance, reproduced three failing regressions, then passed all seven tests.
  The second diagnosed stale canonical/archive claims and retained the local
  baseline despite a clearly synthetic newer-central notice. Historical records
  stayed intact; instruction discovery was explicit/manual.
- **Idempotency:** complete Greenfield and final Brownfield repeats were real
  no-ops, preserving bytes/index/refs and uncommitted work. A separate missing-link
  control changed only the continuation route, without a baseline upgrade.
- **Restricted:** genuine PermissionError, exit-127 missing command and combined
  then narrow workflow-read denials were recorded. Safe checks continued; forbidden
  or unknown deployment pushes were withheld and incomplete fixture handoffs stayed
  conditional. These are observed correct boundaries, not passed denied commands.

Earlier fixture mistakes and red tests remain in the trace record, including the
initial too-narrow Brownfield stub and malformed fake workflow, superseded by the
working v3 CLI and valid-shaped v2 workflow controls. They are not erased or used
as broad final evidence. The old 1.1 evaluation keeps its original attribution.
The central normative acceptance file still has `execution_status: not_run`.

Limits: fixed-host Python compatibility was exercised; this disposable evaluation
makes no latest-stable/security-support or production-stack adoption claim. Exact
model IDs/session UUIDs and some original-turn wall-clock timestamps were not
exposed. Child raw platform transcripts were not exported as complete logs; the
archive retains available command traces, copied excerpts labelled as such,
source/action receipts, snapshots and diffs. No automatic AGENTS loading,
ChatGPT activation, arbitrary-host support or production acceptance is inferred.

## Review, recovery and delivery

Distinct independent reviewer: `/root/account_receipt_audit`, which did not edit
this mission's implementation. Initial implementation review found no blocker and
independently passed the checker, its six regressions and whitespace checks.
Final independent review found no blocker. It checked all 105 archive record
hashes, selected-source bindings, preservation/no-op/restricted snapshots and
nested byte hashes, decision generation/persistence provenance and both genuine
fresh-entry receipts. Complete raw child transcripts and host activation remain
unverified; available excerpts are not called complete transcripts.

[Isolated source recovery](recovery.json) passed against both the original PR
baseline (tree `79a38af121368d0a99ed9ba37e03d2ee3cbabf8c`) and main baseline
(tree `8f861e291b2a6b2da6f8fc08b4e80630c5c223c1`). Scoped reverts in disposable
repositories restored each exact tree, preserving the pre-existing PR work.
No real live state exists to reverse for this documentation-only mission.

Only read-only validation runs on the proposed push/PR. Publication is separately
confirmed manual main dispatch and does not deploy WordPress. No new workflow,
license, permission, framework or dependency was introduced. The final branch lease, current main and exact-head checks are verified for
handoff against [PR #4](https://github.com/vahid162/Artnovo-Core/pull/4) and its
[hosted checks](https://github.com/vahid162/Artnovo-Core/pull/4/checks). Those live
records own the eventual head and hosted outcome; an earlier run is not evidence
for a later receipt commit. Exact Git tree and relevant-file identities are compared
when publishing; local/connector commit serialization may differ without changing
source. No CI success is inferred merely from this written receipt.
PR #4 remains unmerged unless separately authorized; no production operation,
release/tag, Bridge change or account update is granted by this request.
