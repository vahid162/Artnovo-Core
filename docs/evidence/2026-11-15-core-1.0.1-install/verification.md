# Core 1.0.1 installation verification — 2026-11-15

Owner authority: explicit request to install the new Core on artnovo.ir.
Source baseline: merged PR #2, `f93c10049109e21673b474824f8c3aa290af033b`.
Risk: high (production code replacement); staging was explicitly waived earlier.

Package: 28 regular files, no symlinks or unexpected archive paths. SHA-256:
`2ecb78de61483b693e5805057a3a09ab59d474ebb9363b7b728cd89e59bd5193`.
Modules are byte-identical to the merged baseline; the entrypoint changes only
header/runtime version from 1.0.0 to 1.0.1. The release fixture uses a synthetic
1.0.0 release intentionally; no public release workflow was dispatched.

Passed: bootstrap 14, checkout-context 43, independence 63, migration 40,
release-stub 14, package 5, document checker/regressions and PHP syntax checks.
Distinct independent package reviewer: `/root/account_receipt_audit`; archive bytes,
module equivalence and bootstrap/package/release checks passed independently.
The package mismatch test still requires an actual version mutation and rejection.

Preparation passed: installed runtime matched published 1.0.0; private source
backup verified; same-filesystem atomic exchange and exact source restore
rehearsed using disposable private copies. Pre-install homepage and guest account
both returned HTTP 200 with their expected Core UI. No customer data was inspected.

Installation **passed**. Core 1.0.1 is active according to WordPress, all 28 files
match the package, and the maintenance flag is absent. FPM drained to one active
process (the status request itself) and zero queued requests before atomic exchange.
FPM invalidated all 15 Core PHP scripts, with no cached-script failures; three
site-specific FastCGI cache files were purged. Origin homepage/account checks
returned 200 with the expected UI. Public homepage/account also returned 200;
account headers contain private/no-store and www returned 301 to the apex.

One initial external header probe falsely failed because it kept only the last of
two Cache-Control headers. Rechecking all header values verified the unchanged
private/no-store directive on both public and origin responses; no server setting
was changed to satisfy that probe.

Activation, wp-config and Bridge identities stayed unchanged. The old source,
reviewed scripts, exact ZIP and private operational receipt are retained outside
Git/web root. See the [redacted result](deployment-result.json). The active path
kept its canonical basename and www:www ownership; no activation hook was invoked.

Distinct exact-script reviewer: `/root/account_receipt_audit`. Its in-memory failure
checks found and then verified fixes for unsafe maintenance removal on failed
recovery and missing FPM cache evidence. Final review had no remaining blocker.
Happy install, successful rollback, failed rollback retaining maintenance, initial
drain abort, and disabled/unavailable OPcache paths were exercised with substitutes.
Actual live rollback was unnecessary; private exchange/restore rehearsal passed.

Initial source local commit `425ffc5141c33032b4437a76147edc1308d1984c` maps to GitHub
`44063d8c63b1f625757e1fd0bc7606e1ad1c5e9f` with identical tree
`6c684508755288523ed262e6b12c56947dacc1ee`. Push/PR CI passed that revision before
installation. Later commits change only evidence/state. Source delivery and its
current-head validation are owned by [PR #3](https://github.com/vahid162/Artnovo-Core/pull/3).

Not run: staging/browser checkout, actual debit, receipt approval or provider
issuance. No such outcome is inferred from source tests or HTTP health checks.
No Bridge change, real payment, migration, public GitHub tag/release or plugin
activation/deactivation is part of the requested installation.

## Repository merge authority

An initial PR #3 merge attempt was rejected by automatic authorization review:
the installation request alone did not explicitly cover main. No merge or main
write occurred. The owner subsequently answered “بله، PR شمارهٔ ۳ را مرج کن” to
the explicit PR #3 question. Recheck the exact current base/head and green CI
before the separately authorized squash merge. Public release/tag authority is
not inferred from that answer.
