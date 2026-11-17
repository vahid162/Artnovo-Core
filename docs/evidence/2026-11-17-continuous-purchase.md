# Continuous purchase development evidence

## Scope and source

Owner-approved mobile/desktop preview: a visually continuous experience with one
main card and one primary action per state. Separate native authentication,
gateway/return, receipt and private delivery routes remain valid. Implementation
is Core-only, on work/continuous-checkout from main 5b38833; no release/install is
part of this development handoff. Published 1.5.1 and its assets remain unchanged.

Payment discovery has two phases. Registered enabled candidates permit preparing
a selection even if a gateway requires a populated cart for availability. The
resulting cart must pass native get_available_payment_gateways and every upstream
policy; rejection restores the previous cart/coupons/session pointers. Native
checkout retains gateway titles, descriptions, custom fields, financial totals,
fees, processing and original existing-order payment rules. No Core whitelist is
needed for future eligible gateways. The installed Bridge 0.2.8 still restricts
service payments to BACS/its wallet; the owner will develop that change separately.

The existing selection and session protections remain. Exact valid signed-in
draft/cart state can render checkout without the old checkout query flag. Compact
auth returns through a fresh purchase GET carrying the current hidden product and
the guarded checkout flag, retaining the server's newer-selection recovery. Core
does not move selection mutation into GET. Real elapsed-time/TTFB improvement has
not been measured; no fixed speed or conversion improvement is claimed.

## Executed checks

- PASS: complete 62-command standalone batch under PHP 8.5 and Node 24: 15
  mandatory source/behavior/governance/migration/release/package/build gates,
  39 PHP lints and 8 runtime JavaScript syntax checks.
- PASS: payment/checkout scenarios 54 with wallet plus 53 without; third gateway
  eligible/disabled/unavailable/denied, cart-dependent availability, wallet-only
  empty-cart preparation, final-denial rollback, quantity/stock and absent-Bridge
  checks. These are isolated fixtures, not live gateway activation.
- PASS: compact auth 50 JavaScript and 12 rendered-form assertions; current-product
  fresh GET, stale/replayed/failed request protections remain in existing contracts.
- PASS: 35 notice lifecycle/safety assertions and 9 received bulk-copy/fallback
  assertions; native field errors remain local after eight seconds.
- PASS: financial summary/discount/tax/fee/native-fragment regression.
- PASS: independent read-only runtime review by /root/sync_governance_review;
  reviewed modules diff SHA256
  8e8be2ad88709a73c1b8916433c69c7f0161749f87e3a77019e25fe8baab85a4.
- PASS: installed Core/Bridge/Wallet/Pinova payload readback unchanged during the
  unprivileged fixture attempt. No real order, receipt, mail, payment or issuance.

The first standalone migration run used /tmp, which is itself inside a Git tree
on this host. It correctly rejected that backup location. Repeating the unchanged
fixture with sys_temp_dir=/var/tmp passed all 40 migration assertions.

## Unfinished verification and operational limit

The six native WP-CLI fixture processes failed during bootstrap because the
unprivileged OS account could not read wp-config.php; zero assertions executed.
These are failed environment attempts, not passed product tests. A proposed root
WP-CLI execution against the live WordPress/database was rejected by automatic
approval review for production-effect risk and missing explicit current authority.
The rejected command was not executed, retried or bypassed.

Native WordPress integration, multipart and affected browser checks are NOT RUN.
Their fixture source is present, including native checkout and future-gateway
component rendering, exact-draft reuse, partial delivery and owner/key-scoped native
order-pay field/nonce/hooks checks. CI does not supply that native WordPress stack.
Keep the PR draft/conditional until this check is authorized and completed, or a
separately isolated WordPress/database environment is supplied. A green GitHub
validation job does not resolve this limitation or certify a real online payment.

Source recovery is a scoped branch/PR revert. No data migration is introduced;
source reversal never reverses an actual order, charge or issued service. No tag,
GitHub Release, live installation or native WordPress/Elementor configuration change
has been performed in this mission. Issues 15/16/17 stay open pending verification.
