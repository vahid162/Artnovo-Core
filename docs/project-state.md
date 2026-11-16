# Current project state

## Current mission and authority

The owner explicitly selected **My Accounts** (اکانت‌های من) and requested its
implementation, a site-wide UX/UI audit and fixes, and equivalent desktop/mobile
improvements. The human selection overrides an earlier untrusted prototype-tool
selection of the other concept. Apply AI Development Governance and UI UX Designer
methods, retaining the navy/mint palette, Vazir, native Elementor header/footer and
independent Bridge/Wallet/Pinova code/ownership.

GitHub-first delivery remains mandatory for Core and associated native changes:
review/test the branch, merge and validate main, publish the official runtime ZIP,
install that exact asset, then apply the recorded supported native operations and
verify. Prior scoped release/install authority remains applicable to this ongoing
UX delivery. Do not edit live plugin source, overwrite old assets, change another
plugin's code, create a second ledger/issuer or add a mandatory dependency. Tests
use synthetic records; no real payment or provider issuance is needed.

Candidate **1.4.0**, branch `work/accounts-ui-audit`, is not yet released or installed.
The selected layout uses right-side navigation and flat native subscription rows,
copy/QR and private purchase details, known-empty versus error states, optional
email completion and native endpoint handlers inside the same frame. Native order,
profile and Wallet callbacks retain authorization and processing. Without Bridge,
signed-in native WooCommerce remains the fallback. No guessed usage/renewal data.

Additional proven audit fixes: obsolete page 61 redirects to the current purchase
journey; skip-link targets are scoped to home/legal roots; receipt wording and
summary support phone-only buyers and Persian progress numbers. The recorded
native operation contract in `docs/site-design/artnovo/planned-1.4.0.json` aligns
the header consistently on desktop/mobile, gives legal/product roots main tags
and removes only product-gallery placeholders. Native catalog styling preserves
real images and all catalog processing. Native operations have not been applied. All seven synthetic integration commands
passed229 assertions, including actual native order/edit/Wallet handlers; final
GitHub CI and live artifact verification remain delivery gates. See
[account-design evidence](evidence/2026-11-16-account-design.md).

## Installed baseline and workspace

Core **1.3.1** is published and installed. PR #8 merged as
`18e39aa8a40f7e6d51374f3619502c5c9bce8a46`, full source tree
`ca2f7485a12fe51f23c3d70509e5be3249ddbb0d`; tag v1.3.1 targets that commit.
Official ZIP SHA-256:
`db6307cb13fa35c19141ab810133e687c430d300adad86178bc33a57e9621bcf`.
All 32 installed files match the published payload. Main CI 43897136878, PR CI
43897144330 and publication 43897423853 passed. Recovery was rehearsed; the
preceding 1.3.0 backup is `/var/backups/artnovo-core/20261116T153502Z-ty6h_nie`.
Bridge, Wallet and Pinova source manifests remain unchanged.

The preceding feature release 1.3.0 was PR #7, merge
`bc9c9209c4f973965b2be63b3c3ede5443f2c45b`, tree
`cbcf418d82dfbbc72afe6c7b8213c95ddfbc014a`, ZIP SHA-256
`dfe4df55d0bbe47f6e0b8eb14fb79956d5ce5a5d0acf270cf0463c22b4863d14`.
Its retained backup is `/var/backups/artnovo-core/20261116T150623Z-0u3kp0hn`.
Published assets were not replaced. 1.3.1 adds only the final dynamic-price CSS
fallback plus version/docs metadata; every module PHP/JS byte matches 1.3.0.

The reconciliation branch `work/governance-site-sync` starts at the 1.3.1 merge.
It includes the previous post-deployment notes and a public native design reference
for home 68, header 75 and footer 86. The 1.3.1 tag stays immutable; newer docs/tests
commits need not be its target. Package identity is verified independently of the
full source tree. The reconciliation PR and Actions are the delivery record for
this docs/tests/CI change. There are no plugin-runtime changes.

## Preserved UX scope and seams

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
  navigation and no address UI. The owner selected My Accounts; implement its real native rows and endpoints, without prototype data.

## Verification and remaining work

The delivery-order audit confirmed Core releases preceded installation. The actual
gap was earlier direct native Elementor publication and uncommitted final notes.
The supported public design reads are now in `docs/site-design/artnovo/`; these are
versioned references, not an importable full-site/database backup. Current policy
requires the proposed native operations in GitHub first.

Fresh reconciliation checks: all 12 mandatory commands, 35 PHP lints and two JS
syntax checks passed (49 command exits). All seven read-only site fixtures passed
with 126 PASS assertions and zero process exits, including shutdown and multipart
HTTP parsing. Two legacy harness failures were repaired without weakening guards
or changing Bridge: remove only the cart Blocks cache writer; provide fresh
synthetic order/item CRUD state for receipt checks and add stale-state negatives.
Eighteen release workflow cases passed; existing assets must match candidate bytes
or publishing stops without rewriting tags/assets. Independent cross-reviews of
both areas found no blocker. The rebuilt 32-file ZIP remains identical to 1.3.1.
No customer/order/receipt save, real payment, provider issuance or outbound message
was performed in this reconciliation. GitHub validation is the delivery gate for
this docs/tests/CI change; no runtime release/reinstall is required.


The clear home/purchase/standalone sign-in changes are delivered. Native Elementor
changes on #68/#75/#86 are published with preserved element identities, product
queries and link destinations. The two account-panel concepts remain historical design references; the owner has
now selected My Accounts. The installed baseline still predates that new layout. Address navigation was hidden only
inside Core's presentation.

Required checks and independent reviews passed. 1.3.0 passed 17 loopback HTTP checks
with actual native Pinova password proof and persisted BACS order 167, plus 14 live
HTTPS checks through native checkout for preserved product 26 without a new order.
Customer 15 is a controlled blank-email account; order 167 is cancelled, unpaid and
has no issuance operation. Its tokens/cart/persistent cart were cleared, password
rotated and temporary credential file removed. The audit customer/order remain
explicitly marked as tests. No real payment or provider issuance was performed.

1.3.1 reuses auth/money evidence only through the byte-identical module PHP/JS
mapping. Fresh geometry passed for 4/6/4 cards at 320/480/1206px. On the installed
public site, all four 320px one-month prices align at x30 (card x14 + 16px padding),
with currency on the left and a positive gap. Public 320px native login has the
required LTR field, soft focus and email-signup alternative, without overflow.
All 32 installed bytes and unchanged other-plugin manifests were verified again.

The localhost preview was stopped and removed; its SSH tunnel was closed. The
isolated browser was closed and its temporary profile removed. Only non-secret
aggregate evidence and public screenshots were retained outside Git. Raw private
reports and test credentials were not copied into durable artifacts.

See [UI refinement evidence](evidence/2026-11-16-ui-refinements.md). Actual browser
zoom at 200%/400%, physical devices, real payment and provider delivery remain
untested. The owner has now selected My Accounts; its new candidate and delivery state are recorded above.
