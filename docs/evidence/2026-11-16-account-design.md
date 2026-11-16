# My Accounts and site UI reconciliation

## Outcome and boundaries

The owner chose My Accounts and asked for a site-wide UI/UX review with equivalent
mobile/desktop improvements, retaining the existing site identity. Core 1.4.0 is
prepared in GitHub first. No Bridge/Wallet/Pinova source, credential, payment
approval, issuance engine or ledger is changed. Native callbacks, account owner
checks, nonces, private cache headers and return paths stay authoritative.

The account layout uses real Bridge subscription output and progressive DOM
presentation, not a copied backend. It adds responsive navigation and native
endpoint framing, removes unavailable usage/renewal placeholders, keeps copy owned
by Bridge and QR generated locally, distinguishes verified empty output from errors,
and preserves phone-only accounts and optional email completion. Native profile
editing remains reachable. Active/error email states reopen their disclosure.

## Evidence-based audit scope

Reviewed current home/duration/plans, compact purchase, standalone/email login,
account rows/help/support/profile, native orders/order detail/Wallet, payment and
received states, legal documents and shared header/footer. Public GET/source audit
found obsolete page 61 with disabled preview checkout, broken skip targets on
home/legal pages, receipt email assumptions and Latin progress counters. These are
fixed through scoped presentation/GET changes; ordinary checkout and payment
handlers keep their behavior. Shared header desktop layout is brought into line
with the already approved mobile menu-right/logo-center/account-left arrangement.

The [native operation contract](../site-design/artnovo/planned-1.4.0.json) is
recorded before live edits. It preserves content, links, IDs, queries, template
assignments and non-target styles. Legal documents and the active product template receive native main tags;
Elementor's exposed schema has no verified HTML-ID setting, so Core adds content
IDs only to the known rendered roots. These references are not import/DB backups.

## Verification and delivery

Development and integration checks, independent review, browser geometry and
interactions are recorded here as completed. Candidate is not a release or live
installation until the corresponding GitHub and installed-payload evidence exists.
Real payments/provider issuance, physical devices and native browser zoom require
separate executed evidence; viewport emulation alone does not establish them.


## Candidate checks

All 12 mandatory standalone commands passed, with PHP 8.5.12 and Node 24.11.1;
35 PHP lints and two mandatory JavaScript syntax checks also passed. A later
report-file ownership error was repaired outside Git and the runner completed
49/49 command exits. Final focused PHP/JS/bootstrap/package checks follow scoped
presentation changes; GitHub CI must run again on the committed final tree.

All seven real-stack synthetic fixtures passed, with 229 assertions: auth25,
cart/root/selection35, account83, received59, receipt20, template scope5 and actual
multipart2. Native order, edit-account and Wallet callbacks were exercised directly
in addition to delegation sentinels. A fresh process-local WordPress cache supplies
only the reserved fake user's record for Woo's fresh identity read. Wallet's exact
connection-tuning SET is replaced with SELECT1 only for that fake user/route and
its transient cache stays in the temporary memory cache; SQL write guards remain.
The original failed fixture output was diagnosed, not ignored or relabelled.
No real order, customer, receipt, balance credit, message or issuance was saved.

Independent cross-reviews found and rechecked optional-email disclosure state and
native account-edit access regressions; both are fixed. No auth/payment/independence
blocker remained in the reviewed scope. Additional catalog audit found oversized
placeholder images, raw button/colors and attached currency. Core styles only the
native catalog while preserving real images, sorting, queries, pagination and
purchase links. Its placeholder-only product gallery collapse is recorded as a
native operation; actual galleries remain available. Skip links use Hello's native
filter for Woo main and Core's scoped IDs for native Elementor roots.

Actual Chrome checks used synthetic HTML at loopback through a private SSH tunnel,
with the real Vazir font and public native Woo/Wallet styles. Account rows passed
320/390/768/1206px geometry; copy success/failure was exercised through the unchanged
native handler using an in-memory Clipboard fault fixture, and local QR canvas and
manual selection passed. Controls measured at least44px. Native orders/edit/Wallet
passed320/390/1206px without horizontal overflow; edit fields measured48px. Public
home/plans/purchase/login/legal/shop were inspected before deployment on desktop
and mobile. A failed timing observation in a multi-navigation run was discarded;
settled per-route measurements replaced it. These are not real payment, real OS
clipboard, physical-device, native zoom or accessibility-certification claims.


The final standalone rerun completed49/49 commands on the frozen candidate. Final
cart/root fixture replay passed35 cases and the complete fixture aggregate remains
7/7 with229 assertions. Catalog prototype checks preserved10 products at1206/390/
320px, rendered4/1/1 columns with no horizontal overflow, hid only placeholders,
and measured46px actions. Header prototype centering matched half the actual client
width (including desktop/mobile scrollbar differences). The exact installed/native
readback is a later delivery fact, not inferred from these prototypes.


## Published and installed 1.4.0

PR10 merged as `c2988d59b7085c544dbfef8e4d104a507a8de085`, tree
`f6047eb9c3b2efc62c8123aa984e345c2c3304cf`. PR/main/publication runs
43925235125 /43925745160 /43925900948 passed. Tag v1.4.0 targets that commit;
formal release published2026-11-16T18:10:06Z. Official ZIP SHA-256
`5c99394c330ac2ffdc4428df290ead1d0143d30a8d1d530b95cf5753fc761064` equals
the candidate and installed32-file payload. Private backup/rehearsal receipt:
`/var/backups/artnovo-core/20261116T181215Z-tjuu2j0o`. Atomic exchange/drain passed,
17 FPM scripts invalidated with no cached failures, no maintenance left, and origin
home/account health passed. Bridge/Wallet/Pinova manifests and activation/config
checks passed unchanged. No live plugin source was edited.

Only after release/install were the recorded native operations applied through
supported Elementor tools and published. All non-target IDs/content/links/styles
were preserved; the three legal documents changed only root tag to main. Native
undo revision IDs: header75=168, terms132=147, privacy134=148, refunds136=149,
product35=108. Twenty fresh public Chrome cases on home/category1/category2/guest
purchase/login/shop/product/three legal documents at1206/320px passed no overflow,
exactly one main and valid skip target. Logo centers match actual client width,
with menu right/account left, and native login remains present.

The live product read showed a selector mismatch in the first placeholder-only
collapse: Woo's gallery uses `woocommerce-product-gallery__image--placeholder` on
a DIV; only the catalog uses the IMG placeholder class. The follow-up native
selector correction is recorded in GitHub before application. The display-only
Woo option `woocommerce_currency_pos` is also proposed from right to right_space;
this supplies real separator whitespace to plain price strings as well as HTML.
Neither proposal changes plugin code, currency or monetary values. Current
observed source/settings and proposed operations remain explicitly distinct.


## Post-publication display refinements and page-type regression

PR11 commit `66f1251415411f485e5e9bc78dfe16cb9f723c1e` recorded the corrected
placeholder wrapper and right_space proposal before application. Supported native
update/publish succeeded (undo174), WordPress option changed with expected-old
guard, and fresh readback matched. Product placeholder height is0, grid width
1112/292 at1206/320px without overflow; currency and product25 price unchanged.
The observed product reference and display-setting manifest now match these facts.

PR10 review4589577964 correctly identified ID-only routing: a category/author with
ID61 could enter a retired page redirect. Both page61 and wizard68 helpers now use
qualified native `\is_page(PAGE_ID)`. The regression was reproduced before the fix
(bootstrap ready failed on category61) and passed afterward. Fourteen bootstrap
scenarios and72 new real WP_Query type-collision checks cover page/home, category,
tag, product taxonomy, author and post/CPT contexts and render/asset/checkout callers.
Native WordPress source confirms static front-page queries keep is_page=true and
send_headers runs after query setup. No payment/auth/cart guard was weakened.

Patch1.4.1 passes49/49 standalone/lint commands and7/7 synthetic integration commands
with301 assertions (auth25,cart107,account83,received59,receipt20,template5,multipart2).
These use the existing unchanged SQL/mail/outbound guards. No real records were
saved. GitHub CI, formal1.4.1 publication and exact installed asset are pending.
The separate1.4.1 native contract proposes the final product-detail price alignment
after release/install; it has not yet been applied.
