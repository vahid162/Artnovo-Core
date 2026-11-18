# Current project state

## Completed delivery: Quick Transfer / Core 1.8.0 (2026-11-18)

The approved receipt design applies to pending, receipt-eligible bank transfers.
WooCommerce renders the destination and total. The page offers a copy action only
when the displayed figure and currency agree with the stored order amount. Receipt
previews stay local while the existing native form submits to Bridge's protected
receipt flow. If the destination is absent, customers see the support link and can
still send the receipt of a previous transfer.

[PR27](https://github.com/vahid162/Artnovo-Core/pull/27) was reviewed and merged at
`8831c408399b5773be85b46e36dee7a2253923d0`, tree
`c85ea8fc48b84b649a2ecdfd4ae80820ab42d1c3`. Exact-head branch/PR validation
44227399550/44227411690, main validation 44227655913 and publication 44227722790
passed. The automatic review covered the first PR commit and found no issues.
Local review and responsive verification also covered the final centering adjustment.

68 standalone/lint/build checks and 235 native checks passed. The native runs used
private synthetic WordPress orders, IRT and fa_IR. Chromium passed widths 320, 390,
554, 768, 1024, 1206 and 1440, including keyboard copying, manual selection,
receipt preview, invalid files, replacement and the form without JavaScript.
The temporary database, clone and synthetic credentials were removed.

The official [1.8.0 release](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.8.0)
and installed files match all 36 tested runtime files. ZIP SHA256
`8b411fd8334c87bef0445ea578e93e560117bf985d8811c50f4d1e2282c87a96`.
The private backup and tested rollback are at
`/var/backups/artnovo-core/20261118T030500Z-nso9kdah`. Live home/login returned 200,
receipt CSS/JS matched the official artifact and an anonymous order-received request
could not see the bank details or the receipt form. The installed Bridge, Wallet and
Pinova files and plugin activation set were verified against the preinstall inventory.
The authorized private receipt journey was tested in isolation. No actual customer
order, receipt, payment, or service issuance was used for production verification.

## Completed delivery: issues19–25 / Core1.7.0 (2026-11-17)

The owner authorized one GitHub-first bundle, Core code only, independent plugins,
full checks, official release and installation, plus native BACS description-only
configuration for issue24. All seven scoped issues are implemented and delivered.

PR26 merged at `f33304c302bef04f149c5eac7b3c3126b198a281`, tree
`62be522126e24547bf2b85ecf4df1aca48c93058`. Automatic review had no findings;
branch/PR/main/release CI passed. Official tagv1.7.0 and ZIP SHA256
`1fd7eb1817c58cb6960a3017cfc2a384c896f97744a61a15fc24c1f17b5ecd03` match the
frozen tested candidate and every one of36 installed files. Later delivery records
are documentation only; runtime bytes remain the published1.7.0 artifact.

Guest summary/sign-in appears immediately, with native checkout preparation in
the background, serialized selection and nonce/origin/revision/session locks.
Late/ambiguous results cannot enable payment. Native checkout/coupon forms,
gateway availability, financial validation and silent unpaid-plan replacement
remain upstream-owned. Headings, three summary rows, always-visible quantity,
Persian human digits and the compact final consent/action group are delivered.

Final68/68 standalone/lint/build commands and nine isolated native commands
(570 executed PASS assertions, including repeated cart cases) passed. Actual
Chrome passed guest/customer purchase, native gateway/assets/coupon, rapid
selection, cross-tab, explicit persisted-pageshow recovery and14 responsive cases.
Live390/1206 passed with zero new documents on selection, no overflow/errors and
six served Core assets matching the official payload. Real payment/provider/mail
were not invoked; native BFCache eligibility was not certified.

The official package was installed using verified backup, atomic exchange/restore
rehearsal, request drain and Core-only opcode invalidation. Recovery reference:
`/var/backups/artnovo-core/20261117T183152Z-csvk_1ke`. No maintenance remains.
Bridge/Wallet/Pinova code, activation set and wp-config are unchanged. Issue24 was
applied afterward through the native option API with a binary concurrency guard;
fresh readback proves only the approved description changed, with instructions,
all other BACS settings and bank accounts preserved. No Elementor edit was needed.

[Delivery evidence and limits](evidence/2026-11-17-purchase-19-25.md) and
[the native configuration contract](site-design/artnovo/planned-1.7.0.json) retain
nonsecret proof. Private synthetic QA services were stopped; production fixtures
were never run. No required Core work remains in this mission.

## Previous completed delivery: continuous purchase 1.6.0 (2026-11-17)

The owner authorized completion of issues15/16/17, testing, merge, official release
and installation. All scoped work is complete. PR18 merged at
`db5f0ed7294fee8cb5d55ee8a0012530e366ebc1`, tree
`32bf2095434c45c983e559fcd44384097122b9d2`. PR/main/release Actions
44054323132 /44054454699 /44054594824 passed. Published tagv1.6.0 targets that
merge. Official ZIP SHA256
`24318ad77a04d9212cb68f2a29dbbe503c68e5357d815bba98e419108730fc70`
matches the frozen tested candidate, source and every one of35 installed files.

Only Core changed. Bridge/Wallet/Pinova payloads, activation set and wp-config are
unchanged. Independent installer review, atomic exchange/restore rehearsal, request
drain,18 Core PHP opcode invalidations with zero failures, host-scoped cache purge
and home/account HTTP200 health checks passed. Recovery is retained privately at
`/var/backups/artnovo-core/20261117T092223Z-r9gpzrbf`; no maintenance remains.

The approved continuous purchase design uses one compact card/primary per state.
Exact authenticated drafts render directly; a fresh post-auth purchase GET avoids
the old account hop. Chrome Back/BFCache recovery lets the server retain the latest
selection before an old page can issue prepare again. Native gateway discovery,
custom fields, totals and authorization remain upstream-owned. Wallet-only guest
candidates can sign in without bypassing actual customer eligibility. Partial
ready-link copying/manual fallback, native order-pay skin, eight-second contrasting
mobile bubbles and precisely aligned coupon controls are included.

Final62 standalone/lint/build commands,119 payment assertions and independent
runtime review passed. An isolated native WordPress/MariaDB clone passed8 commands
with550 executed assertions;32 runtime files exactly matched final source. Actual
Chrome passed native sign-in/payment rendering, quantity1→2→1, coupons, financial
summary, partial copy/manual fallback/QR, Back recovery, eight-second notices and
mobile/desktop geometry. Five paired valid-draft samples reduced3 document requests
and1 prepare POST to1 GET and0 prepare POSTs. Staging timing is not a production SLA.

Live guest purchase at1206/390/320 and native Pinova login at390 had no horizontal
overflow or browser errors. Six actually served Core assets match official payload
hashes. Live toast timing/progress/contrast/close/gutters passed. No real charge,
message or issuance was used. Tests against the production database were rejected
by automatic review and not executed or bypassed; all synthetic records stayed in
the isolated clone. The clone, private dump, browser profile/tunnel and owned
processes are removed; only nonsecret aggregate evidence and recovery are retained.

Issues15/16/17 are closed with delivery evidence. No functional work remains in this
Core mission. Bridge0.2.8 still restricts service online payments; the owner assigned
that policy change to separate Bridge work. Core is prepared to display newly
eligible native gateways, but this delivery does not certify an actual paid online
transaction or alter Bridge/Wallet/Pinova. Documentation-only delivery records do
not alter tag/assets/runtime bytes. See
[delivery evidence](evidence/2026-11-17-continuous-purchase.md).

## Previously completed outcome and authority

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

## Previous 1.5.1 source and artifact

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

## Previously delivered behavior and checks

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
