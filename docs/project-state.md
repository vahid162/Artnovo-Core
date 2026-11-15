# Current project state

## Mission

The owner requested standardization of `vahid162/Artnovo-Core` with AI Development
Governance on **2026-11-15**. Work starts at
`547a7dd6a13c423a75d51160920cac57516d3163` on a clean checkout; the working branch is
`work/adopt-development-governance`. The [governance policy](development-governance.md)
owns stable rules; this file owns current status.

Scope: agent routing, authority/review/release boundaries, canonical documentation,
read-only PHP 8.5 CI, explicit manual publication and development checks. Plugin
runtime, business data, dependency versions, the constitution, completed Spec Kit
feature and production deployment are outside this repository change.

## Plan and risk

**High risk** because agent authority and release activation rules change, even
though application runtime does not. Required checks were selected before edits:
documentation checks and their negative regressions; PHP lint/bootstrap/migration;
unauthorized event/ref/confirmation rejection and existing release-state cases;
deterministic package/exclusion/failure cases; a distinct independent review;
isolated recovery to the baseline; and hosted validation on the submitted revision.

1. Preserve existing contracts and introduce one agent entry, one operational policy
   and one current-state document; link rather than duplicate historical records.
2. Separate read-only development checks from confirmed manual `main` publication.
3. Run selected checks/recovery and an independent review; publish only a branch/PR.
4. Record actual evidence and remaining limits here before handoff.

The initial standardization request authorized scoped development and a PR.
On **2026-11-15**, the owner subsequently instructed: "PR شماره ۱ رو مرج کن و وضعیت
نهایی ریپو رو بررسی کن." This authorizes merging **PR #1** and verifying the final
repository state. Use **squash** for this coherent change. It grants no standing
authority for other PRs, tags, releases, production deployment, access changes or
paid services. Keep released `v1.0.0` and its assets unchanged.

## Baseline and compatibility

- Core's current plugin version is **1.0.0**. Requirements remain in README; the
  [2026-11-11 release verification](release-1.0.0-verification.md) is historical,
  not evidence for this branch or the current production dependency stack.
- The existing [constitution](../.specify/memory/constitution.md) is 1.0.0; installed
  Spec Kit metadata is 1.3.2. Neither is being upgraded or reinitialized.
- The initial GitHub read returned no repository rulesets. Classic branch-protection
  inspection returned **403**; enforcement and plan eligibility remain unverified.
- The pre-adoption release workflow published on pushes to `main`. PR #1 replaces
  that trigger with explicitly confirmed manual publication. Its resulting `main`
  tree runs read-only validation on push; it does not publish or deploy on merge.
  Do not dispatch publication for this mission.

## Verification and delivery

Implementation is committed locally as
`d8d20896e699ea51d1d8bae4fd7efec269d8bd73`; its GitHub equivalent is
`20bff55015f2898cac97c909b2b8629b793f2460`, with an identical source tree. The
[verification record](evidence/2026-11-15-governance/verification.md) and its source
manifest bind actual checks and review to that implementation. Subsequent receipt
changes are current-state/evidence only and must be checked separately.

- Passed: document checker, 6 document regressions, 14 bootstrap scenarios,
  40 migration assertions, 14 release cases, 5 package cases and 27 PHP syntax checks.
- Passed: distinct independent technical review after its README/package finding
  was fixed; isolated source revert and exact baseline ZIP reconstruction.
- Passed at **decision-only** scope: 16 isolated Codex policy scenarios. Full upstream
  fixture implementations and ChatGPT activation were not run; no such claim is made.
- Hosted validation: push and PR runs passed on
  `9180c7965e2ff3a91af23662ed875dc0637040f5`:
  [push run](https://github.com/vahid162/Artnovo-Core/actions/runs/43688348059) and
  [PR run](https://github.com/vahid162/Artnovo-Core/actions/runs/43688436323).
  These URLs identify that tested revision, not a later receipt commit. The latest
  head and its checks are owned by [PR #1](https://github.com/vahid162/Artnovo-Core/pull/1/checks);
  recheck them after any subsequent commit before calling delivery ready.
- Delivery branch is published. The current merge outcome and merge commit are
  owned by [PR #1](https://github.com/vahid162/Artnovo-Core/pull/1), and final `main`
  validation by [GitHub Actions](https://github.com/vahid162/Artnovo-Core/actions/workflows/validate.yml?query=branch%3Amain).
  Check those live records for completion instead of treating this pre-merge
  receipt as proof that the operation succeeded.
- The development ZIP is unpublished; existing v1.0.0 assets remain unchanged.
  Tagging, release publication and repository deployment are outside the merge request.

Integration/payment/browser tests are not applicable to unchanged runtime in this
mission; no production acceptance result is inferred from that exclusion.

## Follow-up work outside this mission

The earlier read-only audit found a catalog/checkout mismatch for future three-month
plans, missing OTP boundary regression cases and incomplete public SEO/content.
These are separate tasks, not fixed by governance setup. Bridge wallet qualification
and server/CDN operations belong to their independent operational scope.

The authorized handoff checks the exact PR base/head, conflicts, current checks,
independent review and workflow effects immediately before squash merge; then it
verifies the resulting `main` tree, hosted validation and clean local checkout.
Retain source/evidence recovery identities. After `main` validation, continue only
newly scoped development; publication and deployment need their own authorization.
