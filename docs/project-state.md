# Current project state

## Mission and authority

The owner selected **My Accounts** (اکانت‌های من), requested a global UX/UI audit
and matching desktop/mobile improvements, and authorized scoped Core merge, release
and installation. Only Core runtime code may change. Bridge, Wallet and Pinova
remain independent owners of issuance, money and identity. Native Elementor/config
changes must be proposed in GitHub, reviewed, and applied through supported APIs
only after the associated official Core artifact is released/installed. Never edit
live plugin source or raw Elementor storage; never overwrite published assets.

The latest added scope is a compact post-order flow, bottom-left five-second
customer toasts with close/progress, and coupon/payment copy/spacing refinements.
This is developed separately in `work/compact-receipt-notices` while the1.4.1 routing
patch is delivered. Persistent order/payment state remains visible; transient
notifications use the shared toast presentation. Preserve native validation, OTP,
owner/order-key/nonces, private links, receipt policy and backend payment/issuance.

## Installed baseline

Core **1.4.1** is officially released and installed after PR11 merge
`eb9a9da0b76acc3f3a3c6322f01db2529896d894`, full tree
`37f2cf98e5f3f531976a562d0ce27ecb2be4423e`. Main CI43937295695 and publication
43937456955 passed. Official ZIP SHA256
`b9954651c2488b36de64052695852cc4ded07409a572f3e0359b051d3cb2a2a3` matches all32
live files. Recovery/health and unchanged Bridge/Wallet/Pinova manifests passed;
backup `/var/backups/artnovo-core/20261116T191624Z-zu8s2dpn`. PR10's archive ID
collision review is fixed/resolved. Page61/68 guards use qualified native is_page.

Selected My Accounts and global home/login/checkout/native catalog/legal/header/
footer improvements remain delivered. All planned native1.4.0/1.4.1 refinements
are published after the associated Core release/install and verified. Native source
references and display manifest record the observed result, including left-aligned
product prices with currency gap. No unrelated plugin source was edited.

## Current candidate and delivery gates

**1.5.0** in `work/compact-receipt-notices` is based on current1.4.1 main. Adds shared
customer toasts, compact post-order presentation and coupon/copy/spacing refinements.
All existing guards and native integrations remain. Independent reviews found and
fixed initial-notice announcement, copy-wrapper exclusion and hidden manual-copy
field issues. Thirty isolated JS lifecycle/security assertions and five asset
contracts pass. Final59/59 standalone/lint commands (all14 mandatory gates) and
7/7 native integration commands (357 assertions) passed. Browser toast lifetime/progress, focused reading pause, multi-source coverage,
manual dismiss, safe/native link actions and desktop/mobile placement passed.
GitHub CI/merge/publication and exact installed asset remain delivery gates.

See [compact completion evidence](evidence/2026-11-16-compact-completion.md) for the
current scope, actual tests and limits. Avoid financial/provider tests unless needed
for a new financial concern; this is presentation work with preserved backend APIs.

## Evidence and limits

[Account and1.4.1 evidence](evidence/2026-11-16-account-design.md) records native
source operations, source/artifact identity, synthetic native handler tests and
actual Chrome geometry/keyboard/copy fallback/QR/no-JS checks. Current QA uses
synthetic records with SQL/mail/HTTP writes blocked, and public read-only pages.
Real payment/provider issuance, OS clipboard, physical devices and actual browser
zoom200/400% remain untested. Private preview server/tunnel/browser still belong to
this ongoing task and must be stopped/removed after final QA. Retain aggregate
evidence only; no raw customer records, tokens or credentials in Git.

Earlier reconciliation and controlled order167/customer15 history are preserved in
[UI evidence](evidence/2026-11-16-ui-refinements.md) and dated prior evidence. Order167
is cancelled/unpaid without issuance; its credential/session/cart cleanup was done.
Do not reuse it or infer current financial/provider test success from that history.
