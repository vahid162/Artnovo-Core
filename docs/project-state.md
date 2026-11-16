# Current project state

## Current mission and authority

The owner requested the 24 browser-annotated UX/UI refinements on artnovo.ir,
then added a minimal standalone Pinova sign-in screen with one mobile/email/
username entry. Use Elementor SentinelX supported native tools for Elementor
content and UI UX Designer guidance while preserving the existing navy/mint
visual language. Core is the only plugin source in scope. Preserve Bridge,
Wallet and Pinova code and their ownership; no mandatory dependency among the
three commerce plugins, second ledger or new issuer. No direct live plugin
source edits: use this repository, review/test, GitHub publication and install
only the exact official release asset before shipping plugin changes.

Implement the unambiguous purchase/home/header/footer fixes. For account-panel
redesign (comments 19, 21, 22, 24), prepare two distinct minimal concepts for the
owner to choose; do not deploy an unchosen account layout. Remove the unnecessary
address navigation within Core's presentation when implementing the agreed menu;
do not erase data or disable native third-party endpoints.

The owner allows required WordPress/Woo settings changes and controlled test
orders/messages to their own contacts. Do not make a real payment or financially
approve issuance merely to test presentation. Preserve native terms consent,
nonces, owner checks, rate limits, stale-selection and duplicate-payment fences.

## Baseline and workspace

Installed/released baseline is Core **1.2.0**, PR #6, main commit
`0cc4ac1792e4c80b489fe08bbcdc5c311519944a`; source tree
`c7e8538447169b87c1393b00b77f919938ea3a5f`. Its official ZIP SHA-256 is
`6f0cf4dc32ac85d7cd55818e59983df28edc7ca43fb0200363080918f1a4020f` and all 30
installed files matched. Bridge/Wallet/Pinova were byte-identical before/after.
The prior deployment backup is `/var/backups/artnovo-core/20261116T123737Z-5h24mu4q`.
Keep published assets immutable. Detailed prior acceptance remains in
[compact checkout evidence](evidence/2026-11-16-compact-checkout.md) and PR #6.

Current branch: `work/purchase-ui-refinements` from origin/main. The initial two
dirty documentation files were this thread's post-deployment notes, not unrelated
owner code; preserve and commit that completed evidence with this mission's
baseline before implementation. Root owns Git, version/docs, release, installation
and native Elementor mutations. Workers must use disjoint files and not mutate
Git or the site.

## Scope and seams

- Native Elementor: home #68, header #75, footer #86, identified by fresh MCP
  structure reads. Restyle explanatory game feature as noninteractive content,
  center/match step headings, align mobile gutters, align price/currency, place
  copyright last, and mobile header menu right / logo center / account left.
  Preserve product query identities, duration navigation, links and content.
- Compact auth: exact requested wording, required label marker, soft accessible
  focus, Iranian-mobile normalization compatible with native numeric usernames,
  safe LTR-isolated phone notices and precise edit labels. Preserve the native
  provider's returned proof method and the existing Core email signup path.
- Checkout: remove mandatory email UI only in the protected service wizard;
  delivery is in the account. Retain existing account email as optional order
  metadata without fake addresses, account merging or automatic email attachment.
  Use native Woo coupon processing with a visible single-column secondary action,
  and improve the unchecked native terms control.
- Standalone sign-in: inspect Core account routes and native Pinova ownership;
  prefer native Pinova form/engine and existing safe return helpers, not another
  authentication backend. Preserve return to selected purchases and owned deep
  account/order/wallet routes.
- Account concepts: throwaway responsive mockups in the thread visualization
  directory, with realistic empty/active/multiple-account states, customer-service
  navigation and no address UI. Await the owner's concept choice for deployment.

## Verification and remaining work

Implementation is complete for the clear home/purchase/sign-in fixes. Native
Elementor changes on #68/#75/#86 are published and read back: 63/5/13 nodes remain,
with no unexpected tree changes. Browser geometry confirms equal 14px mobile
plan/duration gutters, the requested mobile header and final copyright section.
The two account-panel concepts remain outside the plugin, awaiting owner selection.

Core candidate version is 1.3.0 (32 runtime files). Only Core source changes.
Required standalone checks passed; focused auth checks include cold Pinova REST,
ordinary checkout/wallet preservation, numeric usernames and email signup fallback.
Independent review closed email-signup, ordinary-return and policy-link issues,
then confirmed the narrowly preserved canonical resume URL without weakening the
general validator. Real HTTP testing found and verified this REST resume fix.

The loopback real-stack journey passed 17 checks: native Pinova password login of
controlled blank-email customer 15, preserved product 25/quantity 1, native checkout
without contact fields and persisted BACS order 167. It was on-hold, total 100000,
unpaid, email blank, Bridge snapshot matching, issuance operation absent. It is
now cancelled and customer tokens/cart/persistent cart were cleared. No payment or
issuance was performed. Do not send the test password/session data to GitHub.

Details are in [UI refinement evidence](evidence/2026-11-16-ui-refinements.md).
The private candidate server on localhost:8773, its isolated browser/tunnel and
fixture identity still require final cleanup after deployment verification.
GitHub PR/merge, official release and exact-ZIP installation remain pending.
Finish the remaining focused responsive/focus check, then publish through GitHub,
verify artifact identity, rehearse recovery, install and run a scoped live smoke.
Keep actual native zoom and real payment/provider delivery marked untested.
