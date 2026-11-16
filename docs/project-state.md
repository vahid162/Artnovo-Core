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

Core 1.3.0 was merged in PR #7 as `bc9c9209c4f973965b2be63b3c3ede5443f2c45b`,
source tree `cbcf418d82dfbbc72afe6c7b8213c95ddfbc014a`, formally released and
installed from its exact official ZIP, SHA-256
`dfe4df55d0bbe47f6e0b8eb14fb79956d5ce5a5d0acf270cf0463c22b4863d14`.
All 32 installed files match; Bridge, Wallet and Pinova manifests are unchanged.
Main CI 43891848175 and release workflow 43892107942 succeeded. Backup/recovery
is `/var/backups/artnovo-core/20261116T150623Z-0u3kp0hn`.

The final live visual check found that saved legacy Elementor price alignment does
not apply to generated loop widget IDs. Branch `work/plan-price-alignment` from
that exact main adds one scoped CSS alignment rule and version metadata for 1.3.1.
No module PHP or JavaScript bytes change. Do not overwrite the published 1.3.0
assets. Core remains the only plugin source owner; root owns Git and deployment.

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

Home/header/footer native changes are published; their element identities, queries
and link destinations remain. Clear purchase/sign-in fixes are installed in 1.3.0.
The two account-panel concepts remain outside the plugin, awaiting owner choice.

1.3.0 passed required local/CI checks and independent review, 17 real loopback HTTP
checks including native Pinova password proof and order 167, and 14 live HTTPS
checks through the native checkout without a new order. Order 167 is cancelled,
unpaid and has no issuance operation; owner 15 has no email. Live tests confirmed
that product 26 survives sign-in and checkout has no email field. All 32 installed
payload files and unchanged other-plugin manifests were verified. An initial CI
failure was an obsolete expected asset list; its required assets were retained in
the corrected assertion and subsequent PR/main/release validations passed.

1.3.1 reuses those auth/money checks only because every module PHP/JS file is byte
identical to 1.3.0. Independent review confirmed this mapping. Fresh browser QA passed price alignment and currency gap for 4/6/4 cards at
320/480/1206px with no overflow. All mandatory patch checks passed. A separate
official patch release/install remains next. The legacy-widget
CSS gap is recorded in [UI refinement evidence](evidence/2026-11-16-ui-refinements.md).

Final cleanup remains: revoke/rotate the controlled test identity, clear its carts,
close localhost:8773 preview and the isolated browser/SSH tunnel, preserve only
non-secret aggregate QA results. Do not copy raw private reports or credentials.
Actual 200%/400% browser zoom, real payment and provider issuance remain untested.
