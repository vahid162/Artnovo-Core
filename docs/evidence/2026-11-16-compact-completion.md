# Compact completion and shared customer notices

## Outcome and boundaries

The owner requested bottom-left customer popups with a five-second countdown,
close control, consistent desktop/mobile design, coupon-row/payment-copy refinements
and a shorter post-order screen matching the compact purchase journey. UI UX Designer
methods guide hierarchy, clear next actions, focus/reading pauses and native form
semantics. Public innet.ir was read only as a three-stage layout reference; no
authentication, OTP, order or payment was attempted on that service.

Only Core changes. There is no second ledger, issuer or authentication engine.
WP admin notices are outside customer-facing scope. Temporary Woo/Core/Pinova/Bridge
copy notices share a presentation adapter; source nodes, native IDs/handlers and
ARIA field relationships remain. Permanent business state, busy state and field
validation retain local context. Safe content strips handlers/classes/unsafe URLs
from visible copies, while native link actions remain delegated. Hover/focus and
a hidden tab pause the five seconds; reduced-motion keeps motion optional.

Received URLs/key/owner/nonces, receipt2MiB/JPG/PNG policy and payment/issuance helpers
remain unchanged. Compact summary/state/action stay visible; invoice/purchase
metadata uses a native disclosure. Pending bank-transfer upload is a real submit,
receipt/issuing uses a real refresh, ready/multiple/partial retain only authorized
links. The same visual journey uses the protected native post-order URL; it does
not create another order or infer paid status in a guest wizard.

## Review and executed checks

Independent read-only reviews found initial server notices silent for screen
readers, a broad account wrapper preventing copy toasts, and manual-copy input
hidden in closed receipt details. All were fixed before delivery. Native source
comparison shows state/owner/receipt helpers are untouched. The merged candidate
retains the published1.4.1 actual-page routing fix and its regression cases.

Thirty isolated notice lifecycle/security assertions and five PHP asset contracts
passed. Received fixture expanded from59 to115 assertions over12 states, including
missing/failed delivery and ready with stored receipt. Final complete gates passed59/59 commands, including all14 mandatory commands,
37 PHP lints and8 JavaScript syntax checks. All7 native WordPress integration
commands passed357 assertions: auth25,cart107,account83,received115,receipt20,
template5,multipart2. SQL/mail/outbound guards remained active, and no exit failed.
Browser toast timing and GitHub delivery remain pending.

Actual Chrome synthetic received manual-copy fault check passed: details open,
input focused/fully selected/visible and native selected status preserved. Tab
copy→QR and Enter QR canvas passed. Coupon geometry at320/1206 has no overflow,
input/button on one row,6px label gap and28px to signed-in guidance. These are
production-rendered coupon functions with synthetic context, not real checkout POST.

The loopback font URL failed due cross-origin restrictions. The same public site's
Vazir file was inlined only into private synthetic HTML and document.fonts.check
then returned true. Final geometry uses that loaded font; initial uncorrected font
observations are not evidence of the actual site typography. Synthetic ready390px
height728 with no overflow. Other layouts and toast lifecycle remain final QA gates.

No real order, customer, receipt, credit, outbound message, payment or provider
issuance is needed/saved. Browser emulation does not prove physical-device, real
OS Clipboard, screen-reader speech or native200/400% zoom behavior.


Actual Chrome popup check confirms14px mobile left/bottom placement, initial
server notice announcement, visible close control, original hidden source, a
shrinking progress transform (~0.40 after3 seconds) and expiry after5 seconds.
The multi-source browser fixture passed Woo synchronous scroll suppression,
block notice, busy/auth transition, account-wrapper copy source/selection, native
Pinova inline/Notyf single-announcement adaptation, coupon ID preservation, typed
quantity busy→error, safe URL filtering, native link-handler delegation and dismiss.
Focus-pause's first headless run did not retain the toast. Bringing the isolated
page to front and enabling CDP focus emulation established real focus events:
focused/hasFocus=true, visible, focusin=1, paused=1; the toast remained after5.5s
and expired after blur/resumption. This is a harness-focus correction, not a
production-code change. Unit hover/focus/hidden-tab pause cases also passed.


## Published and installed1.5.0

PR12 merged as `9cdb73196626cc881fd901dd30d74f1940217e81`, full tree
`77718b7041853355789c6748ac19de32054cf8ff`. PR/main/publication runs
43942004134 /43942132451 /43942307242 all passed. Tag v1.5.0 targets that merge;
official release published2026-11-16T19:43:53Z. ZIP SHA256
`111594a8108a99a3fb902f062a4b5256e0e4a1c60ac177350cbb03eea3104009` matches the
frozen candidate and all35 live files. Independent readback confirmed exact match;
Bridge/Wallet/Pinova source, activation set and wp-config hash were unchanged.

Recovery backup/atomic exchange rehearsal receipt:
`/var/backups/artnovo-core/20261116T194433Z-gm48xqfx`. Installation drained FPM,
invalidated18 PHP scripts with no cached failures, purged only one host cache entry,
removed owned maintenance state and passed home/account HTTP200/UI-marker checks.
No live plugin source was patched. Native design did not change in1.5.0; its current
manifest now maps the observed references to the installed Core artifact.

Popup geometry: desktop1206 left/bottom20px,width360px,close44×44; mobile320
left/bottom14px,width292px; no horizontal overflow. Final live guest purchase at1206/320 and native account login at390 passed: no
overflow, one main, correct wizard/auth markers, published notice CSS/JS loaded
and initialized. Browser-only synthetic notice injection on the actual site passed
source preservation,44px close, desktop20px/mobile14px placement and5-second expiry.
The temporary DOM nodes were removed; no message/order/customer was saved.


## Cleanup and final operational record

Owned loopback preview process stopped, port8774 has no listener, private stages
with synthetic rendered HTML were removed, and Windows confirmed zero owned QA
Chrome/tunnel processes and zero temporary QA profiles. User browsers were not
targeted. Only nonsecret aggregate results/public screenshots and private recovery
backups remain. No credentials were made for this UI mission.

The final delivery-record change is documentation only. Runtime ZIP rebuilt after
these observations remains byte-identical to the formal1.5.0 asset; no retag, asset
overwrite or reinstall follows from operational notes. Local main was fast-forwarded
to the official merge before this notes branch.


## Post-publication Pinova field-validation correction

PR12 review4590621008 (PRRT_kwDOVqm1Ws64Gdel) identified a real classification
error after1.5.0 was published: `[pinova-text$="errorMsg"]` is persistent local
validation, but its native source stayed hidden after toast expiry. The isolated
positive adapter test covered transfer, not the invalid field's continuing need.
Patch1.5.1 removes only that field selector from timed transfer. Native Pinova
Notyf and all other transient adapters remain; the field error stays until native
validation clears it, consistent with README and Woo field validation.

The original1.5.0 tag/assets remain unchanged. PR13 includes this scoped correction
and its regression checks alongside the preceding deployment record. Fresh patch
checks and final1.5.1 delivery will be recorded after execution.


The new regression failed against1.5.0 before the patch and passed afterward:
34 notice assertions now include initial/repeated field error after5001ms, intact
local text/binding/role/ARIA relation/invalid state. Five asset contracts and an
independent scoped review passed. Fresh patch-wide checks again passed59/59 and
all7 native integrations passed357 assertions with the original write/mail/HTTP
guards. No backend/payment/receipt helper or other plugin source changed.
