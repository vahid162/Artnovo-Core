# Continuous purchase 1.6.0 evidence

## Scope and source

The owner approved the continuous mobile/desktop purchase design, then explicitly
authorized completion of issues 15/16/17, testing, merge, GitHub publication and
installation. Reuse PR18 on work/continuous-checkout from main 5b38833. Core is the
only changed plugin. Published 1.5.1 is the installed baseline until the separately
recorded official 1.6.0 installation succeeds; old tags/assets remain immutable.

Core discovers native WooCommerce payment gateways in two phases: registered
enabled candidates can prepare a selection; the resulting cart must pass native
get_available_payment_gateways and all upstream policies. Rejection restores the
old cart/coupons/session pointers. Native titles, descriptions, custom fields,
financial totals/fees, processing and existing-order authorization remain intact.
The installed Bridge 0.2.8 restricts service payments to BACS/its wallet. The owner
assigned that restriction to separate Bridge work; this release does not bypass it
or claim an actual online payment passed. Wallet-only guest discovery permits
sign-in, while actual payment still requires original customer authorization.

Exact valid signed-in drafts render checkout directly without the old query flag.
Compact authentication returns through a fresh purchase GET, retaining the current
hidden product and newer-selection recovery. No cart mutation was moved into GET.
A real Chrome Back replay exposed an old page issuing prepare again and replacing
the newer selection. The final fix detects history/BFCache before auto-prepare and
requests a guarded fresh GET; ordinary explicit navigation/reload retains its
normal behavior. Nonces, selection revisions/locks and payment/private-data fences
remain authoritative.

## Final automated and native checks

- PASS: 62-command final standalone batch: all 15 mandatory source, behavior,
  governance, migration, release, package/build gates; 39 PHP lints; 8 runtime JS
  syntax checks under PHP 8.5 and Node 24. No gate was removed or weakened.
- PASS: 119 payment assertions (60 wallet-present, 59 missing-wallet), including
  future native gateway eligibility/disabled/denied/cart-dependent behavior,
  wallet-only guest sign-in, strict final authorization and denial rollback.
- PASS: compact authentication, financial summary/discount/tax/fee/native fragment,
  history/BFCache recovery and unsafe URL boundary regressions.
- PASS: 35 notice lifecycle/safety checks and 9 received bulk-copy/manual fallback
  checks; field validation and permanent processing/payment/delivery states remain.
- PASS: 8 native integration commands, 550 executed assertions: authentication25,
  cart107, cart UI116, account83, received179, receipt20, template scope18,
  multipart2. Cart UI repeats cart setup assertions; 550 is not a unique-case count.
- PASS: independent read-only review of final runtime changes, including guest
  wallet, history recovery, coupon CSS and received polish; no blocking finding.
  Final modules diff from 5b38833 SHA256:
  3bf4215d5fea2ea68b39f039b6a45ccb8d32dcd3b5824297e9521b2f8384462e.
- PASS: all 32 final runtime files exactly matched the isolated candidate before
  and after native checks. Canonical staged manifest SHA256:
  daa2186c881a08b6d210e98e8e9adc3f0fb9118919f76c6874f45240964eeda3.

Native checks ran on an independent temporary WordPress/MariaDB clone with its own
socket/credentials, no database TCP listener, and empty real customer/order/service
tables. A minimum read-only catalogue/config/schema export populated it. Mail,
outbound HTTP, raw mail/cURL and cron were blocked. Native synthetic fixtures and
one browser-only customer/coupon stayed in that isolated database. No fixture ran
against the live database and no real charge, message or provider issuance occurred.

Initial unprivileged production fixture attempts could not read wp-config.php and
executed zero assertions. Automatic approval review then rejected root live-DB test
commands and required isolation; those commands were not executed or bypassed.
Clone setup warmed its own normal shipping/product/count caches before guarded
fixtures; the SQL/mail guards and acceptance assertions were not relaxed.
The first standalone migration attempt correctly rejected /tmp, which belongs to a
Git tree on this host. The unchanged fixture passed with sys_temp_dir=/var/tmp.
These setup failures are not counted as successful product checks.

## Browser verification and measured path reduction

Actual isolated Chrome used the native cloned WordPress, WooCommerce, Pinova and
Bridge APIs. Only the dedicated QA profile/loopback tunnel was used. No cookies,
nonces, credentials, POST bodies or private service links were recorded in evidence.

- PASS: guest compact form, native username/password challenge and proof, then fresh
  authenticated purchase GET to the native payment form without a my-account hop.
  An initially empty cart still requires its legitimate prepare POST/redirect.
- PASS: repeated valid-draft reuse across plans25/26/27; actual first selection of a
  different plan retains normal guarded preparation and silent unpaid replacement.
- RED then PASS: disable BFCache/cache; prepare25, choose26, choose27, Back to the
  old plain25 URL. Before fix: old prepare POST restored25. Final: no prepare POST,
  guarded GET redirects to current27, native form remains ready. VM checks also
  cover BFCache, normal navigation/reload and invalid origin/path/plan boundaries.
- RED then PASS: native Woo clearfix pseudo-elements made the coupon input14px
  lower despite both controls being50px. A scoped CSS rule removes those extra
  grid rows. At1206/390/320, both controls are50px with identical top/bottom;
  input font is16px and there is no horizontal overflow.
- PASS: native apply/remove COREQA10, invalid coupon notification, financial summary
  matching native totals, optional-email absence and exactly one checkout primary.
- PASS: native quantity1→2→1 with visible controls, duplicate blocking during
  requests and summary/native-total equality; wait for native overlays to clear.
- PASS: ready/partial receipt views at1206/390/320, one primary action, only ready
  links copied, Clipboard rejection/manual selection, unchanged real delivery state,
  technical reference in details, square QR and no overflow; desktop main card592px.
- PASS: eight-second mobile toast remains before expiry and closes after it; native
  source DOM survives. Surface#214354,1px border, shadow, mobile bubble radius,
 14px safe gutters and44px close control. Desktop uses20px left/bottom spacing.

Five paired plan25 valid-draft samples used desktop/mobile and cache-disabled/warm
conditions. Baseline1.5.1: three document requests and one prepare POST in all five.
Candidate: one document GET and zero prepare POSTs in all five. Time from first
request until native updated_checkout: baseline median18.662s/max22.264s; candidate
median10.635s/max19.406s. This deliberately isolated PHP development server is slow
and not production-representative. These measurements demonstrate the removed hop;
they are not an SLA, a claimed production speed or a measured conversion gain.
The final history fix is limited to back_forward/persisted restoration; fresh final
normal-navigation samples still reuse the exact draft with one GET.

Early browser harness failures (same-document Page.navigate, PowerShell quoting,
missing pseudo-element selection and virtual clicks during native blocking) were
corrected at the harness boundary. Only actual observed passing results are claimed.
Native quantity/financial transitions also passed in the visible native browser form.
Physical devices, real OS clipboard, screen-reader speech and real paid online
gateway/provider completion were not tested. Bridge integration remains separate.

## Published, installed and verified

- PR18 merged at`db5f0ed7294fee8cb5d55ee8a0012530e366ebc1`; final PR head
  `d301543afe9f837a8050c51d5f049e3864379db3` and merge share tested tree
  `32bf2095434c45c983e559fcd44384097122b9d2`.
- PASS: [final PR CI](https://github.com/vahid162/Artnovo-Core/actions/runs/44054323132),
  [main CI](https://github.com/vahid162/Artnovo-Core/actions/runs/44054454699) and
  [manual official publication](https://github.com/vahid162/Artnovo-Core/actions/runs/44054594824).
- Published [v1.6.0](https://github.com/vahid162/Artnovo-Core/releases/tag/v1.6.0)
  is non-draft/non-prerelease; tag points to the exact merge. Official ZIP and
  SHA256SUMS were downloaded and matched the deterministic tested candidate byte
  for byte. ZIP SHA256:
  `24318ad77a04d9212cb68f2a29dbbe503c68e5357d815bba98e419108730fc70`.
  Each of35 entries also matches the tagged source; no private/development file.
- PASS: independent read-only installer/FCGI review, private backup and exchange/
  restore rehearsal, drain, Core-only atomic installation and post-readback.
  Backup:`/var/backups/artnovo-core/20261117T092223Z-r9gpzrbf`.
  FPM:18 Core scripts invalidated, zero cached failures. Six host-specific cache
  entries purged; no global service restart or unrelated plugin mutation.
- PASS: installed version1.6.0 and every35 payload files equal the official ZIP.
  Bridge/Wallet/Pinova, active-plugin set and wp-config match pre-install snapshots.
  Home/account HTTP200 with expected UI; no maintenance remains.
- PASS: actual live guest purchase1206/390/320: selected plan/auth visible,
  desktop card592px, optional checkout email absent and no overflow. Native
  Pinova login identifier/required label/script at390 verified. No authentication
  message, order, charge, receipt or issuance was triggered on production.
- PASS: live mobile temporary notice:8s progress and active lifetime, source DOM
  retained, contrasting surface/border/shadow,44px close and14px gutters. All six
  actually served purchase CSS/JS bodies hash-identical to the official payload.
  No browser runtime errors. Assets use native filemtime query versions, not the
  plugin version string; standalone Pinova uses its native username/model node,
  not compact checkout's identifier name. Initial invalid harness assumptions
  about these two selectors/versions were corrected using actual source/DOM and
  byte hashes, without changing product code or weakening a real requirement.

Issues15/16/17 were closed with results and release/PR references. This delivery
introduces no data migration. Source recovery is separate from reversing an actual
order/charge/service; retain the private previous-source backup. Bridge online
payment-policy work remains separately assigned, not silently included or bypassed.

## Cleanup

The independent WordPress/MariaDB clone was shut down and removed, including its
private dump/config/synthetic browser account/coupon/fixtures. Owned Chrome/Node
processes and the SSH forwarding process were stopped; the dedicated QA browser
profile and credential-bearing scripts were deleted. Readback confirms zero owned
Chrome/Node processes, no forwarding process and no profile/clone. Keep only
nonsecret aggregate QA/public UI screenshots outside Git and the private recovery
receipt. No published tag/asset was replaced. Documentation-only final delivery
records do not change the official runtime package. All requested Core work is
complete; real paid online/provider acceptance awaits separately compatible Bridge.
