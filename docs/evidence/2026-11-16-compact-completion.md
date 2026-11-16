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
