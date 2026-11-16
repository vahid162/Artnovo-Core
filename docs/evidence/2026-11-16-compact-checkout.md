# Compact checkout verification — 2026-11-16

## Scope and source

Owner authorization covers Core-only implementation, tests, GitHub merge/release
and exact published-package installation on artnovo.ir. No Bridge, Wallet or
Pinova source edits are included. Base: `7aad8c503ee4d6d8f9eb9c058fd7d8f0e75fef53`
(installed 1.1.0). Runtime and regression fixes are committed as `3101fb2` on
`work/compact-checkout-session`; the PR supplies the final source identity.

The compact form delegates native mobile/username proof to Pinova and preserves
Core's existing email-code engine. WooCommerce remains the source for checkout,
amounts, discounts, tax and fees. Bank transfer still requires financial review;
this delivery does not add payment authorization, issuance or a wallet ledger.

## Required checks

Passed with PHP 8.5.12 and Python 3.10+:

- `python3 tools/check.py`, `python3 tests/governance.py` (6 document fixtures).
- `php tests/bootstrap.php`, `php tests/checkout-context.php` (33 authentication,
  67 selection and 12 compact form assertions), `php tests/independence.php`
  (80 assertions).
- `php tests/migration.php` in a private `/var/tmp` directory outside Git.
- `python3 tests/release.py`, `python3 tests/package.py` (5 package fixtures),
  `python3 tools/package.py`, PHP lint of entrypoint/modules/tests/tools and
  `git diff --check`.
- Node.js 24.18.1 on the owner's Windows host: `node tests/compact-auth.js`
  (27 assertions), `node tests/compact-checkout.js`, syntax checks for both
  production scripts. SHA-256 of the four executed JavaScript files matched the
  Linux checkout exactly. CI runs the same two behavioral fixtures.

## HTTP and browser observations

A loopback-only PHP preview loads the candidate Core process-locally against the
actual development WordPress stack. It changes no installed plugin file or
activation option. Cron and outbound HTTP are blocked; mail is restricted to the
owner's controlled test address. Edge uses an isolated temporary browser profile
through an SSH loopback tunnel. Test sessions and the controlled order/coupon are
real development data; no real payment or financial approval is performed.

- A fresh guest GET sets the native WooCommerce session cookie before output;
  repeated GETs retain the same selection revision. The installed 1.1.0 baseline
  did not set this cookie in the equivalent HTTP observation.
- Native guest preparation silently replaces product 25 with 26. An expired
  ordinary POST returns 303 to the latest allowed choice; repeated expired AJAX
  requests return 403 with fresh-GET recovery. Product 26 remains current and a
  foreign origin is rejected. No order, message or authentication is created by
  these HTTP session probes.
- Real Core email-code sign-in reaches native checkout for controlled customer
  13 and the same product 26. Expired proof is rejected; resend and fresh proof
  succeed. Native username recognition presents password proof. Native mobile
  authentication accepts SMS delivery and presents its four-digit challenge;
  native SMS/password proof completion is not claimed.
- A temporary coupon restricted to the controlled customer makes native and
  compact totals both 180000 instead of 200000. The visible cloned remove link
  invokes WooCommerce's native handler and restores both totals to 200000.
- The final compact guest and checkout screens at 320px have `innerWidth` and
  document width both 320; the native review table remains in the DOM with
  `display:none` after summary synchronization. A 390px payment/receipt flow and
  a 1365px guest view were also observed.
- A browser contract probe retains real Core preparation but holds only the native
  provider response: quantity, step navigation, change-plan and browser Back stay
  locked while auth is pending; provider failure releases the controls and leaves
  the current choice intact. This probe sends no native authentication message.
- Real native BACS order 157 belongs to customer 13, total 200000, status
  `on-hold`, `is_paid=false`, and its Bridge purchase snapshot matches. The
  received page presents bank information and the native-policy receipt form.
  A generated PNG clearly labelled TEST / NO PAYMENT uploads successfully and
  produces an inline review-pending notice. The 320px receipt page fits the
  viewport. Order 157 was then cancelled, coupon 156 deleted and customer 13
  sessions revoked. This is not evidence of real payment or service issuance.

The preview email used WordPress's loopback sender (`wordpress@127.0.0.1`) and
arrived in Spam; production deliverability cannot be inferred from that preview.
No sender configuration was changed for this UI task.

## Review and known fixture limits

Simplification: code-reuse found no applicable replacement; repeated step-button
availability was consolidated and the resend timer now runs only for an active
OTP countdown. Dedicated `ce-code-review` run `20261116-113452-6dff1fc4` completed
all 10 selected lenses and an independent findings validator, against `bd47412`.
Its three P2 findings (numeric usernames, request deadlines, DOM amount regression)
were fixed in `3101fb2`; the same reviewer independently confirmed their closure
and no new material defect in those fixes. Root-observed raw provider markup and
320px hidden-table overflow were also fixed and rechecked. The complete receipt
is in the host's private temporary review artifact directory; no credentials or
customer message bodies are committed.

Additional site characterization limits are recorded separately from passes:
the account-auth fixture passed with writes/mail/HTTP blocked. The cart fixture
passed all 10 behavioral assertions but its process failed at shutdown when
WooCommerce Blocks attempted a transient write forbidden by the fixture guard.
The receipt fixture failed the same three upload-form assertions on both installed
1.1.0 and candidate: its unpersisted synthetic order does not satisfy Bridge's
fresh persisted-order policy. These are not green runs. Positive receipt coverage
above uses the actual controlled persisted order; owner-denial and invalid-nonce/missing-file
fixture assertions remained active. Provider, real bank settlement, real issuance,
load testing and third-party plugin source changes are outside this verification.

## Delivery boundary

Publication, asset/tag identity, installation, rollback receipt, unchanged third-
party source manifests and post-install smoke are recorded in the resulting PR
and local operational deployment artifacts after those operations occur. A local
package or a passing preview alone is not an installed or released version.
