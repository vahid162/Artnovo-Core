# Changelog

## Unreleased

## 1.4.1 — 2026-11-16

- Restrict the retired purchase-page redirect and current wizard context to actual
  WordPress pages, preserving unrelated term/author/post archives with matching IDs.
- Exercise both page helpers and their render, asset and checkout-context callers
  with type-collision regressions using native in-memory WordPress queries.

## 1.4.0 — 2026-11-16

- Implement the selected My Accounts layout with responsive native subscription
  rows, copy/QR details, product-focused navigation and a shared frame for registered
  WooCommerce and wallet endpoints. Keep their native handlers and absent-Bridge fallback.
- Show usage/renewal only when actual integration data exists. Keep email completion
  optional, preserve its active/error state and link to native account editing.
- Distinguish an empty account from provider errors/unknown output. Redirect the old
  preview purchase page to the current journey and repair scoped skip-link targets.
- Align receipt copy with phone-only purchases and use Persian progress counters.
- Style native catalog and Wallet surfaces to match desktop/mobile account and
  purchase screens, preserving real product images, filters and native handlers.
- Record GitHub-first native header/semantic-page operations separately from runtime
  packaging; no Bridge, Wallet or Pinova source is changed.

## 1.3.1 — 2026-11-16

- Align plan prices to the physical left in dynamic Elementor cards. The saved
  legacy excerpt alignment does not follow the generated widget IDs in loop output;
  a scoped presentation fallback preserves product queries and native card identities.

## 1.3.0 — 2026-11-16

- Reuse Pinova's native sign-in component in a minimal account/login screen, with
  one mobile/email/username field and safe customer return paths. Preserve native
  admin/third-party routes and Core's existing email fallback when the native component is unavailable.
- Let authenticated virtual-service customers finish the protected purchase flow
  without contact fields or an email address. Keep an existing account email only
  as optional order metadata; never invent addresses or update account ownership.
- Show the native coupon form directly with a secondary action, improve consent
  and field focus, clarify authentication text and isolate mobile notices in LTR.
- Hide address navigation in Core's customer panel; retain native endpoint ownership.

## 1.2.0 — 2026-11-16

- Present purchase completion in one responsive compact card, with the selected
  plan, quantity, native WooCommerce total and a single active authentication action.
- Accept email, mobile and username in the same form: retain Core email-code
  signup/login and delegate native mobile/password proof to Pinova without a new
  authentication engine or dependency among Core, Bridge and Wallet.
- Establish guest sessions before page output and recover expired forms with a
  fresh GET, preserving the latest valid selection without replaying a stale cart.
- Preserve native checkout, coupon, payment, receipt-owner and delivery boundaries;
  bank-transfer orders remain awaiting review until their provider confirms them.

## 1.1.0 — 2026-11-16

- Make native Pinova sign-in available from the purchase flow while preserving
  email-code entry and new-email registration.
- Keep third-party account endpoints, ordinary carts and wallet funding with their
  native handlers; confine Core's cart behavior to its own plan purchase flow.
- Preserve silent replacement of the current unpaid plan without exposing a cart.
- Keep receipt-eligibility policy in Bridge instead of duplicating it in Core.

- Upgrade the project contract to AI Development Governance 1.4.0: adaptive work,
  fresh-agent continuity, repeat setup without duplicate changes, and informed
  delivery with immutable source/artifact identity. Plugin runtime is unchanged.

- Adopt repository-specific AI Development Governance instructions, document
  ownership, risk/review gates and a canonical current-state record.
- Add read-only PHP 8.5 pull-request validation and separate explicitly confirmed
  manual publication from development pushes.
- Check documentation links and package exclusion, reproducibility and rejection
  boundaries with isolated fixtures. The governance adoption itself did not change
  plugin runtime or version.

## 1.0.1 — 2026-11-15

- Keep existing-order Store API payment eligibility independent of an unrelated
  current cart, preserving the gateways already authorized by Bridge.
- Load Core's general features without Bridge, preserve native WooCommerce account
  and checkout fallbacks, and reject service payments while Bridge is unavailable.
- Add isolated payment-context and optional-Bridge regression checks, including
  native guest checkout and private order/receipt protections.

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
