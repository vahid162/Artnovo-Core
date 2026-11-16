# Current project state

## Current mission and authority

The owner selected the **compact purchase** prototype and explicitly requested
implementation in Core, the guest-session fix, tests, a new GitHub release and
installation on artnovo.ir. Only Core plugin source may change. Ordinary WordPress
or WooCommerce settings may be adjusted when necessary. No Bridge, Wallet or
Pinova source change, second ledger, new issuer or mandatory dependency among
Core/Bridge/Wallet is authorized. The user already authorized controlled test
orders and SMS/email to their own contacts; do not make a real payment or approve
financial delivery merely to test presentation.

Canonical checkout: this repository. Base source is merged PR #5,
`7aad8c503ee4d6d8f9eb9c058fd7d8f0e75fef53`, released and installed as **1.1.0**.
The selected visual is the compact variant in the thread's
`artnovo-checkout-directions.html`; its demo data and simulated payments are not
implementation. Use the existing native authentication, checkout and receipt APIs.
Development, review, authorized merge/release and exact-package installation are
owned by this mission; do not stop after a draft PR.

## Work and ownership

- Session repair: initialize the native WooCommerce guest cookie before output on
  Core purchase pages, account for native empty-session cleanup, and recover stale
  forms with a fresh GET retaining the current allowed selection. Invalid input
  must never update a cart, create an account/order, debit or issue a service.
- Compact authentication: one identifier and one main action; Core's existing
  email-code signup/login stays available, mobile/username use native Pinova APIs.
  Proof fields depend on the provider's response. Refresh authenticated nonces by
  returning through the canonical resume route; do not reuse guest checkout tokens.
- Compact presentation: one summary, quantity and change-plan controls, one active
  login/payment state, native WooCommerce checkout submission, native receipt and
  real delivery status. Bank transfer remains pending financial review. Wallet
  appears only when its existing provider permits it. Never promise bank-transfer
  instant delivery or modify submitted orders when a new plan is selected.
- Root owns version/docs, integration acceptance, the final tree, reviews, commits,
  PR/CI, release and deployment. Shared implementation workers have disjoint files
  and must not stage, commit, build packages or mutate the website.

## Agreed implementation seams

The existing nonce-protected root purchase POST remains the selection boundary.
A guest AJAX request with `artnovo_login_method=pinova` may prepare a validated
selection for native sign-in, returning its current revision and resume destination.
It does not authenticate. Email proof continues to use the existing Core checkout
OTP request. Native Pinova authenticate, login/otp and login/password endpoints
remain their owner. JSON stale-form responses include a safe fresh-GET destination;
normal stale POSTs redirect instead of rendering an unrecoverable 403 page. Nonce
and same-origin checks stay in place.

The compact auth module owns `render_compact_auth(product_id, quantity,
selection_revision, can_checkout)` and its separate script. Home rendering calls
that helper only for guests and keeps native Woo checkout for authenticated users.
No-JavaScript fallback uses the native sign-in route. Existing endpoint, receipt,
account-owner and payment fences remain intact.

## Current progress

Core **1.2.0** is implemented on `work/compact-checkout-session`; runtime changes
and review fixes are committed through `3101fb2`. Required standalone checks,
PHP lint, both Node behavioral tests and real private HTTP/browser acceptance
passed. Dedicated review `20261116-113452-6dff1fc4` completed; its three findings
were fixed and independently closed. The final 320px guest/checkout/receipt views
and auth-busy/Back recovery were rechecked. Detailed observations and the two
pre-existing site-fixture limits are in [compact checkout evidence](evidence/2026-11-16-compact-checkout.md).

The live site remains on 1.1.0 until authorized GitHub publication succeeds.
Next: publish the scoped PR, wait for exact-head CI and available automated review,
merge, run main validation, publish v1.2.0, verify the official asset identity and
install that exact ZIP with the existing atomic recovery procedure. Controlled
order 157 is labelled and cancelled, its temporary coupon 156 was deleted and
customer 13 test sessions were revoked after browser acceptance. No real payment
or financial approval was performed.

## Verification and release boundary

High risk: authentication, purchase state and production deployment. Prove new
behavior with focused failing/passing regressions; run the required standalone
suite, PHP lint, package/release checks and meaningful integration checks. Existing
in-memory tests do not prove browser header/cookie behavior. Exercise a native
first guest GET, first selection, Back, refresh, stale token recovery, real email
login, native username recognition, desktop/mobile layouts, native Wallet/orders,
BACS order and receipt page. Use controlled test records, mark/cancel them and
revoke test sessions. Do not weaken ownership/payment tests for visual convenience.

Require an independent review, then exact-head GitHub validation. Inspect auto
review suggestions posted after a draft becomes ready before merging. Build a
new versioned package, publish from the authorized main commit, verify tag/asset/
checksum/payload, preserve a private previous-source backup and recovery rehearsal,
and install only that published ZIP. Verify all installed bytes and unchanged
Bridge/Wallet/Pinova source manifests. Record actual observations and unavailable
browser/provider checks separately.

## Prior facts and limits

Core 1.1.0's published ZIP SHA-256 is
`8774c6cafee1da33de449263e319961794e218fbd9aa279a2db1fdacb15d5c30`.
All 28 installed payload files matched it. Test order #155 was unpaid, had no
issued service, was cancelled and labelled; its test-customer sessions were
revoked. CMS page #154 registers `/login` for Pinova; no server/plugin source
change was needed for that page. The owner reports SMS working after allowing
the server IP in MeliPayamak. Published policy pages and legal settings stay intact.

The confirmed guest issue is late cookie creation in Elementor output, compounded
by stale-form denial without GET recovery. The original browser nonce's initial
staleness was not directly observed; Core's nonce action is not WooCommerce's
session-specific nonce prefix. A previous diagnostic POST retry was rejected as
outside its read-only scope. This new request explicitly authorizes the fix,
implementation, tests and deployment; the earlier read-only diagnostic scope no
longer governs this mission.
