# Changelog

## Unreleased

## 1.11.2 — 2026-11-19

- Show confirmed bank payment while the existing subscription purchase waits for
  issuance to start. Keep private status checks running through a deferred recovery.
- Use the bank status in account purchases and show the support route when the
  status is unavailable. WooCommerce supplies the account actions and retry form.

## 1.11.1 — 2026-11-18

- Refresh the private bank-return page as payment and delivery progress. Hold
  retry until the payment bridge confirms that the previous attempt ended.

## 1.11.0 — 2026-11-18

- Present authorized subscription order payments in one responsive card, with
  status, the order's payable amount, plan and reference. Keep the native payment
  form, gateway fields, terms and itemized pricing available.

## 1.10.1 — 2026-11-18

- Show a small mint loading ring and a short Persian status while retrieving
  payment methods in checkout step three. Keep the ring still when reduced
  motion is preferred and remove it when checkout is ready or needs recovery.

## 1.10.0 — 2026-11-18

- Show open purchases first on each native orders page and keep WooCommerce
  pagination, order actions and extension output.
- Present private service orders with payment and delivery status, their next
  action and a compact invoice. Keep native order details and Bridge receipts.
- Preserve notices on the receipt page when a gateway registers WooCommerce's
  notice printer directly on the checkout-completion hook.
- Group account editing into profile, sign-in and password sections. Retain
  WooCommerce saving and validation, and Pinova's mobile field and email policy.

## 1.9.0 — 2026-11-18

- Present My Accounts as a compact responsive list with ready, follow-up and
  ended groups on each page, a single purchase entry, and a compact mobile menu.
- Distinguish a bank transfer without a receipt from one under review. Point
  each state to its own existing payment, delivery or support action while
  keeping connection tools and purchase details separately available.
- Preserve a separate cart selection when a customer opens tracking or submits
  a receipt from their account. Keep Bridge receipt permissions and native
  connection controls authoritative.

## 1.8.1 — 2026-11-18

- Simplify the pending receipt review with one status card, an order reference,
  and one action to check payment. Keep receipt tools and native order details
  available in a closed disclosure. After submission, return to the status card
  on success and to the receipt tools on error.

## 1.8.0 — 2026-11-18

- Present the approved Quick Transfer layout on eligible pending bank-transfer
  orders, with a clear payable amount, adjacent copy controls and receipt upload
  beside the transfer details on desktop and below them on mobile.
- Preview the selected receipt locally and show clear file guidance before the
  existing multipart upload. Retain native bank details, order ownership,
  receipt authorization and the separate payment-review state.

## 1.7.0 — 2026-11-17

- Show the selected plan, quantity and guest sign-in immediately; prepare the
  validated native checkout in the background. Serialize rapid selections, retain
  revision/origin/session locks and recover ambiguous responses through a fresh GET.
- Load native checkout/gateway dependencies only after the real cart is ready;
  preserve WooCommerce checkout/coupon form identities and upstream payment rules.
- Group guest and signed-in headings, present an always-visible three-row summary,
  and align privacy, terms and the primary action across responsive sizes.
- Render human-readable amounts, counts and plan labels with Persian digits while
  preserving native arithmetic, identifiers, credentials, URLs and input values.
- Record the approved BACS description-only configuration operation separately;
  apply it through native WooCommerce settings after official installation.

## 1.6.0 — 2026-11-17

- Implement the approved continuous purchase design on mobile and desktop: a
  shared compact card, stable order summary and one primary action per state.
- Discover native WooCommerce gateways without a fixed Core list; retain native
  fields, availability filters, quantity constraints and original-order payment
  authorization. Bridge's existing service-payment policy remains authoritative.
- Reuse a valid authenticated checkout draft and return directly from compact
  authentication with a fresh purchase GET, preserving nonce/revision/selection
  guards and silent replacement of an unpaid plan. Recover history/BFCache through
  a guarded GET before an old page can replay its prepare POST.
- Allow a guest to sign in before wallet customer eligibility is known when the
  wallet is the only configured payment candidate; authorize payment only after
  authentication through the original strict native/Bridge rules.
- Distinguish partial service delivery and copy only ready private links, with
  secondary per-account controls and a manual selection fallback.
- Align coupon field/action heights and extend transient notices to eight seconds,
  with a contrasting surface, thin stroke, soft shadow and mobile bubble shape.

## 1.5.1 — 2026-11-16

- Keep Pinova field-validation errors beside their input until native validation
  clears them; only transient native Pinova notifications become timed toasts.
- Verify invalid field guidance survives toast expiry and manual dismissal, while
  the native field relationship and other notification adapters remain intact.

## 1.5.0 — 2026-11-16

- Present transient customer notices as safe bottom-left five-second toasts with
  dismiss/countdown and reading pauses; retain native source nodes, field validation
  and permanent states. Cover WooCommerce, Core, native Pinova and Bridge copy output
  without changing another plugin or adding an authentication/notification engine.
- Keep coupon input and secondary action in one row, tighten its label spacing,
  separate signed-in guidance and clarify the payment-method heading.
- Compact post-order completion into a purchase summary, persistent current state,
  real next action and optional invoice details. Preserve receipt owner/key/nonce,
  file policy, bank-transfer processing and private partial/complete delivery.
- Keep manual copy usable when Clipboard fails, with matching visual/keyboard order.
- Include the previously published 1.4.1 page-type routing regression fix.

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
