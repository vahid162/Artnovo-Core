# Changelog

## Unreleased

- Adopt repository-specific AI Development Governance instructions, document
  ownership, risk/review gates and a canonical current-state record.
- Add read-only PHP 8.5 pull-request validation and separate explicitly confirmed
  manual publication from development pushes.
- Check documentation links and package exclusion, reproducibility and rejection
  boundaries with isolated fixtures. Plugin runtime and version remain unchanged.

## 1.0.0 — 2026-11-11

- Combine ArtNovo's custom home wizard, checkout, account panel, order confirmation,
  header and default sender integration in one modular WordPress plugin.
- Preserve email-code sign-in, silent plan replacement, private subscription delivery,
  same-page receipt upload, current settings and customer-session identifiers.
- Keep WP VPN Bridge and the existing third-party plugins independent.
- Add dependency/collision checks, a guarded WP-CLI migration and rollback tool,
  synthetic journey checks and an installable versioned ZIP.

Version 1 uses ZIP-based installation and updates. It does not add a private update
service or modify the mail transport, server cache rules, bridge, customers or orders.

Verification evidence is recorded in [release verification](https://github.com/vahid162/Artnovo-Core/blob/main/docs/release-1.0.0-verification.md).
