# Current project state

## Mission and authority

The owner subsequently explicitly authorized finalizing PR #5, publishing the
formal GitHub release **1.1.0**, installing that exact release on **artnovo.ir**,
and running a short post-install smoke test. This supersedes the earlier hold on
those effects for this Core release. It does not authorize changing Bridge/Wallet
code or making a real customer payment. The delivery preparation below predates
the merge/release/install; their immutable results belong to the delivery record.

On 2026-11-16 the owner requested implementing the purchase-sign-in correction in
`vahid162/Artnovo-Core`, with independent operation and no duplicated responsibilities
between Core, WP VPN Bridge and TeraWallet. Repository work starts from
`be084b8aa2c9ca5953ab39c13d2361bb32363497` on
`work/pinova-auth-independent-accounts`. The earlier Governance PR #4 is merged;
its detailed evidence remains in the dated record.

The owner explicitly prohibits direct changes to custom plugin code on WordPress.
Develop and verify the repository change, prepare its reviewable PR and versioned
delivery. Production must receive an approved published package, never edited
source files or a site snippet. Merge, release and installation remain distinct
effects under the repository's [development governance](development-governance.md).
Full purchase testing on the live site waits for coordinated plugin reports.

Separately, the owner explicitly authorized enabling Bridge's two delivery emails
through WooCommerce settings. That settings-only operation was completed and read
back with `enabled=yes` and valid generation IDs. Hashes of installed PHP, JS and
CSS files in Core, Bridge and Wallet remained unchanged; no test email was sent.
This operation does not authorize replacing their source or changing their ledger.

## Acceptance and boundaries

This is high risk because sign-in, customer identity and purchase context are
affected. Use focused negative/replay/missing-dependency regressions and a distinct
independent technical review; retain nonce, rate-limit, ownership and payment fences.

- Expose native Pinova mobile/email/username sign-in and preserve Core's existing
  new-email registration and usable server-rendered email fallback.
- Preserve plan, quantity and a validated internal return destination. Billing
  email must not select, merge or transfer account/order ownership.
- Keep the three plugins independently installable: no new mandatory dependency
  among them, no second wallet ledger or service issuer in Core. An unavailable
  optional provider disables only the capability that actually requires it.
- Leave third-party account endpoints and non-plan carts/funding to their native
  handlers. Core must not intercept them through its template or redirects.
- Preserve the hidden-cart service journey: selecting plan B replaces unpaid plan
  A silently, with the new quantity, without notices or a visible cart. Browser
  return, sign-in or a stale request must not revive an older selection; existing
  orders, submitted payment attempts and payment evidence are not deleted.
- Keep receipt authorization in Bridge's public API, with safe compatibility and
  missing-provider behavior. Do not open payment/receipt guards for independence.
- Preserve the published Elementor policy pages and their footer links/settings.
  These CMS changes are independent of the plugin release.

## Verification and delivery

The 1.1.0 candidate is implemented locally. Proof-first regressions captured eight
account/login ownership failures, three ordinary-cart failures, and four final
login-session/error-persistence failures before their production fixes. Integrated
standalone checks passed: document/governance, bootstrap, checkout context,
authentication (33 assertions), selection/replay/login (42 assertions), independence
(80 assertions), migration, release policy, packaging and PHP lint (31 files).
Migration used a private temporary directory outside Git; no WordPress fixture ran.
The initial version assertion was advanced with the runtime version; no behavioral
assertion or payment guard was weakened.

Independent review completed eight risk-based lenses and validated five findings.
The fixes protect native review and denied-checkout persistence, redirect stale
checkout URLs, prevent competing login submits, and exercise the actual account
completion callback. A fresh independent resolution review closed all five with
no remaining blockers for a **draft PR**. Actual WordPress/browser acceptance is
still pending. See the [candidate verification](evidence/2026-11-16-pinova-auth-independence/verification.md).

Three behavior-preserving simplification lenses found no worthwhile changes in
the submitted module/test diff. The final login-session edge was then closed with
focused failing/passing regressions and remains included in the technical review.

No new runtime code is installed, no release/tag is published and no real customer
purchase is executed by this mission so far. Browser and live purchase acceptance
must be recorded as unavailable/not run until actually observed. Repository push/PR
automation was inspected: validation is read-only; publication is manual-only on
main and does not install WordPress.

## Prior delivery facts

Installed Core remains 1.0.1; the dated [installation record](evidence/2026-11-15-core-1.0.1-install/verification.md)
identifies its approved package. The [Governance 1.4 evidence](evidence/2026-11-16-governance-1.4/verification.md)
belongs to the earlier policy change and is not new authentication evidence.
Keep installed, developed, published and product-accepted facts separate.
