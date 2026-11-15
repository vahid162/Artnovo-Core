# Governance adoption verification

Recorded on 2026-11-15. Scope is the owner's Artnovo Core standardization request,
not a plugin runtime release, production acceptance or update of the governance
plugin itself. The adoption changes operational rules and release activation, so
its tier is **high**. Required checks were selected in project state before edits.

## Source identity

- Base: `547a7dd6a13c423a75d51160920cac57516d3163`.
- Tested implementation: `d8d20896e699ea51d1d8bae4fd7efec269d8bd73`.
- GitHub equivalent implementation: `20bff55015f2898cac97c909b2b8629b793f2460`.
  It has the exact same Git tree `93fe64810525d5145cda57870ea181b45c392d10`;
  the connector creates its own commit identity, so local and GitHub commit SHAs differ.
- Exact reviewed file identities: [source manifest](source-manifest.json).
- Any later state/evidence-only commit must preserve the implementation files
  other than the current-state receipt, pass documentation checks and be checked
  at the exact remote head. A matching artifact alone does not cover CI/policy.
- Plugin runtime, migration/package implementation, constitution, specifications
  and integration fixtures compare byte-for-byte unchanged against the base.

Environment: connected Linux development host, PHP **8.5.12**, Python **3.10.12**.
No customer environment was loaded by these tests. The migration fixture was run
outside the filesystem sandbox because its injected `/tmp/.git` marker correctly
triggers the private-backup guard; the guard and expectations were not weakened.

## Actual checks

| Command/action | Class | Result | Scope/limit |
| --- | --- | --- | --- |
| `python3 tools/check.py` | Structural | passed | Canonical docs and local links; no external URL fetch or semantic/agent proof. |
| `python3 tests/governance.py` | Structural regressions | passed, 6 cases | Missing state/link, valid current versions/encoded links and path/symlink escapes. |
| `php tests/bootstrap.php` | Isolated runtime regression | passed, 14 scenarios | Mock WordPress/dependencies; no live auth or payment. |
| `php tests/migration.php` | Isolated failure/recovery regression | passed, 40 assertions | Races, retries, rollback, private backup and preservation; no production migration. |
| `python3 tests/release.py` | Isolated publication contract | passed, 14 cases | Local `gh` stub; 8 existing states plus 6 unauthorized event/ref/confirmation denials. |
| `python3 tests/package.py` | Isolated distribution regression | passed, 5 cases | Repeatability/exclusion, README links, version mismatch, linked runtime and missing notice. |
| `php -l artnovo-core.php` and all `*.php` in `modules`, `tests`, `tools` | Syntax | passed, 27 files | No claim of business integration coverage. |
| `python3 tools/package.py` | Development build | passed, 28 entries | Development ZIP SHA-256 below; no upload/install. |
| `git diff --check` | Structural | passed | Reviewed diff whitespace. |
| Temporary local clone, `git revert --no-edit d8d20896e699ea51d1d8bae4fd7efec269d8bd73`, rebuild | Recovery | passed | Restored exact baseline tree and published artifact bytes; no live-state revert. |
| Hosted GitHub validation | Hosted execution | pending at this receipt | Must observe exact submitted head before calling the PR ready; current result is linked from project state. |
| Runtime WordPress/browser/payment checks | Integration | not applicable to unchanged runtime | Not run or represented as current production acceptance. |
| Actual publication or merge | Live effects | not run | Not authorized for this mission. |

Development artifact SHA-256:
`cdd82bfa767df50b0eedb58e6efa28fd44fef57de9c98a914cb262400f2abcdf`.
It contains changed README/changelog bytes at version 1.0.0 and must not replace
the published v1.0.0 ZIP. Released assets/tags remain untouched.

Recovery restored tree `afdcb86f48068420c4236e1e0b3d712b57f5c992`, exactly the base,
and ZIP `9003405f8d323610e6daf3eeb2e2a0f6c88bea14215e7182abf3368dd85e72f4`.
Reverting the workflow would also restore its old automatic `main` publication
trigger; any real rollback must recheck that operational effect before activation.

## Independent review and regression evidence

Reviewer **`/root/account_receipt_audit`** is a distinct Codex agent that did not
implement this change. It reviewed all 11 implementation files, authority and
release gates, versions/history, package boundaries and connected code/tests.
This is technical review, not a GitHub approving review or merge authorization.

One finding was fixed: packaged README linked to excluded development documents.
The new archive-link regression failed on `artnovo-core/AGENTS.md` before the fix.
Canonical GitHub links replaced those relative links while preserving exclusions.
The reviewer independently reran all 5 package tests, 6 document tests and checker;
restoring the broken links in a temporary fixture reproduced the failure.
No findings remained in its final review. Its 11-file candidate digest was
`c11b60b019955f3100305bd0657f39105b8609b20a029473855e0926611c1880`,
computed from sorted `relative_path + NUL + file_SHA256_hex + newline` records.

The CI implementer also removed the manual-publication Bash guard in memory;
all six new denial cases then failed. Existing publication-body behavior was
compared with the baseline; draft/incomplete release and existing-tag protection
remain covered. No actual release workflow was dispatched.

## Actual agent decisions, kept separate from structural tests

An isolated Codex agent **`/root/governance_decisions`** read the selected policy
and 16 scenario inputs without reading expected answers. Its actual decisions,
source hashes, tool-access trace and parent evaluation are in
[agent-decisions.json](agent-decisions.json). The inherited model's exact runtime
identifier was not exposed; the host was Codex desktop/local tool execution.

Parent evaluator `/root` compared each recorded decision with the unchanged
upstream specification. All 16 passed **at decision-only scope**: fix, review,
no-target, readme-version, readme-history, versions, test-fails, prod-missing,
payment, push-deploy, dirty-worktree, standing-release, handoff, unrelated,
injection and self-review. The specification's `execution_status: not_run`
was not edited or presented as a result.

No full upstream fixture implementation or ChatGPT activation test ran. This is
repository adoption of the unchanged skill, not a private skills-package update.
Project fixture actions (release denials, package failure/exclusion, source recovery)
were actually exercised separately above. The birthday/injection cases describe
their isolated scenario choices; they do not claim that the evaluation harness
made no source reads. These observations cannot guarantee future agent behavior.

## Remote and operational boundaries

GitHub reports the repository public and its merge methods available. The initial
rulesets read returned `[]`; classic `main` protection returned 403 to the connector.
Protection and plan eligibility are therefore unverified. Nothing changed account
access, protections, visibility or spending. Feature-branch publication is scoped
to read-only validation; main's old release workflow remains active until merge.

Selected merge method: **squash**, for one coherent, reversibly scoped adoption.
Merge, tag, release and deployment still require separate owner authorization.
Production server/CDN work requested in the earlier conversation is an independent
operational task and is not evidence of deploying this repository branch.
