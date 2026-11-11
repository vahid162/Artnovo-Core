# Artnovo Core 1.0.0 verification

Verified on 2026-11-11 against the existing ArtNovo installation with PHP 8.5.
The first release consolidates existing features; it does not migrate business data.

## Source and focused checks

| Check | Observed result |
| --- | --- |
| Runtime relocation | All 24 original files mapped; only five entrypoint/header/include/asset-path corrections. JavaScript, CSS, QR renderer and vendor license retained byte-for-byte. |
| Bootstrap | 14 isolated scenarios passed, including missing dependencies, legacy conflicts and sender restoration races. |
| Activation and recovery | 40 assertions passed, including stale-cache races, binary expected-value comparison, retry and rollback after sender-retirement failure. |
| Native concurrency boundary | The installed WordPress option API emitted the guarded comparison; an impossible expected value changed zero rows and preserved activation/MU state. |
| Native missing-bridge behavior | The real configured WooCommerce service product remained unavailable for purchase with the bridge skipped; ordinary product decisions were retained. |
| Existing journeys | 118 assertions passed: auth 25, cart 10, account 8, received states 56, receipt 14 and template scope 5. |
| Multipart parsing | Valid PNG and invalid-image uploads passed real PHP multipart parsing/native validation with synthetic storage and same-order HTTP 303 returns. |
| Release states | Eight functional Bash/CLI-stub cases passed, including incomplete drafts, missing/empty assets and existing tag conflicts. |
| Syntax and package | PHP syntax checks passed. The reproducible ZIP has one `artnovo-core/` root and 28 exact runtime/documentation files; development and private files are excluded. |

The structured Compound Engineering review completed seven lenses. Its five
validated findings were addressed before activation, with failing-before/passing-after
checks for the new failure branches. No external peer received the code; the local
adversarial reviewer covered that lens. The simplification pass found no worthwhile
behavior-preserving changes.

## Observed live replacement

- Core 1.0.0 is active. The four legacy PHP plugins and the sender MU file were
  archived outside the web root. WordPress lists one ArtNovo Core entry.
- The seven feature/payment/endpoint/schema setting fingerprints match the private
  snapshot. Every third-party activation is preserved.
- WP VPN Bridge remains independently active at 0.2.5; all 32 bridge source files
  match the pre-migration checksums.
- The installed Core matches every file in the installable ZIP. Its nine JavaScript/
  CSS URLs return the exact source bytes.
- Nine old JavaScript/CSS URLs also retain their original bytes for cached pages;
  the old directories contain only ten static assets/license files, with no PHP.
- Email-code readiness is enabled, the effective default From is `orders@artnovo.ir`,
  and exactly one Core sender filter is registered. All three existing Elementor
  dynamic tags are available.
- Home and guest account return HTTP 200. The cart/review URL redirects to plan
  selection; a synthetic private order URL redirects to email login with its return
  destination preserved. Existing private cache protections remain in place.

## Live browser checks

An isolated guest Chrome session checked 12 cases at widths 1280, 390 and 320:
duration selection, plan selection, the third-step email form and account email login.
There was no horizontal overflow, visible cart interface, JavaScript exception or
HTTP error. Loaded custom assets used Core paths. No non-GET request was made.
The browser and its temporary profile were cleaned up afterward.

## Scope limits

No real payment, customer login, email submission or production receipt upload was
performed during this migration. Authenticated account/order states were checked
with synthetic records. Mail transport, server cache configuration, third-party
source and existing customer/order/receipt stores were retained.

## Operational validation and rollback

The deployment operator checked availability, private redirects, assets, sender,
configuration fingerprints and bridge independence immediately after activation.
Unexpected route failures, missing assets, changed sender/configuration or an enabled
service purchase without its bridge are rollback triggers. Use the documented guarded
rollback with the same private snapshot; it refuses later activation/source changes
instead of discarding them. There is no scheduled background monitoring task.

## Publication

The repository's `Validate and release` workflow publishes the tagged runtime ZIP
and `SHA256SUMS`. Its release-state checks reject drafts or incomplete existing
releases. Final publication is verified against the downloadable release assets.
