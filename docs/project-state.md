# Current project state

## Completed outcome and authority

The owner selected **My Accounts** (اکانت‌های من), requested global UX/UI fixes,
matching mobile/desktop presentation, minimal native Pinova login, compact checkout
and post-order completion, and shared bottom-left customer notices. The ongoing
scoped authority covered Core GitHub development, merge, release and installation.
All scoped implementation, corrections and delivery are complete through
Core **1.5.1**, published and installed through the GitHub-first sequence.

Only Core runtime source changed. Bridge, Wallet and Pinova remain independent
owners of service issuance/payment policy, balances and identity. No copied ledger,
issuer or authentication engine was added. Native Elementor/config proposals were
recorded in GitHub before supported live application after the corresponding Core
release/install; current references/manifest match observed source. No raw Elementor
storage or live plugin source was patched. Published tags/assets are immutable.

## Installed source and artifact

PR13 merge/tag target `496583cc1fa6c62304d43a51ed51fe33b13a2dcc`, source tree
`f4cc27e1651cb8df1c33e105c1bf30bc8f022368`. PR/main/publication Actions
43944577362 /43944697736 /43944826163 passed. Official1.5.1 ZIP SHA256
`ef8ede79b1421ff4c76718c78e98f24c9ba24f6506dc5da4d098d2871639bc9b` equals the
frozen candidate and every one of35 installed files. Bridge/Wallet/Pinova source,
activation set and wp-config hash remain unchanged. Recovery backup/rehearsal:
`/var/backups/artnovo-core/20261116T195916Z-lnu19tps`. Atomic exchange/drain,18 PHP
opcode invalidations, zero cached failures and health checks passed; no maintenance
remains. Final delivery notes are documentation only; runtime bytes match the
official ZIP. Existing1.5.0 and older tags/assets remain unchanged.

Previously PR11/1.4.1 fixed actual-page routing for61/68; PR10 review4589577964 is
resolved. Those fixes and regression checks are included in1.5.0. The native price
alignment was applied only after official1.4.1 installation and readback verified
only its intended card style changed. Native designs did not change in1.5.0.

## Delivered behavior and checks

My Accounts keeps real native subscription rows, copy/local QR, optional email,
proper navigation and native order/profile/Wallet handlers. Missing-Bridge fallback
remains native Woo. Header/footer/home/catalog/legal and minimal Pinova login align
with navy/mint/Vazir at mobile and desktop sizes. Protected service checkout needs
no email, keeps silent unpaid plan replacement and native discounts/financial totals.
Coupon input/action share a row, label gap6px, login-info separation28px; payment
heading is clear. Post-order view gives summary, permanent real state, actual next
action and optional invoice details, with owner/key/receipt/issuance fences intact.

Temporary customer notices use five-second bottom-left toasts with close/progress.
Focus/hover/hidden tab pause reading time; permanent state, busy progress and field
validation keep their context. Original native sources/IDs/handlers remain. Safe
visible content, initial announcements and native copy/manual-selection are tested.

Final59/59 independent/lint commands include all14 mandatory gates,37 PHP lints and
8 JS syntax checks. All7 synthetic native WordPress commands passed357 assertions
with SQL/mail/outbound guards. Independent reviews found and rechecked three UI
issues before release; no blocking finding remains in the reviewed scope. Actual
Chrome passed popup lifetime/progress/placement, focused pause, native message
sources, safe link actions/dismiss, receipt Clipboard fault/manual selection,
Tab/Enter/QR and representative desktop/mobile geometry. First font/focus fixture
failures were diagnosed and rerun correctly; they were not counted as successes.
Live guest purchase1206/320 and native login390 passed; actual published assets
showed/closed synthetic browser-only notices at the expected position/time.

[Compact completion delivery evidence](evidence/2026-11-16-compact-completion.md)
and [account/native evidence](evidence/2026-11-16-account-design.md) record details.
Cleanup of this task's private preview/staging/browser/tunnel is verified and
recorded at the end of delivery evidence: no owned process/profile or port listener
remains. Preserve private recovery receipts; retain only nonsecret aggregate QA
results/public screenshots outside Git.

## Practical limits and history

No real order/customer/receipt/credit/message/payment/provider issuance was created
in this UI work. Physical devices, actual OS clipboard, screen-reader speech and
native browser zoom200/400% were not tested; emulation is not certification.
Earlier controlled customer15/order167 history and cleanup are preserved in
[UI evidence](evidence/2026-11-16-ui-refinements.md); that order is cancelled/unpaid
without issuance. No old test identity/token is reused or inferred to be current.

PR12 review4590621008 identified a real post-publication Pinova field-error
classification problem.1.5.1 removes only that field selector from temporary
transfer, preserving its local error until native validation clears it. Notyf and
other transient adapters remain unchanged. A red regression preceded34 passing
notice assertions and an independent scoped review; fresh59/59 standalone/lint
and7/7 native integrations (357 assertions) passed. The exact review thread is
resolved after the merged fix. Live browser red/green confirms the invalid field
remains visible/bound after5.1s while a simultaneous temporary toast expires.

All owned private QA stages/browser profiles are removed and processes stopped;
no functional work remains. New findings follow the same GitHub-first sequence and
independent plugin ownership.
