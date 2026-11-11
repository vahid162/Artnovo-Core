# Tasks: Unified Artnovo Core Release

**Input**: Design documents from `/specs/001-unify-artnovo-core/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/compatibility.md](contracts/compatibility.md).

**Tests**: FR-012 requires preservation verification. Reuse characterization checks
for existing behavior; add focused checks for new bootstrap, migration and packaging logic.

**Organization**: Tasks are grouped by user story and mapped to plan units U1–U4.
Unchecked tasks are implementation work; this file does not claim their execution.

## Format: `[ID] [P?] [Story] Description`

- **[P]** identifies independent files with no incomplete prerequisite.
- **[US1]**, **[US2]**, and **[US3]** refer to the specification's user stories.
- Preserve the paths and contracts in the plan; record verification evidence separately.

## Path Conventions

Runtime files live in the plugin root and `modules/`. Development-only checks and
operations live in `tests/` and `tools/`, excluded from the installable package.
Private snapshots and raw operational logs remain outside the repository/web root.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish the specification and recoverable implementation baseline.

- [X] T001 Establish the project principles in `.specify/memory/constitution.md` and feature selection in `.specify/feature.json`.
- [X] T002 Finalize this feature's specification, plan and compatibility contracts in `specs/001-unify-artnovo-core/`.

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish preservation evidence and package boundaries before replacement.

- [X] T003 Capture the current source, activation order, setting fingerprints and bridge checksums in a private external snapshot for `tools/migrate.php`; do not commit snapshot contents.
- [X] T004 Characterize the existing isolated journeys before relocation and define candidate/installed module paths for `tests/integration/`.
- [X] T005 Define runtime-only package inclusion and development exclusions for `tools/package.py` and `.gitignore`.

**Checkpoint**: The baseline and contracts are available; source movement can begin.

## Phase 3: User Story 1 - Manage One Site Plugin (Priority: P1)

**Goal**: Supply the current custom features from one Core activation (U1 and U3).

**Independent Test**: Bootstrap checks and an isolated activation replacement/rollback
preserve third-party entries, settings, and independent bridge checksums.

### Tests for User Story 1

- [X] T006 [US1] Add focused normal-load, missing-dependency, legacy-collision and single-registration assertions in `tests/bootstrap.php`; observe failures for new logic before implementing it.
- [X] T007 [US1] Add an isolated activation replacement/rollback check alongside `tests/bootstrap.php` or the documented test seam in `tools/migrate.php`.

### Implementation for User Story 1

- [X] T008 [US1] Relocate the existing feature sources into `modules/auth/`, `modules/checkout/`, `modules/home/`, `modules/account/`, `modules/order-received/`, `modules/header/`, and `modules/mail/`; preserve namespaces, existing option/session/nonce values, templates, assets and QR notice.
- [X] T009 [US1] Implement the dependency-aware and collision-safe `artnovo-core.php` entrypoint, preserving the captured callback registration order and early sender predicate.
- [X] T010 [US1] Correct moved include, template and asset paths under `modules/`; retain the external bridge asset reference and existing script handles/dependencies.
- [X] T011 [US1] Implement the documented external snapshot, coordinated activation replacement and rollback interface in `tools/migrate.php`, using WordPress APIs and leaving unrelated entries unchanged.
- [X] T012 [US1] Apply the verified replacement through `tools/migrate.php`, retire the old sender MU file to the private snapshot, and compare source/settings/bridge preservation evidence.

**Checkpoint**: Core can supply the existing site features without duplicate legacy loading.

## Phase 4: User Story 2 - Continue Existing Customer Journeys (Priority: P1)

**Goal**: Prove the package relocation preserves the existing user experience (U2).

**Independent Test**: The unchanged expected auth, purchase, panel, private-order and
receipt assertions pass with Core; controlled desktop/mobile and route checks agree.

### Tests for User Story 2

- [X] T013 [P] [US2] Retarget the existing email-code/cart characterization checks in `tests/integration/`, preserving synthetic state and mail/write blocking.
- [X] T014 [P] [US2] Retarget the existing account/received-state/private-link checks in `tests/integration/`, correcting candidate paths, reflection matches and asset roots without weakening assertions.
- [X] T015 [P] [US2] Retarget and run existing valid/invalid multipart receipt parsing checks in `tests/integration/`, retaining real upload parsing and synthetic guarded storage.

### Implementation for User Story 2

- [X] T016 [US2] Resolve any relocation-only regressions under `modules/` and run the focused PHP syntax, bootstrap and integration checks.
- [X] T017 [US2] Verify installed assets and representative guest/account/financial return/cart routes plus desktop/mobile presentation; record controlled sender/OTP evidence in the release verification notes referenced from `CHANGELOG.md`.

**Checkpoint**: Existing customer journeys pass without a new behavior or customer-data migration.

## Phase 5: User Story 3 - Install a Traceable Release (Priority: P2)

**Goal**: Publish a verified, runtime-only version 1.0.0 distribution (U4).

**Independent Test**: The downloadable ZIP, runtime metadata and tagged source agree,
and the package contains no development or private artifacts.

### Tests for User Story 3

- [X] T018 [US3] Inspect the archive produced by `tools/package.py` for one `artnovo-core/` root, version consistency, required QR notice and absence of forbidden files.

### Implementation for User Story 3

- [X] T019 [US3] Implement deterministic versioned ZIP packaging in `tools/package.py`.
- [X] T020 [P] [US3] Document requirements, installation, ZIP-based updates, migration and rollback in `README.md`, with first-release notes in `CHANGELOG.md`.
- [X] T021 [US3] Add `.github/workflows/release.yml` to validate/package source and publish `v1.0.0` plus the installable artifact using the scoped runner token; handle existing release conflicts explicitly.
- [ ] T022 [US3] Publish the verified source and observe the tagged release/artifact produced by `.github/workflows/release.yml`.

**Checkpoint**: The supplied GitHub repository contains the traceable first release.

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Complete review and evidence without expanding release scope.

- [X] T023 Review the runtime diff, public-file contents and final version metadata in `artnovo-core.php`; resolve in-scope findings before publication.
- [ ] T024 Run `specs/001-unify-artnovo-core/quickstart.md` verification and record convergence against FR-001 through FR-012 without inventing unobserved results.

## Dependencies & Execution Order

### Phase Dependencies

Setup precedes foundation. U1 runtime implementation precedes integrated U2 checks
and the U3 live replacement. U4 tooling/documentation may be prepared independently,
but publication waits for verified runtime, package and preservation evidence.

### User Story Dependencies

- US1 has no other story dependency; its isolated bootstrap/migration checks are standalone.
- US2 characterization fixtures may be retargeted independently after path contracts are
  agreed; integrated and live checks depend on the US1 runtime package.
- US3 package/tooling work may proceed against the agreed layout; final publication
  depends on successful US1/US2 verification.

### Within Each User Story

New bootstrap/migration branch logic gets a focused failing proof before implementation.
Existing feature relocation uses before/after characterization rather than duplicate
implementation-mirroring tests. Verify source first, replace live activation second,
and publish only the reviewed passing source/artifact.

### Parallel Opportunities

- T013, T014 and T015 can use disjoint harness files after the module paths are fixed.
- T020 documentation can run separately from T019 package tooling.
- Shared `artnovo-core.php`, module integration, activation state and release publication
  have one owner; do not run competing Git operations or live mutations.

## Parallel Example: User Story 2

```text
Worker A: retarget auth/cart fixtures in tests/integration/.
Worker B: retarget account/received fixtures in tests/integration/.
Worker C: retarget multipart receipt fixtures in tests/integration/.
```

Each worker owns disjoint files; the integrator runs the combined authoritative checks.

## Implementation Strategy

### MVP First (User Story 1 Only)

Prove the single-plugin runtime and preservation boundary with isolated fixtures.
This is an intermediate checkpoint, not the full requested deliverable.

### Incremental Delivery

Integrate runtime, confirm all existing journeys, apply the coordinated replacement,
and publish the matching release. The user's request includes all three stories.

### Parallel Team Strategy

Keep one owner for bootstrap, activation and release state. Delegate bounded module,
fixture or documentation work only after ownership and path contracts are clear.

## Notes

The plan is stable scope; task completion and verification receipts record progress.
No task authorizes a real customer payment, bridge change, server configuration rewrite,
new settings screen, module-toggle system, or private updater service in version 1.
