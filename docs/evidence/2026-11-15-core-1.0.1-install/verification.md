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

Installation is pending. The operational plan uses owned native maintenance,
bounded FPM drain, atomic directory exchange, FPM-specific Core invalidation,
scoped FastCGI cache cleanup, and post-install HTTP/activation/source checks.
After traffic resumes, rollback must reestablish maintenance and drain first.
The old source and private operational receipt are retained outside Git/web root.
Activation, wp-config and Bridge identities must remain unchanged.

Not run: staging/browser checkout, actual debit, receipt approval or provider
issuance. No such outcome is inferred from source tests or HTTP health checks.
No Bridge change, real payment, migration, public GitHub tag/release or plugin
activation/deactivation is part of the requested installation.
