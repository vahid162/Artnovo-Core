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

Core **1.4.0** is officially published and installed after PR10 merge
`c2988d59b7085c544dbfef8e4d104a507a8de085`, tree
`f6047eb9c3b2efc62c8123aa984e345c2c3304cf`. Main CI43925745160 and publication
43925900948 passed. ZIP SHA256
`5c99394c330ac2ffdc4428df290ead1d0143d30a8d1d530b95cf5753fc761064` matches all32
live files; Bridge/Wallet/Pinova unchanged. Atomic recovery rehearsal and health
checks passed; backup `/var/backups/artnovo-core/20261116T181215Z-tjuu2j0o`.

Selected responsive account rows, right sidebar/mobile navigation, copy/local QR,
optional email and real native orders/profile/Wallet handlers are delivered.
Absent-Bridge signed-in fallback remains native Woo. Home/login/checkout/native
catalog/legal/header/footer improvements retain navy/mint/Vazir and native data.
Initial supported native operations were published after1.4.0 install. PR11's
GitHub-first placeholder wrapper and currency-spacing refinements are now applied
and verified; source references and display manifest are current.

## Current candidate and delivery gates

PR11 `work/accounts-delivery-evidence` adds **1.4.1**: actual-page type checks for
retired page61 and wizard68, correcting PR10 review4589577964 without changing
checkout authorization. Regression reproduced before fix;14 bootstrap scenarios
and72 native WP_Query type-collision cases now pass. Complete standalone/lint
49/49 and synthetic real-stack7/7 (301 assertions) passed. Independent native
WordPress behavior review found no blocker. GitHub CI, merge, official publication
and exact ZIP install remain pending. Existing tags/assets stay immutable.

The [1.4.1 native contract](site-design/artnovo/planned-1.4.1.json) proposes final
product-detail price/currency alignment after patch release/install. It is not yet
applied. Keep proposed and observed source separate until supported readback/QA.
Update final delivery evidence after installation and resolve the exact PR10 thread.

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
