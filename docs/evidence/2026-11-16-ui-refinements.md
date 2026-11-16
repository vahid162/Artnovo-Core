# Purchase and sign-in UI refinement evidence

## Scope and recovery boundary

Base is published Core 1.2.0, main `0cc4ac1792e4c80b489fe08bbcdc5c311519944a`.
The owner authorized the browser-commented home/purchase/header/footer fixes and
a minimal standalone Pinova sign-in screen, preserving independent Core, Bridge
and Wallet ownership. Only Core plugin source changes. Elementor documents 68,
75 and 86 use supported native MCP edits, separately from the plugin release.
No direct live plugin source edits are permitted. Two account-panel concepts are
prototypes awaiting the owner's choice; the account panel is not replaced.

Native before/after trees, allowed-change contracts, comparisons and browser
captures are outside Git in the task's `governance-artifacts/ui-refinements-20261116`.
The comparison covers 63 home, 5 header and 13 footer nodes: no unexpected tree
change, product query change, link destination change or lost element identity.
Intentional additions are native collection style classes; the refund label is
shortened and copyright is last. Document settings are outside this tree comparison.

The initial native style replacement retained a mobile footer override; explicit
mobile centering was subsequently saved, published and read back. Native collection
defaults added three 10px inset layers. Explicit mobile padding removal on all nine
collection wrappers fixes the measured 30px extra inset on each side.

## Implementation boundaries

- Reuse the installed Pinova partial and proof engine, not copied authentication
  logic. Core changes the wrapper, label, required semantics, styling and official
  policy links. The existing Core email-code registration remains an explicit
  secondary route, including non-JavaScript submission and retry.
- Ordinary checkout and third-party wallet return URLs stay native. Core's
  financial return validation, owner checks, nonces and payment fences remain.
- Real HTTP testing found that Pinova REST proof has neither a Woo session nor a
  cart. The former return filter therefore discarded the purchase-resume marker.
  A single filter now retains only the exact site-generated account resume URL.
  It carries no product, quantity or payment identity. The next normal account GET
  loads Woo's current session, reads the current draft and uses existing guards.
  The general destination validator is unchanged; no Woo cart is booted in REST.
- Remove contact fields only for the authenticated, nonce/draft-matched virtual
  service wizard with tax disabled. An existing valid owner email is optional order
  metadata. Blank-email accounts remain blank; forged/stale posted email is ignored
  and checkout does not update the customer. Other checkout contexts retain fields.
- Keep native coupon processing and unchecked, required terms consent. Presentation
  uses the existing Core stylesheet. A scoped currency gap/LTR notice repair covers
  markup that the inspected Elementor legacy excerpt style target cannot express.
- Iranian formats remain owned by Pinova's native classifier. Do not force numeric
  usernames into phone numbers; friendly format guidance follows native rejection.

## Executed checks

PHP 8.5.12; Python 3.10; temporary standalone Node 24.11.1 from the official archive
with its SHA-256 verified. No system runtime installation or project-source transfer
to another workstation is needed for Node tests.

Required standalone bootstrap, checkout context, independence, migration, compact
authentication/checkout JavaScript, governance, release and package checks passed.
The new account-login fixture is part of validation CI. Packaging contains 32 runtime
files; the two new runtime files are the standalone native wrapper and stylesheet.
PHP lint and JavaScript syntax checks passed.

Focused checks: 49 compact-auth JavaScript assertions; 63 native-route assertions
plus 3 missing-plugin-module and 3 missing-partial assertions; 70 assertions with
the installed WordPress parser and Pinova partial without booting their database;
33 existing auth assertions; 89 selection assertions. The isolated missing-Pinova
module test does not imply that Core's existing entrypoint dependency is removed.

A read-only real-stack fixture passed 11 contact-policy checks for controlled
customer 15 with no account email: native posted-data/field validation, rejected
forged email, invalid nonce denial, unchanged owner and unsaved Bridge snapshot.
Its SQL-write, mail and outbound-HTTP guards remained active. It is not an HTTP
login or persisted payment test.

The separate loopback HTTP journey used actual Pinova password authentication for
that controlled blank-email customer, resumed selected product 25, performed the
same normal form preparation as the browser, and submitted native Woo BACS checkout
without contact fields. All 17 journey checks passed. Persisted order 167 was
on-hold, total 100000, unpaid, with blank order/account email, a matching Bridge
snapshot and no issuance operation. Its receipt page rendered. The order was then
marked as a controlled test and cancelled; issuance remained absent and that
customer's tokens/cart/persistent cart were cleared. No real payment or issuance
was performed. This preview uses the actual WordPress database but does not replace
the installed Core plugin.

Independent reviewer `/root/ui_phone_checkout` found and rechecked email-signup,
ordinary-checkout return and policy-link issues. All three were fixed. The reviewer
also closed the cold-REST resume fix and tested five extra foreign/malformed return
cases. No unresolved auth/payment/independence blocker was found.

## Browser scope and limits

Published native layout: Edge desktop 1206px and mobile 480/320px. Seven home/plan
states had no horizontal overflow. Plan gutters measured x=14, width=452 at 480px,
and x=14, width=292 at 320px, matching duration selection. One-month and two-month
groups have 4 and 6 products; the three-month group is unavailable, so its product
rendering was not tested. Its source wrappers were checked. Mobile menu is right,
logo centered and login left; desktop header arrangement is preserved. Footer
legal links center and copyright is last. The game feature is informational,
icon-right/text-left on desktop and icon-above-text on mobile. Actual font is Vazir.

Two account concepts cover empty/active/multiple accounts at desktop and 390/320px;
local controls and sample-copy interactions passed. Their Vazir font is a static
version of the same family, not the site's exact variable-font file.

Actual browser zoom 200%/400%, physical devices and other browser engines are not
tested. Viewport emulation is not native zoom. BACS UI and an unpaid order do not
prove a completed real payment or successful provider delivery.

## Delivery state

At source review, GitHub publication and installation of 1.3.0 remain pending.
Before deployment, verify CI, the exact merged tree, tag, official ZIP/checksum,
installed payload and unchanged Bridge/Wallet/Pinova manifests. Preserve the prior
1.2.0 artifact and rehearse recovery before exchanging live plugin directories.

## Published 1.3.0 and price-alignment follow-up

PR #7 merged as `bc9c9209c4f973965b2be63b3c3ede5443f2c45b`; full tree
`cbcf418d82dfbbc72afe6c7b8213c95ddfbc014a`. PR CI 43891442876, main CI
43891848175 and publication 43892107942 passed. The official 1.3.0 ZIP SHA-256 is
`dfe4df55d0bbe47f6e0b8eb14fb79956d5ce5a5d0acf270cf0463c22b4863d14`; all 32
installed payload files match. Other-plugin manifests are unchanged. Recovery was
rehearsed and backup is `/var/backups/artnovo-core/20261116T150623Z-0u3kp0hn`.
The initial CI asset-list mismatch was corrected without dropping required assets.

Post-install HTTPS verification passed 14 checks with real native sign-in and
product 26 preserved through native checkout, without creating another order.
Published login at 320px has no overflow and a diffuse focus indicator; the plan
anchor has 124px scroll margin and its heading remains below the sticky progress.
Private checkout at 320px confirmed zero email fields, a one-column coupon form,
distinct secondary/main buttons and an unchecked 22px circular native checkbox.

The final live price measurement exposed a native styling gap: a 292px card starts
at x14 with 16px padding, but its price begins at x192. The native excerpt saved
`text-align:end`, yet generated loop widget IDs differ from source IDs and the
rendered price wrapper still computes `text-align:start`. Currency order/gap is
correct. The inspected legacy wrapper schema cannot express an alternative layout
control. A single Core CSS rule targets only direct legacy price widgets inside
wizard product cards, using their price-content class rather than generated IDs.

This requires patch 1.3.1 because 1.3.0 is already published; no existing tag/asset
is replaced. Independent review confirmed all module PHP/JS bytes match 1.3.0, so
its auth/payment evidence is reused with that exact mapping. New QA is price
geometry, currency spacing and overflow on mobile/desktop plus mandatory gates.

Fresh price QA passed: four one-month cards at 320px, six two-month cards at
480px and four one-month cards at 1206px. Every measured price aligns with its
card's left content edge and currency remains to the left with a positive gap.
At 320/480px the measured price left is x30 (card x14 + 16px padding). No horizontal
overflow occurred. The first 320px observation ran before cards rendered and is
not counted; the settled four-card observation replaces it. All mandatory patch
checks passed. No new auth/payment implementation or provider test is introduced.

## Installed 1.3.1 and completed cleanup

PR #8 merged as `18e39aa8a40f7e6d51374f3619502c5c9bce8a46`, tree
`ca2f7485a12fe51f23c3d70509e5be3249ddbb0d`. Tag v1.3.1 targets that commit.
PR/main/publication runs 43897144330 / 43897136878 / 43897423853 passed. Official
ZIP SHA-256 `db6307cb13fa35c19141ab810133e687c430d300adad86178bc33a57e9621bcf`
and all 32 installed files match the tested package. Bridge/Wallet/Pinova full
manifests are unchanged. Backup is `/var/backups/artnovo-core/20261116T153502Z-ty6h_nie`;
recovery exchange was rehearsed. FPM drained with queue zero, 17 PHP scripts were
invalidated without cached failures, and origin home/account health passed.

The final public browser check at 320px confirmed four prices at x30, with each
currency preceding the amount visually and a positive gap; document width stayed
320px. Public native account sign-in also fit 320px, retained its required LTR
identifier, had no hard focus outline and kept its Core email-signup alternative.
Public screenshots and only aggregate, non-secret verification fields were saved
outside Git. The attempted raw private-report copy was rejected; it was replaced
with fixed validated aggregates, and raw private files were removed with the preview.

Controlled customer 15 tokens/cart/persistent cart were cleared, the password was
rotated and temporary credentials removed. Unpaid cancelled order 167 remains as
marked audit evidence, with no issuance. The localhost preview and its private
source/data directory were stopped/removed, the owned SSH tunnel was closed and
the isolated Edge browser/profile were removed. Real payment, provider delivery,
physical devices and native 200%/400% zoom are still not tested. No unchosen account
panel was deployed; its two visual concepts remain available for owner selection.


## GitHub/site reconciliation and complete fixture rerun

The owner requested a GitHub-first delivery audit and full applicable checks under
AI Development Governance 1.4.0. Independent inspection found no reverse Core
code deployment. GitHub metadata and retained local installation receipts show
this sequence on 2026-11-16 (UTC):

| Version | Merge into main | Main CI completed | Formal release published | Installation receipt |
| --- | --- | --- | --- | --- |
| 1.3.0 | 15:00:49 | 15:01:06 | 15:03:00 | 15:08:31 |
| 1.3.1 | 15:30:44 | 15:31:00 | 15:32:52 | 15:37:04 |

The last column is the retained receipt file's write time, after installation;
it is not a claim of the exact directory-exchange transaction time.

Freshly rebuilt 1.3.1 remains the same 32-file ZIP with SHA-256
`db6307cb13fa35c19141ab810133e687c430d300adad86178bc33a57e9621bcf`.
Source, existing tag, official release asset and installed files match. Native
Elementor home/header/footer changes did precede the Core release, and final
post-install notes were uncommitted. This is the actual provenance gap. The
public [native design references](../site-design/artnovo/README.md) now capture
those three published documents through the supported Elementor read API,
including stable identities, styles, bindings and public page/Theme Builder
assignments. They are neither import files nor a complete site/database backup.
No customer records, private options or credentials are included. Capturing them
now does not change the historical order.

Two older site fixtures had failed for harness reasons and are not retroactively
reported as passing. The cart's SQL guard caught the unrelated Woo Blocks cache
write at shutdown, after its cart assertions passed. It now removes that specific
cache writer and retains the guard. The receipt fixture formerly constructed only
an in-memory caller order; Bridge correctly rejected it when its fresh order read
found no stored order. A read-only synthetic Woo CRUD store, like the existing
multipart fixture, now supplies fresh order/item state without a DB save. Original
positive checks remain, with six additional stale-owner/amount/status/existence
and private-receipt checks. Native Bridge source and authorization remain intact.

The release workflow also had a genuine assurance gap: an existing published
release with non-empty correctly named assets could pass without comparing bytes.
It now downloads both assets privately and requires exact candidate equality;
wrong ZIP/checksum, partial or failed downloads fail closed. Existing tags/assets
are never overwritten. Eighteen local cases execute the actual workflow script,
including six unauthorized publishing contexts. These automation changes do not
publish anything.

Fresh local validation passed all 12 mandatory commands plus 35 PHP lints and two
JavaScript syntax checks (49 commands, PHP 8.5.12 / Node 24.11.1). All seven
site-dependent fixtures passed with process exit zero, including shutdown: auth
25 assertions, cart 10, account render 8, received states 56, receipt 20, template
scope 5 and actual multipart HTTP parsing 2 (126 PASS assertions). Fixtures ran
in separate CLI processes or a loopback-only ephemeral PHP server using a private
copy of candidate runtime/tests, guarded DB writes, intercepted/blocked mail and
blocked external HTTP. No customer, order, subscription or receipt was saved.
Independent cross-review found no blocker in the two repaired fixtures or the
release workflow/negative cases. Raw synthetic HTML/logs stay outside Git.

This reconciliation changes documentation, test harness and release assurance
only. It changes no packaged runtime byte, so it neither needs a new plugin
version nor replacement of the existing release/install. The owner's GitHub-first
native-design sequence is now explicit in the local policy. New changes must be
proposed in GitHub before live application. Existing real-payment/provider,
physical-device and native-zoom limits remain as stated above; the full-site
backup and an unchosen account-panel redesign are outside this reconciliation.
