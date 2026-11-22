# Current project state

## Fresh plan selection after a final bank failure and deferred wallet status

The Core wizard owns the selection session. On an explicit fresh plan choice,
it can replace the prior submitted split-payment row after a final bank failure
when the returned order and cart key match the original purchase. Failed cart
preparation restores the original session. If the session no longer points to
the previous order, Core can use the Bridge's optional owner-scoped cart key
lookup. The previous order, retry eligibility, wallet debit and any refund remain
with their existing payment owners.

Core uses the optional Bridge status to show pending wallet debit or wallet
review for an externally confirmed service order. Older Bridge installations
keep their existing payment presentation. Core creates no new wallet debit,
refund, bank payment or receipt approval in either path.

## Purchase amount confirmation and wallet status / Core 1.12.10 (2026-11-22)

The custom plan checkout confirms the amounts rendered by WooCommerce before
allowing a fresh order. It compares the recalculated total, discount and native
wallet contribution with the review shown to the customer. A mismatch refreshes
the review in the same step and leaves payment to the customer's next explicit
submission. Existing orders continue on their own routes and saved amounts.

The customer surfaces for a direct wallet top-up reuse the provider's available
status and the native wallet ledger. Pending bank verification, bank-approved
payment awaiting credit and recorded credit are shown separately.

## Silent plan selection / Core 1.12.9 (2026-11-22)

The Core wizard replaces the customer's previous cart rows on plan selection, including
legacy TeraWallet recharge rows. WooCommerce still validates the plan, quantity,
coupon and payment methods. A failed preparation restores the old cart. The
submitted-wallet-payment guard remains active.

An explicit new purchase starts with its own checkout intent and releases the
old checkout's session pointers after preparation. Saved orders and payment
attempts remain available through their original payment and tracking routes.

## Wallet contribution in the plan checkout / Core 1.12.4 candidate (2026-11-21)

When the native checkout includes TeraWallet’s partial-payment row, the Core
wizard shows the original WooCommerce review table. This keeps the native
wallet control or automatic deduction notice available with the correct
amounts. The wizard keeps plan and quantity navigation and resumes its compact
summary when WooCommerce refreshes without that row.

Standalone validation, package checks, and the focused checkout regression
passed. An isolated Chromium fixture at 390 and 1280 pixels used TeraWallet
1.7.5’s template, jQuery fragment replacement, and an intercepted wallet
request to verify keyboard access, the automatic disclosure, and the fallback
to the compact summary. Independent source review found no blocker.
[PR 45](https://github.com/vahid162/Artnovo-Core/pull/45) merged the runtime fix
as `6c6a31ec9f34e97238c8e1dff09d0e510db07e57`. This promotion sets both Core
version declarations and records the changelog. The manual release workflow
accepts an optional expected commit and rejects a different revision before
publishing. The installed wizard and an actual payment are outside the
development evidence.

## Native-first ownership rules / documentation candidate (2026-11-20)

The owner approved an explicit native-first capability boundary. Constitution
principle IV and its ownership table are the canonical rule; AGENTS routes agents
to it before executable changes, and development governance requires review of the
selected owner, native limitation and preserved upstream authorization/fallbacks.
Constitution version 1.1.0 is independent of Core 1.12.1. The amendment does not
rewrite existing templates or the email-code flow, alter merge/release authority,
or require a plugin release or site installation.

Validation passed on the candidate based on `036e7cde004fa2874c55a826e3e760ab375bf86e`:
`tools/check.py`, all six `tests/governance.py` checks, and 68 source/syntax/isolated
test commands from the validation workflow under PHP 8.5.12, Python 3.10.12 and
the existing Node 24.11.1 binary. The migration fixture initially refused this
host's Git-contained `/tmp`; `php -d sys_temp_dir=/var/tmp tests/migration.php`
passed using its required private non-Git temporary directory. No check or guard
was weakened. Both [branch validation](https://github.com/vahid162/Artnovo-Core/actions/runs/44748850690)
and [PR validation](https://github.com/vahid162/Artnovo-Core/actions/runs/44748950367)
passed at `eb91196a940c7db48c1878b5dfa1120777a9a1d0`, including the runtime package
build. The existing rule documents are unchanged by this validation record.

Independent reviewer `/root/boundary_policy_review` found no blocking issue.
A fresh Codex desktop agent, `/root/boundary_fresh_agent_eval`, was explicitly
directed to start at AGENTS and returned the expected decisions in seven scenarios:
native banners without tool access; plan preservation; native price/stock/BACS
configuration; external bank verification/retry; Core status presentation; missing
Wallet; and preserving existing email/template behavior without an authorized
migration. Its constitutional source SHA-256 was
`9f94930a8e505484d4a0341512463e24e04a48150e790a9d634e43d7166770ac`;
the configured model identifier was not exposed and no model override was used.
This was a read-only decision exercise, not proof of automatic instruction
discovery, future-agent compliance, WordPress behavior or a real transaction.

This is a documentation development handoff; native site edits, runtime changes,
publication and installation are out of scope. [PR 40](https://github.com/vahid162/Artnovo-Core/pull/40)
awaits a separately authorized merge before adoption on main.

## Completed پانتکس brand delivery / Core 1.12.1 (2026-11-20)

The owner confirmed «پانتکس» as the display name at `artnovo.ir`. The
[Core changes](https://github.com/vahid162/Artnovo-Core/pull/39) and main
validation passed. The [release workflow](https://github.com/vahid162/Artnovo-Core/actions/runs/44733088543)
published [Core 1.12.1](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.12.1)
from `a6cc0bca1fe4bb85f838e16638d935703e2a27bb`. All 51 installed
files matched the official ZIP with SHA-256
`1be93b28e9d3b1264201a0579719f9fe4be7a72734d571768ea3218d6f1162b4`.

The existing [native operation contract](site-design/artnovo/planned-1.12.1.json)
was applied after installation. Independent readback confirmed the WordPress
display name, five post titles, and header menu name. Eight Elementor values in
seven published documents matched the contract across 234 reviewed elements.
The existing domain, historical paths, contact addresses, logo and integration
identities were preserved. The previous plugin and 404 document are backed up.

HTTPS verification through the public domain found the new brand in the title
and text of all 19 routes checked and no old display brand. The error page
returned HTTP 404 and guest requests withheld private order information.

## Completed delivery of remaining customer and public designs / Core 1.12.0 (2026-11-20)

The owner approved the 21 mobile and desktop designs for wallet, account,
recovery and public routes. Core supplies the presentation. WooCommerce,
TeraWallet, Pinova, Bridge and payment gateways own authorization, processing
and persistence. WooCommerce supplies the wallet checkout and result screens.
Gateland was inactive on the production site at verification. In the isolated
test, its optional direct redirect returned through its native WooCommerce
notice, presented by the existing Core toast.

[PR 38](https://github.com/vahid162/Artnovo-Core/pull/38) and the
[main validation](https://github.com/vahid162/Artnovo-Core/actions/runs/44730567384)
passed. The [publication workflow](https://github.com/vahid162/Artnovo-Core/actions/runs/44730682087)
published [Core 1.12.0](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.12.0)
from squash commit `05020793fd8ccc0ad9deac02ce6d4a1ce09b69a5`.
The runtime ZIP SHA-256 is
`08766e314d5bd07a05a2d41945fb2943758e4cbfcacd6c151b75664378fd7987`.

The isolated browser passed 40 public, 20 account and 27 wallet
route-and-width checks. Native integration covered checkout, receipt
handling, account recovery and order and wallet ownership using synthetic
fixtures. No payment, transfer or message was sent to a real provider.

Core 1.12.0 was installed on artnovo.ir. The 51 plugin files matched the
official package. The existing nginx error mapping serves the bundled
`modules/public-pages/edge-404.html` from the existing `/404.html` path.
The origin returned the exact new document and HTTP 404. Eleven public
GET checks passed. The server also withheld private account, order, payment
and receipt data from guests. The activation list, independent plugin files
and WordPress configuration hashes matched the baseline. The prior Core and
error document are retained together in the private deployment backup.

## Delivery candidate: independent purchase from the catalogue / Core 1.11.5 (2026-11-19)

In isolated Firefox and WooCommerce 11.3, Core 1.11.4 reused the previous
same-plan order in bank review when the customer bought from the regular plan
card. WooCommerce replaced a line item of that order even though the plan,
quantity and price were identical. The earlier live independent purchase used
the separate new-purchase form.

The approved change makes the regular plan action the entry point for a separate
online service checkout. Repeated submissions and recovery remain tied to the
same purchase. Pay Bridge owns bank attempt eligibility, and native order-pay
continues the original order when retry is permitted.

Isolated Firefox and WooCommerce validated the ordinary Classic catalogue flow
with a cancelled or open bank attempt, late settlement of the earlier order,
duplicate submission, and the server-rendered flow with JavaScript disabled.
Native WooCommerce order-pay added exactly one bank attempt to the original
cancelled order and preserved its attempt history.
Authenticated Store API requests covered stale tabs, and an unrelated native
BACS checkout exercised the WooCommerce fallback. The site uses Classic for
managed service checkout.

## Delivery candidate: Pinova checkout compatibility / Core 1.11.4 (2026-11-19)

A controlled live Core 1.11.3 checkout stopped at Pinova's phone validation
before an order was created. A read-only check confirmed that the installed
Pinova registers its validator on `woocommerce_checkout_process`. The newer
Pinova fixture registers it on `woocommerce_after_checkout_validation`.
Core 1.11.4 skips that exact validator on either hook for the authenticated
contact-free plan checkout. Ordinary checkout keeps its native validators.

PHP 8.5, JavaScript and package checks passed. Firefox passed native Classic
payment preparation with each Pinova hook in isolated WooCommerce. Pay Bridge
0.1.4 used synthetic bank replies in both browser runs.

## Delivery candidate: Zibal cancellation and independent checkout / Core 1.11.3 (2026-11-19)

Core reads Pay Bridge's verified cancellation reason on the tracking and
purchase pages. WooCommerce's native order-pay route handles eligible retries
of the original order. A separate new-purchase action prepares the same plan
and quantity for an independent order. Classic and Blocks checkout keep repeat
submissions tied to the same selection and protect earlier bank orders.

PHP 8.5, JavaScript, packaging, and 268 isolated received-page assertions passed.
Firefox with WooCommerce and Pay Bridge 0.1.4 passed native same-plan Classic
and Blocks checkout, repeated submissions, old-tab tracking, and read-only
Blocks checkout after the new bank attempt opened. With a signed synthetic
cancellation, the tracking tab and account details showed the cancellation and
the native retry opened one additional attempt for the original order. The
Classic and order-pay forms announced the bank connection before handoff.

Pay Bridge owns the payment journal and WP VPN Bridge owns service issuance.

## Delivery candidate: confirmed payment before issuance / Core 1.11.2 (2026-11-19)

Private tracking, purchases and order details distinguish an open bank attempt
from a payment that Pay Bridge verified while the same WP VPN Bridge operation
still awaits promotion. The private poll keeps checking through a deferred
recovery and leaves a manual refresh available. WooCommerce supplies the native
order actions. The Core view uses the optional Pay Bridge status and preserves
connection links that WP VPN Bridge still authorizes when that status is absent.

PHP 8.5, JavaScript and package validation passed. The isolated WordPress checks
passed 266 assertions and Firefox passed 14 scenarios with Pay Bridge 0.1.0,
including the deferred recovery, authorized partial delivery and private status
access. Both used synthetic orders and bank and issuance responses. The prior
release is [Core 1.11.1](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.11.1).
If the tracking UI needs a rollback after installation, restore that Core
package with the upgraded Pay Bridge active.

## Completed delivery: one-card order payment / Core 1.11.0 (2026-11-18)

The owner selected the one-card order-payment design on mobile and desktop.
The approved layout applies to the existing authorized service-order payment
screen. It places payment status, native total, current plan, order reference
and the WooCommerce payment action together. The native form, itemized amount,
terms, gateway fields and payment permissions retain their existing ownership.
Only Core is authorized for GitHub-first publication and installation.

[PR32](https://github.com/vahid162/Artnovo-Core/pull/32) merged at
`85b5c65e5c953d9c01285611196c690d8e7e5a86` with tested tree
`57910e83f79d0d7df284d17908a523818e08a0cc`. The final candidate, main and release
workflows passed in runs 44384661402, 44384955004 and 44385090890. Local PHP,
JavaScript, migration and packaging passed. Isolated WordPress with WooCommerce,
Bridge and Gateland passed 26 native assertions. Chromium passed 18 native
width/state combinations from 320 to 1280 pixels with order status, gateway
availability, keyboard disclosure, terms and a no-JavaScript fallback.

The official [1.11.0 ZIP](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.11.0)
SHA256 `9ea60425f61d4908fa817bca6f6f0289bdc7259bf450b4530998b9d07a61fe21`
matches the tested bundle and all 43 installed files. Backup and rollback
rehearsal passed. The live home and sign-in returned 200, served payment assets
matched the release, and a guest payment URL redirected to sign-in. Bridge,
Wallet, Pinova and Gateland files, plugin activation and wp-config matched
before and after installation. The payment form was exercised with synthetic
orders in the isolated environment.

## Completed delivery: checkout preparation indicator / Core 1.10.1 (2026-11-18)

The owner selected the small loading ring for payment preparation on mobile and
desktop. Checkout step three shows it only while an authenticated selection is
being prepared. The ring is static with reduced motion. The existing checkout,
sign-in, selection recovery and payment ownership remain in their native paths.
Only the Core release is authorized for GitHub-first publication and installation.

[PR31](https://github.com/vahid162/Artnovo-Core/pull/31) merged at
`8d4c876d5c09d55f0af3ec305c67dd898be390bd` with tested tree
`84812259238fb77fd693b7745b2f132f21b12881`. Branch, PR, main and release
validation passed in runs 44298721936, 44298765727, 44298908093 and 44298997055.
PHP, JavaScript, migration and package checks passed. Chromium used the Core
renderer with synthetic dependencies at 320, 390 and 1024 pixels with normal
and reduced motion, including pending, success, error, retry, guest and
no-JavaScript paths.

The official [1.10.1 ZIP](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.10.1)
SHA256 `efa19a5c127b793c4e96405359e55aafba9e3927342e78226e85689e30094dd6`
matches the tested package and all 43 installed files. A private backup and
rollback rehearsal passed. Live home and sign-in returned 200. The public home
contained the new status and served both wizard assets from the official ZIP.
Bridge, Wallet, Pinova and Gateland files, plugin activation and wp-config matched
before and after installation. Guest routes continued to withhold private data.

## Completed delivery: compact customer pages / Core 1.10.0 (2026-11-18)

The owner selected Quick Follow-up for purchases, Summary and Action for order
details, and Sectioned Editing for account details, on mobile and desktop. The
release is Core-only and is published through GitHub before installation.
WooCommerce still authorizes native orders, pagination and profile saves.
Bridge still authorizes receipt actions and delivery. Pinova still supplies the
mobile field and its validation. Other native and extension routes retain their
existing handlers, and the normal account templates remain available when
Bridge is absent.

[PR30](https://github.com/vahid162/Artnovo-Core/pull/30) merged at
`0ed2b0f1db16664c91e88b9dd1243e156b569013` with the tested tree
`9290a1e14e827958f88c75aeb24a245355b340a1`. Branch, PR, main and release
workflows passed in runs 44291788514, 44291848445, 44292037071 and 44292150105.
All 65 standalone checks and 653 isolated WordPress assertions passed, including
native WooCommerce profile saves and multipart receipt flows with Gateland
active. The receipt renderer preserves the WooCommerce notice that Gateland's
direct callback otherwise consumes. Chromium passed 48 responsive page/state
combinations from 320 to 1280 pixels, including keyboard and no-JavaScript use.

The official [1.10.0 ZIP](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.10.0)
SHA256 `522b0d715c74beeb6d080e442c213d45bf997eb3e01e5e016694ae78376a80fa`
matches the tested package and all 43 installed files. A private backup and
rollback rehearsal passed. Live home and sign-in returned 200, all five served
account assets matched the official ZIP, and the anonymous account and receipt
routes withheld private information. Bridge, Wallet, Pinova and Gateland files,
the activation state and wp-config matched before and after installation. The
authenticated flows were verified with synthetic data in isolation.

## Completed delivery: compact account list / Core 1.9.0 (2026-11-18)

The owner selected the compact list for mobile and desktop and authorized the
Core-only GitHub-first release and installation. On each native subscription
page, ready accounts come first, followed by purchases needing follow-up and
ended accounts. The compact mobile menu and desktop navigation preserve native
account destinations, with one purchase entry and separate connection and
purchase tools. Receipt status and its primary action use the existing private
tracking route. A tracking visit preserves an independent cart selection and
does not replay actual checkout-completion hooks.

Bridge remains authoritative for receipt access, payment and delivery. The new
source change does not install or modify Bridge, Wallet, Pinova or a gateway.

[PR29](https://github.com/vahid162/Artnovo-Core/pull/29) merged at
`73a213c789c6a91f099bfc579099b596a1504836`, with tested tree
`e262430d9162dcac216f241243556647aa0d477b`. Branch, PR, main and release
workflows passed in runs 44271113090, 44271183278, 44271472849 and 44271619239.
All 61 standalone gates passed. The isolated WordPress fixtures passed 633
assertions, including matching-cart preservation and valid/invalid multipart
receipt uploads. Chromium passed 69 checks across nine widths and verified
keyboard focus, native copy and manual copy, QR, and pagination.

The official [1.9.0 ZIP](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.9.0)
SHA256 `9d3757bd22993b319251994ead8179ee868256202c2565e06007b2e2bfb4328e`
matches the tested package and all 36 installed files. A private backup and
rollback rehearsal passed. On artnovo.ir, home and account sign-in returned 200,
both served account assets matched the official ZIP, and anonymous account and
receipt routes withheld private data. Bridge, Wallet, Pinova and gateway files,
plugin activation and wp-config matched before and after. No live order, receipt,
payment or customer session was used for verification.

## Completed delivery: compact receipt status / Core 1.8.1 (2026-11-18)

The owner approved [PR28](https://github.com/vahid162/Artnovo-Core/pull/28) and
Core-only publication and installation. The pending BACS receipt page now shows
payment review, one order reference and one status check. Receipt tools and native
order details remain available in a closed disclosure. A same-order submission
error opens those tools. The native POST returns to the same order and key.

The native suite passed 251 synthetic checks including multipart redirects.
Chromium passed seven widths, keyboard and no-JavaScript use. Only version
metadata, documentation and the version assertion changed after those tests.
The release head `cf06926b00fb413e9a19c60af4fc19aea4572e11` passed branch/PR
validation 44240052729/44240056876. Merge/tag target
`d35c378db4504f4d3d0f2b6ce73d90c7bfd56015`, tree
`0c109ba15360cba6477c4adc04be530429f13da9`, passed main validation 44240139679
and publication 44240196956.

The official [1.8.1 ZIP](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.8.1)
SHA256 `b85a5331579f240f678c6fbecb3a061104cd814fec563d10ce53738d5d9dc46a`
matches all 36 installed files. Recovery rehearsal and private backup:
`/var/backups/artnovo-core/20261118T045229Z-y49ysxg6`. Live home/login returned
HTTP 200, receipt CSS/JS matched the official payload and an unauthenticated
receipt request returned to sign-in without displaying private details.
Bridge, Wallet and Pinova file inventories, activation and wp-config matched
before/after. Authenticated receipt flows were tested with synthetic data in an
isolated environment. No production customer order or payment was used.

## Completed delivery: Quick Transfer / Core 1.8.0 (2026-11-18)

The approved receipt design applies to pending, receipt-eligible bank transfers.
WooCommerce renders the destination and total. The page offers a copy action only
when the displayed figure and currency agree with the stored order amount. Receipt
previews stay local while the existing native form submits to Bridge's protected
receipt flow. If the destination is absent, customers see the support link and can
still send the receipt of a previous transfer.

[PR27](https://github.com/vahid162/Artnovo-Core/pull/27) was reviewed and merged at
`8831c408399b5773be85b46e36dee7a2253923d0`, tree
`c85ea8fc48b84b649a2ecdfd4ae80820ab42d1c3`. Exact-head branch/PR validation
44227399550/44227411690, main validation 44227655913 and publication 44227722790
passed. The automatic review covered the first PR commit and found no issues.
Local review and responsive verification also covered the final centering adjustment.

68 standalone/lint/build checks and 235 native checks passed. The native runs used
private synthetic WordPress orders, IRT and fa_IR. Chromium passed widths 320, 390,
554, 768, 1024, 1206 and 1440, including keyboard copying, manual selection,
receipt preview, invalid files, replacement and the form without JavaScript.
The temporary database, clone and synthetic credentials were removed.

The official [1.8.0 release](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.8.0)
and installed files match all 36 tested runtime files. ZIP SHA256
`8b411fd8334c87bef0445ea578e93e560117bf985d8811c50f4d1e2282c87a96`.
The private backup and tested rollback are at
`/var/backups/artnovo-core/20261118T030500Z-nso9kdah`. Live home/login returned 200,
receipt CSS/JS matched the official artifact and an anonymous order-received request
could not see the bank details or the receipt form. The installed Bridge, Wallet and
Pinova files and plugin activation set were verified against the preinstall inventory.
The authorized private receipt journey was tested in isolation. No actual customer
order, receipt, payment, or service issuance was used for production verification.

## Completed delivery: issues19–25 / Core1.7.0 (2026-11-17)

The owner authorized one GitHub-first bundle, Core code only, independent plugins,
full checks, official release and installation, plus native BACS description-only
configuration for issue24. All seven scoped issues are implemented and delivered.

PR26 merged at `f33304c302bef04f149c5eac7b3c3126b198a281`, tree
`62be522126e24547bf2b85ecf4df1aca48c93058`. Automatic review had no findings;
branch/PR/main/release CI passed. Official tagv1.7.0 and ZIP SHA256
`1fd7eb1817c58cb6960a3017cfc2a384c896f97744a61a15fc24c1f17b5ecd03` match the
frozen tested candidate and every one of36 installed files. Later delivery records
are documentation only; runtime bytes remain the published1.7.0 artifact.

Guest summary/sign-in appears immediately, with native checkout preparation in
the background, serialized selection and nonce/origin/revision/session locks.
Late/ambiguous results cannot enable payment. Native checkout/coupon forms,
gateway availability, financial validation and silent unpaid-plan replacement
remain upstream-owned. Headings, three summary rows, always-visible quantity,
Persian human digits and the compact final consent/action group are delivered.

Final68/68 standalone/lint/build commands and nine isolated native commands
(570 executed PASS assertions, including repeated cart cases) passed. Actual
Chrome passed guest/customer purchase, native gateway/assets/coupon, rapid
selection, cross-tab, explicit persisted-pageshow recovery and14 responsive cases.
Live390/1206 passed with zero new documents on selection, no overflow/errors and
six served Core assets matching the official payload. Real payment/provider/mail
were not invoked; native BFCache eligibility was not certified.

The official package was installed using verified backup, atomic exchange/restore
rehearsal, request drain and Core-only opcode invalidation. Recovery reference:
`/var/backups/artnovo-core/20261117T183152Z-csvk_1ke`. No maintenance remains.
Bridge/Wallet/Pinova code, activation set and wp-config are unchanged. Issue24 was
applied afterward through the native option API with a binary concurrency guard;
fresh readback proves only the approved description changed, with instructions,
all other BACS settings and bank accounts preserved. No Elementor edit was needed.

[Delivery evidence and limits](evidence/2026-11-17-purchase-19-25.md) and
[the native configuration contract](site-design/artnovo/planned-1.7.0.json) retain
nonsecret proof. Private synthetic QA services were stopped; production fixtures
were never run. No required Core work remains in this mission.

## Previous completed delivery: continuous purchase 1.6.0 (2026-11-17)

The owner authorized completion of issues15/16/17, testing, merge, official release
and installation. All scoped work is complete. PR18 merged at
`db5f0ed7294fee8cb5d55ee8a0012530e366ebc1`, tree
`32bf2095434c45c983e559fcd44384097122b9d2`. PR/main/release Actions
44054323132 /44054454699 /44054594824 passed. Published tagv1.6.0 targets that
merge. Official ZIP SHA256
`24318ad77a04d9212cb68f2a29dbbe503c68e5357d815bba98e419108730fc70`
matches the frozen tested candidate, source and every one of35 installed files.

Only Core changed. Bridge/Wallet/Pinova payloads, activation set and wp-config are
unchanged. Independent installer review, atomic exchange/restore rehearsal, request
drain,18 Core PHP opcode invalidations with zero failures, host-scoped cache purge
and home/account HTTP200 health checks passed. Recovery is retained privately at
`/var/backups/artnovo-core/20261117T092223Z-r9gpzrbf`; no maintenance remains.

The approved continuous purchase design uses one compact card/primary per state.
Exact authenticated drafts render directly; a fresh post-auth purchase GET avoids
the old account hop. Chrome Back/BFCache recovery lets the server retain the latest
selection before an old page can issue prepare again. Native gateway discovery,
custom fields, totals and authorization remain upstream-owned. Wallet-only guest
candidates can sign in without bypassing actual customer eligibility. Partial
ready-link copying/manual fallback, native order-pay skin, eight-second contrasting
mobile bubbles and precisely aligned coupon controls are included.

Final62 standalone/lint/build commands,119 payment assertions and independent
runtime review passed. An isolated native WordPress/MariaDB clone passed8 commands
with550 executed assertions;32 runtime files exactly matched final source. Actual
Chrome passed native sign-in/payment rendering, quantity1→2→1, coupons, financial
summary, partial copy/manual fallback/QR, Back recovery, eight-second notices and
mobile/desktop geometry. Five paired valid-draft samples reduced3 document requests
and1 prepare POST to1 GET and0 prepare POSTs. Staging timing is not a production SLA.

Live guest purchase at1206/390/320 and native Pinova login at390 had no horizontal
overflow or browser errors. Six actually served Core assets match official payload
hashes. Live toast timing/progress/contrast/close/gutters passed. No real charge,
message or issuance was used. Tests against the production database were rejected
by automatic review and not executed or bypassed; all synthetic records stayed in
the isolated clone. The clone, private dump, browser profile/tunnel and owned
processes are removed; only nonsecret aggregate evidence and recovery are retained.

Issues15/16/17 are closed with delivery evidence. No functional work remains in this
Core mission. Bridge0.2.8 still restricts service online payments; the owner assigned
that policy change to separate Bridge work. Core is prepared to display newly
eligible native gateways, but this delivery does not certify an actual paid online
transaction or alter Bridge/Wallet/Pinova. Documentation-only delivery records do
not alter tag/assets/runtime bytes. See
[delivery evidence](evidence/2026-11-17-continuous-purchase.md).

## Previously completed outcome and authority

The owner selected **My Accounts** (اکانت‌های من), requested global UX/UI fixes,
matching mobile/desktop presentation, minimal native Pinova login, compact checkout
and post-order completion, and shared bottom-left customer notices. The ongoing
scoped authority covered Core GitHub development, merge, release and installation.
All scoped implementation, corrections and delivery are complete through
Core **1.5.1**, published and installed through the GitHub-first sequence.

Only Core runtime source changed. Bridge, Wallet and Pinova remain independent
owners of service issuance/payment policy, balances and identity. No copied ledger,
issuer or authentication engine was added. Native Elementor/config proposals were
recorded in GitHub before supported live application after the corresponding Core
release/install; current references/manifest match observed source. No raw Elementor
storage or live plugin source was patched. Published tags/assets are immutable.

## Previous 1.5.1 source and artifact

PR13 merge/tag target `496583cc1fa6c62304d43a51ed51fe33b13a2dcc`, source tree
`f4cc27e1651cb8df1c33e105c1bf30bc8f022368`. PR/main/publication Actions
43944577362 /43944697736 /43944826163 passed. Official1.5.1 ZIP SHA256
`ef8ede79b1421ff4c76718c78e98f24c9ba24f6506dc5da4d098d2871639bc9b` equals the
frozen candidate and every one of35 installed files. Bridge/Wallet/Pinova source,
activation set and wp-config hash remain unchanged. Recovery backup/rehearsal:
`/var/backups/artnovo-core/20261116T195916Z-lnu19tps`. Atomic exchange/drain,18 PHP
opcode invalidations, zero cached failures and health checks passed; no maintenance
remains. Final delivery notes are documentation only; runtime bytes match the
official ZIP. Existing1.5.0 and older tags/assets remain unchanged.

Previously PR11/1.4.1 fixed actual-page routing for61/68; PR10 review4589577964 is
resolved. Those fixes and regression checks are included in1.5.0. The native price
alignment was applied only after official1.4.1 installation and readback verified
only its intended card style changed. Native designs did not change in1.5.0.

## Previously delivered behavior and checks

My Accounts keeps real native subscription rows, copy/local QR, optional email,
proper navigation and native order/profile/Wallet handlers. Missing-Bridge fallback
remains native Woo. Header/footer/home/catalog/legal and minimal Pinova login align
with navy/mint/Vazir at mobile and desktop sizes. Protected service checkout needs
no email, keeps silent unpaid plan replacement and native discounts/financial totals.
Coupon input/action share a row, label gap6px, login-info separation28px; payment
heading is clear. Post-order view gives summary, permanent real state, actual next
action and optional invoice details, with owner/key/receipt/issuance fences intact.

Temporary customer notices use five-second bottom-left toasts with close/progress.
Focus/hover/hidden tab pause reading time; permanent state, busy progress and field
validation keep their context. Original native sources/IDs/handlers remain. Safe
visible content, initial announcements and native copy/manual-selection are tested.

Final59/59 independent/lint commands include all14 mandatory gates,37 PHP lints and
8 JS syntax checks. All7 synthetic native WordPress commands passed357 assertions
with SQL/mail/outbound guards. Independent reviews found and rechecked three UI
issues before release; no blocking finding remains in the reviewed scope. Actual
Chrome passed popup lifetime/progress/placement, focused pause, native message
sources, safe link actions/dismiss, receipt Clipboard fault/manual selection,
Tab/Enter/QR and representative desktop/mobile geometry. First font/focus fixture
failures were diagnosed and rerun correctly; they were not counted as successes.
Live guest purchase1206/320 and native login390 passed; actual published assets
showed/closed synthetic browser-only notices at the expected position/time.

[Compact completion delivery evidence](evidence/2026-11-16-compact-completion.md)
and [account/native evidence](evidence/2026-11-16-account-design.md) record details.
Cleanup of this task's private preview/staging/browser/tunnel is verified and
recorded at the end of delivery evidence: no owned process/profile or port listener
remains. Preserve private recovery receipts; retain only nonsecret aggregate QA
results/public screenshots outside Git.

## Practical limits and history

No real order/customer/receipt/credit/message/payment/provider issuance was created
in this UI work. Physical devices, actual OS clipboard, screen-reader speech and
native browser zoom200/400% were not tested; emulation is not certification.
Earlier controlled customer15/order167 history and cleanup are preserved in
[UI evidence](evidence/2026-11-16-ui-refinements.md); that order is cancelled/unpaid
without issuance. No old test identity/token is reused or inferred to be current.

PR12 review4590621008 identified a real post-publication Pinova field-error
classification problem.1.5.1 removes only that field selector from temporary
transfer, preserving its local error until native validation clears it. Notyf and
other transient adapters remain unchanged. A red regression preceded34 passing
notice assertions and an independent scoped review; fresh59/59 standalone/lint
and7/7 native integrations (357 assertions) passed. The exact review thread is
resolved after the merged fix. Live browser red/green confirms the invalid field
remains visible/bound after5.1s while a simultaneous temporary toast expires.

All owned private QA stages/browser profiles are removed and processes stopped;
no functional work remains. New findings follow the same GitHub-first sequence and
independent plugin ownership.
