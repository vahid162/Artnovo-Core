# Current project state

## Current mission and authority

On **2026-11-15**, the owner explicitly requested installing the new Core on
**artnovo.ir**, plus a short handoff for Bridge's optional-wallet behavior.
This follows merged [PR #2](https://github.com/vahid162/Artnovo-Core/pull/2),
commit `f93c10049109e21673b474824f8c3aa290af033b`, whose main validation passed.
The owner previously waived staging as a delivery gate; no staging pass is claimed.

Scope: prepare the merged Core fixes as **1.0.1**, verify the versioned package,
record/review its source through a separate PR, and install it on the existing
active Core path with private recovery. Version metadata and source integration
serve this explicitly requested installation; no new feature behavior is added.
No Bridge change, public GitHub release/tag, plugin deactivation, activation-set
change, migration, real payment, email or provider issuance is requested.
The [governance policy](development-governance.md) owns stable delivery rules.
After installation, automatic authorization review rejected merging PR #3 because
the installation instruction alone did not explicitly authorize changing main.
The owner then directly answered “بله، PR شمارهٔ ۳ را مرج کن”. This separately
authorizes PR #3 squash merge after current-head checks pass; it grants no public
release/tag or unrelated repository authority.

## Plan, risk and recovery

**High risk:** production installation of previously reviewed auth/payment code.
Keep Core active at its existing plugin basename. Preserve the current source and
activation/configuration identity privately outside the web root and Git. Before
any live exchange: verify package/source identity, all repository checks, distinct
independent review and a successful isolated recovery rehearsal.

1. Bump only header/runtime version to 1.0.1; keep modules byte-identical to merged
   PR #2. Preserve all prior release artifacts and fix the package negative fixture
   so a future version cannot turn its mismatch mutation into a no-op.
2. Stage the verified ZIP on the same filesystem; snapshot the existing 1.0.0 and
   rehearse atomic exchange/restore using private copies.
3. Use briefly owned native WordPress maintenance, drain active FPM requests, then
   atomically exchange whole directories. Invalidate Core PHP through FPM, clear
   only this site's FastCGI cache and verify HTTP/UI/version/source identity.
4. On failed acceptance, reacquire maintenance and drain before restoring the old
   source. Never overwrite another operator's maintenance flag or changed source.

Code recovery does not undo orders, payments or provider effects after traffic
resumes. This installation invokes no activation/deactivation or data migration.
No real payment or customer account is used to test it.

## Verification and current status

The 28-entry **1.0.1** package SHA-256 is
`2ecb78de61483b693e5805057a3a09ab59d474ebb9363b7b728cd89e59bd5193`.
Its modules equal merged main; its entrypoint differs only in the two version
lines. A distinct independent reviewer passed archive/source checks, bootstrap
14 scenarios, package 5 cases and release 14 stub cases. Checkout 43, independence
63, migration 40, documentation checks and recovery rehearsal also passed.

Core **1.0.1 is installed and active** on artnovo.ir. All 28 installed files match
the verified package. The briefly owned maintenance flag was removed. Origin and
public homepage/account checks returned 200 with expected Core UI; account
responses retain private/no-store protection and www redirects to the apex.
Activation, wp-config and Bridge hashes are unchanged. A root-private source backup,
reviewed scripts and exact candidate ZIP are retained outside the web root/Git.
The installation outcome is recorded in
[installation verification](evidence/2026-11-15-core-1.0.1-install/verification.md).

The unchanged runtime fixes and their earlier review are documented in
[PR #2 verification](evidence/2026-11-15-bridge-isolation/verification.md).
Published v1.0.0 tag/assets remain unchanged. No public v1.0.1 release is claimed.
The source/version/install record is [PR #3](https://github.com/vahid162/Artnovo-Core/pull/3).
Its live merge outcome and [checks](https://github.com/vahid162/Artnovo-Core/pull/3/checks)
own repository delivery status; the installation artifact is bound by the checksum
above, independently of later evidence-only commits.

## Independent Bridge follow-up

Core owns the site's purchase experience, login and presentation; WooCommerce owns
products/orders, Bridge owns financial authorization and provisioning, TeraWallet
owns its balance/transaction ledger, and PasarGuard owns the actual service.
Bridge's missing-wallet handling is reviewed separately. Fresh BACS purchasing
must remain available when configured, and already-verified subscriptions should
remain accessible; unresolved wallet payments must not be retried or silently
converted into another charge. This installation does not change Bridge.

## Governance v1.2 delivery alignment

The local policy now follows AI Development Governance source 1.2.0 for
informed approvals, versioned delivery order, artifact identity and separate
delivery stages. This is a policy-only update. Core code, its version, existing
WordPress installation, private recovery artifacts and GitHub Releases are not
changed by this mission. The historical Core 1.0.1 installation and its missing
public v1.0.1 Release remain separate facts, not a new publication grant.
Future releases/installations require the specific owner authority described in
the local policy. The policy PR and its CI are the evidence for this edit.
