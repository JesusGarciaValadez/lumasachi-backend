# PHPUnit-to-Pest Migration and TIA Adoption Plan

## Purpose

Port every PHP unit and feature test in Lumasachi from PHPUnit class syntax to Pest functional syntax without changing
the application behavior or weakening the business-rule coverage. After the port:

- Every PHP test under `tests/Unit` and `tests/Feature` is written in Pest syntax.
- Pest is the project's direct test-runner dependency. PHPUnit may remain only as Pest's transitive engine.
- The existing `phpunit.xml` suites and testing environment remain usable by Pest.
- The full suite passes serially and in parallel.
- Code coverage remains at or above the existing 90% threshold.
- Pest's Test Impact Analysis (TIA) engine has a verified local baseline and an intentional CI strategy.
- Existing TypeScript/Vue tests under `tests/Frontend` continue to run with Vitest; they are not part of this PHP test
  migration.

This document is an implementation plan. Creating this document does not authorize an agent to combine multiple steps,
change production behavior, or mark unverified work complete.

## Sources and known uncertainty

Use current primary documentation again when beginning Step 1 because Pest versions and TIA behavior can change:

- [Pest installation](https://pestphp.com/docs/installation)
- [Pest PHPUnit migration guide](https://pestphp.com/docs/migrating-from-phpunit-guide)
- [Pest TIA documentation](https://pestphp.com/docs/tia)
- [Pest optimization and parallel testing](https://pestphp.com/docs/optimizing-tests)
- [Pest support policy](https://pestphp.com/docs/support-policy)
- [Laravel 12 testing documentation](https://laravel.com/docs/12.x/testing)
- [Stable Pest package metadata](https://packagist.org/packages/pestphp/pest)
- [Stable Pest Laravel-plugin metadata](https://packagist.org/packages/pestphp/pest-plugin-laravel)

Version uncertainty recorded on 2026-07-28:

- Pest's official support policy lists Pest 5 as released on 2026-07-28 and requiring PHP 8.4 or newer.
- Pest's `5.x` source branch requires PHPUnit 13.
- At the time of this research, stable Composer metadata still listed `pestphp/pest` 4.7.5 and
  `pestphp/pest-plugin-laravel` 4.1.0, while the 5.x packages were development branches.
- The current Pest installation and upgrade pages were still showing Pest 4 guidance.

Therefore, "latest" in this plan means the latest **stable, mutually compatible** Pest and Laravel-plugin releases that
Composer can resolve when Step 1 is executed. Do not lower `minimum-stability`, use a `dev-*` branch, or force
`5.x-dev`. If Pest 5 is required but stable Pest 5 packages are not yet resolvable, record that as a dependency blocker
and wait rather than inventing a compatible set.

## Current repository snapshot

This static inventory was collected on 2026-07-28. Step 1 must refresh it before any package changes.

| Suite or area                                                                           |  Files | Statically discovered test cases |
|-----------------------------------------------------------------------------------------|-------:|---------------------------------:|
| Unit — enums                                                                            |      5 |                               42 |
| Unit — models                                                                           |     10 |                               89 |
| Unit — services and traits                                                              |      2 |                               27 |
| Unit — factories                                                                        |      3 |                               42 |
| Unit — migrations                                                                       |      5 |                               47 |
| Unit — seeders                                                                          |      2 |                               10 |
| **Unit total**                                                                          | **27** |                          **257** |
| Feature — root, auth, settings, localization                                            |     12 |                               42 |
| Feature — requests, mail, models, notifications, observers, policies, traits, factories |     18 |                              127 |
| Feature — controllers and routes                                                        |     18 |                              186 |
| **Feature total**                                                                       | **48** |                          **355** |
| **PHP suite total**                                                                     | **75** |                          **612** |

Risk characteristics in the current suite:

- 73 of 75 files use `RefreshDatabase`.
- 20 files define a custom `setUp()` method.
- 2 files define `tearDown()` cleanup.
- Several files define private setup/assertion helpers.
- One unit test file manages Mockery explicitly.
- Two files use `WithFaker`.
- Tests use both `#[Test]` methods and `test_*` methods.
- The order lifecycle, controllers, observers, policies, attachments, localization, factories, migrations, and seeders
  contain database-dependent tests.

The runtime baseline from Step 1 is authoritative. The static count of 612 is a regression alarm, not a substitute for
executing the suite.

## Business-rule invariants

Read `docs/Business_Rules.md` before migrating order-related files. A syntax port must preserve the existing assertions
for at least these behaviors:

- Order intake records motor information, received items, and components.
- New orders transition from Received to Awaiting Review.
- Customer and administrative/audit notifications remain covered.
- Review, budget, customer approval, advance payment, authorized work, completed work, and final totals retain their
  coverage.
- Delivery cannot bypass required payment rules.
- Order status history and the automatic approval transition remain covered.
- Public UUID/date tracking exposes only the intended order, history, and attachment data.

Do not "improve" production behavior while converting tests. If a test exposes an application defect, record it
separately and keep the migration step focused on parity.

## Mandatory execution protocol

Each future agent or chat must:

1. Work on one numbered step at a time unless the user explicitly expands the scope.
2. Re-read this plan, `docs/Business_Rules.md`, and the files included in that step.
3. Check the working tree before editing and preserve unrelated user changes.
4. Use Laravel Sail for PHP, Composer, Artisan, and Pest commands.
5. Convert mechanically first. Do not combine syntax conversion with test deduplication, production refactors, or new
   business assertions.
6. Run the focused Pest command for every converted batch.
7. After Pest is installed, run the entire Pest suite at the end of **every** numbered implementation step.
8. Record the executed commands, result counts, assertion counts, duration, failures, and any explained count delta in
   this plan before marking a step complete.
9. Never mark a step complete when the runtime is unavailable or a required command failed.

### Standard verification gate after Pest is installed

Use the narrow command first, then both whole-suite commands:

```bash
vendor/bin/sail php vendor/bin/pest --compact <changed-test-path>
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

The first whole-suite command proves the Pest executable passes serially. Laravel's `artisan test` command detects an
installed Pest runner; the second whole-suite command supplies Laravel's parallel-database recreation controls. Confirm
that its output identifies Pest after Step 2.

If parallel PostgreSQL database creation collides:

1. Retry the same parallel command with `--recreate-databases`.
2. If it still fails, run the serial Pest suite.
3. Treat a passing serial suite plus a failing parallel database bootstrap as an environment/isolation problem until
   evidence shows an application failure.
4. Do not hide the parallel failure; record it and resolve it before the final acceptance step.

If Sail reports that Docker or Podman is unavailable, the socket is inaccessible, or the container cannot start, ask the
user for permission needed to restore container access. Then run:

```bash
vendor/bin/sail artisan list
```

Retry the exact failed test command after access is restored. Do not replace an unexecuted result with a claim that the
tests "should pass".

## Step 1 — Freeze the PHPUnit baseline and resolve the target Pest version

### Why

A trustworthy migration needs a before-and-after contract. It also needs Composer evidence for the latest stable Pest
major instead of relying on a date-sensitive assumption.

### Covered

- `composer.json` and `composer.lock`
- `phpunit.xml`
- `tests/TestCase.php`
- All PHP files under `tests/Unit` and `tests/Feature`
- Current CI test workflow
- Current coverage-driver availability

### Instructions

1. Confirm the working tree and do not mix unrelated changes into the migration.
2. Inventory all `*Test.php` files and runtime-discovered tests. Save temporary reports outside the committed
   documentation if useful.
3. Run the current PHPUnit suite serially and in parallel. Record test count, assertion count, duration, and any
   skipped, incomplete, risky, warning, or deprecation output.
4. Run the existing 90% coverage gate and record the percentage.
5. Inspect current Composer candidates for Pest, the Laravel plugin, Drift, PHPUnit, Collision, and ParaTest.
6. Ask Composer why the newest stable Pest major cannot be installed if resolution fails.
7. Select one stable Pest major shared by `pestphp/pest` and `pestphp/pest-plugin-laravel` and compatible with PHP 8.4,
   Laravel 12, Collision, and the other locked development dependencies.
8. Record the exact selected versions and the evidence. Do not modify dependencies in this step.

### Commands

```bash
vendor/bin/sail artisan list
vendor/bin/sail artisan test --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
vendor/bin/sail artisan test --parallel --coverage --min=90
vendor/bin/sail composer show pestphp/pest --all
vendor/bin/sail composer show pestphp/pest-plugin-laravel --all
vendor/bin/sail composer show pestphp/pest-plugin-drift --all
vendor/bin/sail composer prohibits pestphp/pest '^5.0'
vendor/bin/sail composer why phpunit/phpunit
vendor/bin/sail composer why brianium/paratest
```

If Pest 5 is no longer the newest candidate when this step is executed, replace `^5.0` in the diagnostic command with
the actual newest stable major.

### Completion criteria

#### Step 1 execution record — 2026-07-28

- Inventory refreshed: 27 `tests/Unit` files, 48 `tests/Feature` files, 75 PHP test files total. The 14 files under
  `tests/Frontend` remain out of scope for this PHP migration.
- Runtime: PHP 8.4.17, Laravel 12.64.0, PostgreSQL, and PCOV 1.0.12.
- Serial baseline: `vendor/bin/sail php vendor/bin/phpunit` passed with PHPUnit 11.5.56 — 612 tests, 4,614 assertions,
  1:42.140, no failures or skipped/incomplete/risky/warning/deprecation output.
- Parallel baseline: `vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases` passed with
  ParaTest 7.8.5 — 612 tests, 4,613 assertions, 00:32.580.
- Coverage baseline: `vendor/bin/sail artisan test --parallel --coverage --min=90` passed — 612 tests, 4,628 assertions,
  00:48.954, total coverage 90.1%.
- The test count stayed at 612 in all successful runs. PHPUnit reported different assertion totals by runner mode (4,614
  serial, 4,613 parallel, 4,628 coverage); the cause was not established in this baseline step, so the values are
  recorded as emitted by each command rather than inferred.
- CI currently runs `./vendor/bin/sail artisan test --parallel --processes=auto` in `.github/workflows/tests.yml`. The
  workflow provisions a MySQL service but configures the application testing environment for PostgreSQL; this migration
  step does not change that workflow.
- Stable Composer candidates observed: `pestphp/pest` 5.0.1, `pestphp/pest-plugin-laravel` 5.0.0, and
  `pestphp/pest-plugin-drift` 5.0.0. Pest 5 requires PHPUnit 13.2.4 and the current direct PHPUnit `^11.5.3` and
  ParaTest `^7.8` constraints block its installation. Pest 4.7.5 is also blocked because it requires PHPUnit 12.5.30.
- The Pest 3.8.7 / Laravel plugin 3.2.0 Composer dry-run resolves with the current PHPUnit 11 toolchain. Pest 5 was
  initially selected as the next target, but Step 2 resolution proved it incompatible with Laravel 12.64's locked
  Symfony Process 7.x requirement; the actual selected stable stack is recorded below as Pest 4.7.5. No dependency was
  changed in Step 1.
- An initial Artisan baseline attempt was killed with exit code 137 while another container test process was still
  contending for the shared test database. After Docker access was restored and `testing` was migrated fresh, the direct
  PHPUnit baseline and the required parallel and coverage baselines passed. This was an environment execution issue, not
  an assertion failure.

- [x] Runtime PHPUnit baseline is recorded.
- [x] Coverage baseline is recorded.
- [x] The exact stable Pest and Laravel-plugin versions are recorded.
- [x] Composer compatibility is proven or a concrete blocker is reported.
- [x] No dependency or test file has changed.

**Step 1 complete.**

## Step 2 — Install and bootstrap Pest without converting the suite

### Why

Pest can execute PHPUnit tests because it is built on PHPUnit. Installing it first creates a safe compatibility bridge:
the unchanged suite must pass under Pest before syntax conversion starts.

### Covered

- `composer.json`
- `composer.lock`
- `tests/Pest.php` generated by Pest initialization
- `phpunit.xml`
- `tests/TestCase.php`

### Instructions

1. Remove the direct `phpunit/phpunit` constraint and let Pest own its compatible PHPUnit version.
2. Remove the direct `brianium/paratest` constraint only after Step 1 proves the selected Pest release requires a
   compatible ParaTest version transitively.
3. Require the selected stable Pest major and matching stable Laravel plugin.
4. Require the matching stable Drift plugin temporarily for folder/file conversion. It will be removed in Step 9.
5. Keep `pestphp/pest-plugin` allowed in Composer; it is already present in this repository's Composer configuration.
6. Run `pest --init` and review every generated file. Do not allow initialization to delete or replace
   `tests/TestCase.php`, the Unit/Feature suite definitions, database environment variables, or the coverage source in
   `phpunit.xml`.
7. In `tests/Pest.php`, bind `Tests\TestCase` to `tests/Unit` and `tests/Feature`.
8. Do not apply `RefreshDatabase` globally. Two current files do not use it, and a global trait would change their
   runtime and cost. Preserve database traits at file or proven directory scope during conversion.
9. Run all unchanged PHPUnit-class tests through the Pest executable. Counts and assertions should match the Step 1
   baseline unless a PHPUnit-major compatibility issue is identified and explained.

### Candidate dependency commands

Replace `5` below with the stable major proven in Step 1. Do not run these commands with an unresolved placeholder or
against a development-only release:

```bash
vendor/bin/sail composer remove phpunit/phpunit brianium/paratest --dev --no-update
vendor/bin/sail composer require pestphp/pest:'^5.0' pestphp/pest-plugin-laravel:'^5.0' --dev --with-all-dependencies
vendor/bin/sail composer require pestphp/pest-plugin-drift --dev --with-all-dependencies
vendor/bin/sail php vendor/bin/pest --init
vendor/bin/sail php vendor/bin/pest --version
```

### Verification

```bash
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
vendor/bin/sail composer why phpunit/phpunit
vendor/bin/sail composer why brianium/paratest
```

### Completion criteria

- [x] Pest and the Laravel plugin are stable, compatible direct development dependencies.
- [x] PHPUnit and ParaTest are not unnecessarily pinned directly.
- [x] `tests/Pest.php` uses the existing Laravel base test case for Unit and Feature tests.
- [x] The unchanged suite passes through Pest serially and in parallel.
- [x] Runtime test/assertion parity with Step 1 is recorded.

#### Step 2 execution record — 2026-07-28

- Replaced the direct `phpunit/phpunit` and `brianium/paratest` development requirements with
  `pestphp/pest:^4.7.5`, `pestphp/pest-plugin-laravel:^4.1.0`, and
  `pestphp/pest-plugin-drift:^4.1.0`.
- The Pest 5 installation attempt was not resolvable: Pest 5 requires `symfony/process:^8.1.0`, while Laravel 12.64
  requires `symfony/process:^7.2.0`. Composer reverted that failed attempt without leaving a partial installation.
- The resolved stable stack is Pest 4.7.5, Laravel plugin 4.1.0, Drift 4.1.0, PHPUnit 12.5.30, and transitive ParaTest
  7.20.0. Composer confirms Pest requires PHPUnit and ParaTest transitively requires PHPUnit; neither is a direct root
  requirement now.
- `vendor/bin/sail php vendor/bin/pest --init` preserved `phpunit.xml` and `tests/TestCase.php`, created
  `tests/Pest.php`, and created two temporary example tests. The temporary examples were removed after review.
- `tests/Pest.php` binds `Tests\\TestCase` to both `tests/Unit` and `tests/Feature`. No `RefreshDatabase` trait is
  applied globally; existing per-file database behavior remains unchanged.
- Unchanged PHPUnit-class suite through Pest: `vendor/bin/sail php vendor/bin/pest --compact` — 612 passed, 4,618
  assertions, 108.46s.
- Parallel Pest-backed suite: `vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases` —
  612 passed, 4,622 assertions, 42.08s, 8 processes.
- Test count remains 612 in both Pest runs, matching the Step 1 baseline. Assertion totals vary by runner mode and are
  recorded as emitted; no test failure or behavior regression was observed.
- Composer's post-update IDE helper hooks modified generated helper files; those unrelated generated changes were
  restored. Final Step 2 changes are limited to `composer.json`, `composer.lock`, `tests/Pest.php`, and this plan.

**Step 2 complete.**

## Step 3 — Pilot the conversion with Unit enum tests

### Why

The five enum files are a bounded pilot with many assertions but little shared setup. They expose naming, imports,
expectations, exception assertions, datasets, and strict-type conventions before database-heavy files are converted.

### Covered

- `tests/Unit/app/Enums/*Test.php`
- 5 files and 42 current test cases

### Instructions

1. Run Drift only against one enum file first.
2. Review the result manually. Remove the namespace and PHPUnit `Test` attribute imports only when the Pest file no
   longer needs them.
3. Preserve `declare(strict_types=1)`, test descriptions, assertion strictness, and exception expectations.
4. Prefer `it()` or `test()` consistently. Do not turn multiple existing tests into a dataset yet; this is a parity
   step.
5. Keep any file-local `RefreshDatabase` behavior even if it appears unnecessary. Trait cleanup is a later,
   evidence-backed optimization.
6. Convert the remaining four enum files only after the first file and then the full suite pass.
7. Inspect Drift output rather than assuming conversion is complete; the official migration guide says manual cleanup
   may still be required.

### Commands

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Enums/OrderItemTypeTest.php
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Enums/OrderItemTypeTest.php
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Enums
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Enums
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

### Completion criteria

- [x] All five enum files use Pest syntax.
- [x] No enum assertion or exception path was dropped.
- [x] Focused, serial full-suite, and parallel full-suite gates pass.

#### Step 3 execution record — 2026-07-28

- Pest Drift was run with `vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Enums`; the installed Pest 4 Drift
  command accepts the directory target and migrated all five enum files. The plan's single-file Drift example was not
  accepted by this installed command because it treats the argument as a directory; that attempt made no edit.
- Converted files: `OrderItemTypeTest.php`, `OrderPriorityTest.php`, `OrderStatusTest.php`, `UserRoleTest.php`, and
  `UserTypeTest.php`. Each file preserves its original test-case count: 4, 11, 10, 7, and 10 respectively (42 total).
- The first focused run found a Drift conversion issue where PHPUnit assertion-message arguments were interpreted as
  additional Pest matcher values. Those matcher calls were corrected without changing the tested values or paths.
- Focused gate: `vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Enums` — 42 passed, 331 assertions, 3.77s
  before formatting; the focused suite also passed after Pint.
- Serial Pest gate: `vendor/bin/sail php vendor/bin/pest --compact` — 612 passed, 4,622 assertions, 103.89s.
- Laravel serial Pest-backed gate: `vendor/bin/sail artisan test --compact` — 612 passed, 4,623 assertions, 147.48s.
- Parallel Pest-backed gate: `vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases` —
  612 passed, 4,619 assertions, 39.27s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully, and `git diff --check` passed. The enum
  source contains no PHPUnit namespace, `Test` attribute, or test class declarations.
- Assertion totals vary by runner mode, as they did in the Step 1 and Step 2 baselines; no test-count delta occurred.

**Step 3 complete.**

## Step 4 — Convert Unit model tests

### Why

Model tests exercise casts, relations, UUID behavior, mass assignment, money/date values, and database factories. They
need exact semantic parity before higher-level lifecycle tests move.

### Covered

- `tests/Unit/app/Models/*Test.php`
- 10 files and 89 current test cases

### Instructions

1. Convert one model file at a time with Drift or manually.
2. Preserve `RefreshDatabase` and Laravel `Tests\TestCase` binding.
3. Translate custom `setUp()` state into `beforeEach()` only when each property and fake remains available through
   Pest's bound `$this`. Keeping equivalent file-local setup is more important than shortening the code.
4. Preserve strict comparisons for enum values, decimal/money strings, dates, casts, UUIDs, and relation types.
5. Do not consolidate duplicate model tests or move tests between Unit and Feature during this step.
6. Run the affected file after every conversion; then run the whole model folder and mandatory full-suite gate.

### Commands

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Models/<FileName>Test.php
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Models/<FileName>Test.php
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Models
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

### Completion criteria

- [x] All ten Unit model files use Pest syntax.
- [x] Cast, relation, date, UUID, assignment, and persistence assertions are preserved.
- [x] Focused and full-suite gates pass.

#### Step 4 execution record — 2026-07-28

- Converted all ten files under `tests/Unit/app/Models` with Pest Drift. The conversion preserved the existing
  `RefreshDatabase` behavior, Laravel test-case binding, setup fake, and model assertions; unused PHPUnit `Test`
  attribute imports were removed.
- Focused gate after Pint: `vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Models` — 89 passed, 286
  assertions, 10.54s.
- Serial full Pest gate: `vendor/bin/sail php vendor/bin/pest --compact` — 612 passed, 4,623 assertions, 123.41s.
- Parallel full Pest-backed gate: `vendor/bin/sail artisan test --compact --parallel --processes=8
  --recreate-databases` — 612 passed, 4,618 assertions, 54.11s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully, and `git diff --check` passed. No PHPUnit
  class, `Test` attribute, or PHPUnit import remains in the converted model files.
- Initial Sail execution reported Docker/Podman unavailable inside the sandbox. Docker access was restored with
  permission and the exact required commands were then rerun successfully.

**Step 4 complete.**

## Step 5 — Convert the remaining Unit suite

### Why

This finishes the Unit tree and deliberately handles the most complex setup and helper patterns after the pilot and
model conventions are proven.

### Covered

- `tests/Unit/app/Services/OrderLifecycleServiceTest.php`
- `tests/Unit/app/Traits/HasAttachmentsTest.php`
- `tests/Unit/database/factories/*Test.php`
- `tests/Unit/database/migrations/*Test.php`
- `tests/Unit/database/seeders/*Test.php`
- 12 files and 126 current test cases

### Instructions

Convert in this order:

1. Database migration tests.
2. Factory tests.
3. Seeder tests.
4. `HasAttachmentsTest`, preserving Mockery close/cleanup and any test helper class.
5. `OrderLifecycleServiceTest`, preserving its object properties, notification fake, private builders, exception paths,
   order ownership checks, payment rules, and status transitions.

For every file:

- Preserve private helpers as file-local functions or bound closures with explicit return types where practical.
- Translate `setUp()` to `beforeEach()` without moving request-specific state into static/global storage.
- Translate `tearDown()` to `afterEach()` and ensure cleanup still runs when a test fails.
- Keep assertions semantically equivalent. Pest expectations may be used, but a working PHPUnit assertion through
  `$this` is acceptable during mechanical migration.
- Do not change factory, migration, seeder, service, or production code to make converted syntax pass unless the user
  separately authorizes a product fix.

### Commands

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/database/migrations
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/database/migrations
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/database/factories
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/database/factories
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/database/seeders
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/database/seeders
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Traits/HasAttachmentsTest.php
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Traits/HasAttachmentsTest.php
vendor/bin/sail php vendor/bin/pest --drift tests/Unit/app/Services/OrderLifecycleServiceTest.php
vendor/bin/sail php vendor/bin/pest --compact tests/Unit/app/Services/OrderLifecycleServiceTest.php
vendor/bin/sail php vendor/bin/pest --compact --testsuite=Unit
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

### Completion criteria

- [x] All 27 Unit files use Pest syntax.
- [x] Unit runtime counts and assertion deltas are reconciled against Step 1.
- [x] Lifecycle, cleanup, factory, migration, and seeder behavior remains covered.
- [x] Unit, serial full-suite, and parallel full-suite gates pass.

#### Step 5 execution record — 2026-07-28

- Converted the five migration files, three factory files, two seeder files, `HasAttachmentsTest.php`, and
  `OrderLifecycleServiceTest.php` to Pest syntax. The final Unit tree contains 27 Pest test files and no PHPUnit imports
  or `Test` attributes.
- Focused batches: migrations — 47 passed, 253 assertions, 4.25s; factories — 42 passed, 227 assertions, 8.79s;
  seeders — 10 passed, 143 assertions, 16.39s; `HasAttachments` — 3 passed, 19 assertions, 0.76s; lifecycle service — 24
  passed, 44 assertions, 4.99s. These total the planned 126 tests and 686 assertions for Step 5.
- The Unit gate `vendor/bin/sail php vendor/bin/pest --compact tests/Unit` passed — 257 tests, 1,302 assertions, 43.38s.
  The test count matches the Step 1 Unit inventory; Pest assertion totals are recorded as emitted because the prior plan
  records runner-mode variation rather than a stable per-suite assertion baseline.
- Serial full Pest gate: `vendor/bin/sail php vendor/bin/pest --compact` — 612 passed, 4,618 assertions, 133.11s.
- Parallel full Pest-backed gate: `vendor/bin/sail artisan test --compact --parallel --processes=8
  --recreate-databases` — 612 passed, 4,620 assertions, 54.42s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully, and `git diff --check` passed. The converted
  helper and service setup preserve cleanup, fixture state, notifications, private-helper behavior, and lifecycle
  assertions. Drift message-argument and helper-binding defects were corrected without changing the underlying tested
  behavior.

**Step 5 complete.**

## Step 6 — Convert foundational Feature tests

### Why

Authentication, settings, localization, and the dashboard establish shared HTTP, session, authentication, translation,
and Inertia patterns before larger domain controllers are migrated.

### Covered

- `tests/Feature/DashboardTest.php`
- `tests/Feature/Auth/*Test.php`
- `tests/Feature/Settings/*Test.php`
- `tests/Feature/Localization/*Test.php`
- 12 files and 42 current test cases

### Instructions

1. Convert Dashboard and Auth first, followed by Settings and Localization.
2. Preserve database refresh, authenticated/guest branches, session state, cookie handling, locale precedence, and
   Inertia component/prop assertions.
3. Convert localization helper methods carefully; do not globalize mutable locale or session state.
4. Preserve negative paths such as invalid password, invalid verification hash, unsupported locale, and guest access.
5. Run each directory, then the required entire Pest suite.

### Commands

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/DashboardTest.php
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/Auth
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/Auth tests/Feature/DashboardTest.php
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/Settings
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/Settings
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/Localization
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/Localization
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

### Completion criteria

- [x] Foundational Feature files use Pest syntax.
- [x] Auth, settings, localization, and Inertia assertions are preserved.
- [x] Focused and full-suite gates pass.

#### Step 6 execution record — 2026-07-28

- Converted the 12 Dashboard, Auth, Settings, and Localization Feature files to Pest syntax. The root Dashboard file was
  converted manually because the installed Drift command requires a directory path for migration; the other 11 files
  were converted with Drift. All converted files retain their per-file `RefreshDatabase` scope, HTTP branches, session
  and cookie state, locale precedence, Inertia assertions, and negative paths. The converted tree contains no PHPUnit
  test classes, attributes, or imports.
- Focused gate: `vendor/bin/sail php vendor/bin/pest --compact tests/Feature/DashboardTest.php tests/Feature/Auth
  tests/Feature/Settings tests/Feature/Localization` passed — 42 tests, 1,047 assertions, 7.03s.
- Serial full Pest gate: `vendor/bin/sail php vendor/bin/pest --compact` passed — 612 tests, 4,629 assertions, 109.09s.
- Parallel full Pest-backed gate: `vendor/bin/sail artisan test --compact --parallel --processes=8
  --recreate-databases` passed — 612 tests, 4,615 assertions, 47.79s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully. TIA and coverage were not changed or claimed
  in this step; they remain covered by Steps 11–12.

**Step 6 complete.**

## Step 7 — Convert domain-supporting Feature tests

### Why

These files validate the domain pieces used by controllers: models, policies, observers, mail, notifications,
attachments, factories, and request validation. Converting them before controllers gives failures a smaller diagnostic
surface.

### Covered

- `tests/Feature/app/Http/Requests/*Test.php`
- `tests/Feature/app/Mail/*Test.php`
- `tests/Feature/app/Models/*Test.php`
- `tests/Feature/app/Notifications/*Test.php`
- `tests/Feature/app/Observers/*Test.php`
- `tests/Feature/app/Policies/*Test.php`
- `tests/Feature/app/Traits/*Test.php`
- `tests/Feature/database/factories/*Test.php`
- 18 files and 127 current test cases

### Instructions

1. Convert models and factory integration tests.
2. Convert policies and request validation, preserving all role/ownership/authorization matrices.
3. Convert observers, mail, and notifications, preserving fake ordering and after-commit expectations.
4. Convert attachment trait tests, preserving storage/file cleanup in `afterEach()`.
5. Keep locale-sensitive notification assertions isolated per test.
6. Do not replace explicit cases with datasets until exact parity is achieved and recorded.

### Commands

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Models
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Models
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/database/factories
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/database/factories
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Policies
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Policies
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Http/Requests
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Http/Requests
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Observers
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Observers
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Mail
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Notifications
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Mail tests/Feature/app/Notifications
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Traits
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Traits
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

### Completion criteria

- [x] All 18 domain-supporting Feature files use Pest syntax.
- [x] Cleanup, role matrices, observers, mail, notifications, and validation remain covered.
- [x] Focused and full-suite gates pass.

#### Step 7 execution record — 2026-07-28

- Converted all 18 domain-supporting Feature files to Pest syntax: models, factory integration, policies, request
  validation, observers, mail, notifications, and attachments. Per-file database/storage setup, role and ownership
  matrices, fake ordering, notification/mail assertions, cleanup, and validation behavior were preserved. Two helpers
  that shared the original class-local name `users()` were renamed to avoid global-function collisions after namespace
  removal. The converted Step 7 tree contains no PHPUnit test classes, attributes, or imports.
- Focused Step 7 gate: `vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Http/Requests
  tests/Feature/app/Mail tests/Feature/app/Models tests/Feature/app/Notifications tests/Feature/app/Observers
  tests/Feature/app/Policies tests/Feature/app/Traits tests/Feature/database/factories` passed — 127 tests, 725
  assertions, 38.84s.
- Serial full Pest gate: `vendor/bin/sail php vendor/bin/pest --compact` passed — 612 tests, 4,620 assertions, 106.13s.
- Parallel full Pest-backed gate: `vendor/bin/sail artisan test --compact --parallel --processes=8
  --recreate-databases` passed — 612 tests, 4,618 assertions, 52.96s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully. Coverage and TIA were not changed or claimed
  in this step; they remain covered by Steps 11–12.

**Step 7 complete.**

## Step 8 — Convert controller, route, history, lifecycle, and tracking Feature tests

### Why

This is the highest-risk batch: 18 files and 186 test cases cover the externally observable business lifecycle. It is
last so all lower-level Pest conventions are already proven.

### Covered

- `tests/Feature/app/Http/Controllers/*Test.php`
- Order creation, catalog, attachments, history, policies reached through HTTP, lifecycle transitions, route access,
  users, health, and public tracking

### Instructions

Convert in independently verified groups:

1. Health, Users, Catalog, CatalogCache, CatalogRoute, and CatalogSeederIntegration.
2. AttachmentController.
3. OrderHistoryController, OrderHistoryApiIntegration, OrderHistoryAttachmentsFix, OrderHistoryDescriptionField, and
   OrderHistoryTracking.
4. OrderController, OrderAdvancedController, and OrderRoute.
5. OrderLifecycleController and OrderBusinessRulesEdgeCases.
6. PublicOrderTracking.

For each group:

- Preserve setup properties and private builders as file-local helpers.
- Preserve request payloads, validation errors, authorization status codes, response resources, history ordering,
  notification assertions, transaction behavior, decimal totals, service ownership, status preconditions, and public
  data boundaries.
- Compare the migrated assertions directly with `docs/Business_Rules.md`.
- Run the group immediately, then the full Pest suite before starting the next group. This deliberately makes Step 8
  stricter than the standard end-of-step gate.

### Commands

Use the group files for focused runs. Pest 4's Drift command accepts the controller directory, so run the conversion
once for this directory, then run each group directly:

```bash
vendor/bin/sail php vendor/bin/pest --drift tests/Feature/app/Http/Controllers
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Http/Controllers/HealthControllerTest.php
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

After all six groups:

```bash
vendor/bin/sail php vendor/bin/pest --compact tests/Feature/app/Http/Controllers
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
vendor/bin/sail php vendor/bin/pest --parallel --coverage --min=90
```

### Completion criteria

- [x] All 18 controller files use Pest syntax.
- [x] Every business-rule invariant listed in this plan remains represented by passing assertions.
- [x] Each subgroup and the entire suite pass.
- [x] Coverage remains at least 90%.

### Execution record — 2026-07-28

- Converted all 18 controller Feature files to Pest syntax with `pest --drift` at the controller-directory level.
  Removed stale PHPUnit `#[Test]` imports and adapted file-local helpers to Pest's global test context. Two helper names
  were made unique after class-local methods became global functions.
- Focused group gates passed: group 1 — 28 tests, 335 assertions; group 2 — 22 tests, 107 assertions; group 3 — 27
  tests, 395 assertions; group 4 — 50 tests, 375 assertions; group 5 — 46 tests, 209 assertions; group 6 — 13 tests, 127
  assertions. The complete controller directory passed with 186 tests and 1,548 assertions.
- Final serial Pest gate after Pint: `vendor/bin/sail php vendor/bin/pest --no-progress --testdox-summary` passed — 612
  tests, 4,623 assertions, 118.91s.
- Parallel gate: `vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases` passed — 612
  tests, 4,626 assertions, 74.70s, 8 processes.
- Coverage gate: `vendor/bin/sail php vendor/bin/pest --parallel --coverage --min=90` passed — 90.1% total coverage, 612
  tests, 4,612 assertions, 93.84s, 8 processes.
- `vendor/bin/sail bin pint --dirty --format agent` completed successfully. The full Pest suite was rerun after Pint and
  passed. TIA was not changed or claimed; it remains covered by the later TIA step.

**Step 8 complete.**

## Step 9 — Remove migration scaffolding and enforce Pest-only source syntax

### Why

Passing through Pest does not prove the source suite was ported; Pest can run PHPUnit classes. This step removes the
temporary converter and proves no PHPUnit test class or PHPUnit test attribute remains under Unit or Feature.

### Covered

- All PHP tests under `tests/Unit` and `tests/Feature`
- `composer.json` and `composer.lock`
- `tests/Pest.php`
- `tests/TestCase.php`
- `phpunit.xml`
- Composer test scripts

### Instructions

1. Search for classes extending a test case, `#[Test]`, PHPUnit `Test` imports, `test_*` methods, data-provider
   attributes, and lifecycle methods left inside test classes.
2. Manually convert any remaining source.
3. Keep `tests/TestCase.php`; Pest uses it as the Laravel application base class.
4. Keep `phpunit.xml`; Pest uses PHPUnit's suite, environment, and coverage configuration.
5. Remove `pestphp/pest-plugin-drift` after the search is clean.
6. Update Composer test scripts so their intent is explicit:
    - `test`, `test:unit`, `test:feature`, and `test:coverage` invoke Pest.
    - Preserve `--parallel` and the 90% coverage threshold where currently present.
    - Keep the project's Sail invocation expectations in developer-facing commands.
7. Do not rename the `Unit` and `Feature` suites or move files merely for style.

### Commands

```bash
rg -n "extends TestCase|PHPUnit\\\\Framework\\\\Attributes\\\\Test|#\\[Test\\]|function test_|#\\[DataProvider|@dataProvider" tests/Unit tests/Feature --glob '*Test.php'
vendor/bin/sail composer remove pestphp/pest-plugin-drift --dev
vendor/bin/sail composer validate --strict
vendor/bin/sail php vendor/bin/pest --compact --testsuite=Unit
vendor/bin/sail php vendor/bin/pest --compact --testsuite=Feature
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

The `rg` command must return no PHPUnit test-class syntax. PHPUnit assertion calls through Pest's bound `$this` are not
themselves proof of an unported class and may remain if they are clearer or have no direct Pest expectation equivalent.

### Completion criteria

- [ ] All 75 PHP test files use Pest source syntax.
- [ ] Drift is removed.
- [ ] PHPUnit is transitive through Pest rather than the project's direct runner.
- [ ] Composer scripts invoke Pest intentionally.
- [ ] Unit, Feature, serial full-suite, and parallel full-suite gates pass.

## Step 10 — Introduce Pest-native organization without changing coverage

### Why

Mechanical parity comes first. Once parity is proven, Pest-native constructs can improve clarity and enable additional
test types without obscuring whether the port itself lost behavior.

### Covered

- `tests/Pest.php`
- Repeated role, status, locale, enum, and validation cases where the current suite already expresses the same rule
- Optional architecture tests based only on established repository conventions

### Instructions

1. Use `describe()` blocks for related behavior only where they improve navigation.
2. Convert genuinely repeated input/output cases to named datasets. Preserve failure-path names and assertion
   specificity.
3. Extract shared helpers only when at least two files already duplicate the exact setup and the helper does not retain
   mutable request state.
4. Add architecture tests only for rules already evidenced by the repository or its documented conventions. Do not
   invent new layering rules during a testing-framework migration.
5. Treat mutation testing, type coverage, stress testing, and browser testing as separately approved enhancements:
    - Code coverage and TIA are in scope here.
    - Type coverage overlaps the existing Larastan gate and should not replace it.
    - Mutation testing needs an explicit time budget and selected target classes.
    - Stress tests need a running target and performance acceptance criteria.
    - Pest browser tests require browser/Playwright dependencies and must not silently replace the existing Vitest
      frontend suite.
6. After each refactor, compare runtime test and assertion count deltas with the pre-refactor Pest result and explain
   every change.

### Verification

```bash
vendor/bin/sail php vendor/bin/pest --compact <refactored-path>
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
vendor/bin/sail composer run test:types
```

### Completion criteria

- [ ] Pest-native organization improves existing tests without weakening assertions.
- [ ] Any test/assertion count delta is intentional and recorded.
- [ ] Larastan and all Pest gates pass.
- [ ] Optional plugins or test types were not added without an explicit decision.

## Step 11 — Enable and validate local TIA

### Why

TIA builds a dependency graph with a coverage driver, then reruns affected tests and replays cached results. It must be
validated against this repository's Laravel, Inertia, PostgreSQL, parallel-test, and Sail environment rather than merely
adding `--tia` to a script.

### Covered

- Coverage-driver availability inside Sail
- `tests/Pest.php` TIA configuration
- TIA baseline creation and replay
- Sail container persistence
- Parallel database behavior

### Instructions

1. Confirm PCOV or Xdebug is installed and enabled **inside the Sail container**. The repository currently declares
   `SAIL_XDEBUG_MODE=develop,debug,coverage` in `.env.example`, but runtime verification is required.
2. Ensure the converted suite is fully passing before creating the baseline.
3. Create a fresh baseline from the clean converted code state.
4. Run the same command again without source changes and verify the output reports replayed results rather than
   executing the whole suite.
5. On the next real, scoped application change, run TIA and confirm the expected affected subset executes. Then run the
   full suite before merging; TIA validation does not remove the final full-suite gate during rollout.
6. Decide whether `pest()->tia()->always()->locally()` belongs in `tests/Pest.php` or whether explicit `--tia` scripts
   are clearer for the team. Keep `--no-tia` available for troubleshooting.
7. Test what happens after restarting/recreating Sail. Pest documents TIA state under `~/.pest/tia/<project-key>/`. The
   effective home directory is inside the container, so persistence across container recreation is currently unverified.
8. Do not change or repurpose `HOME` to move the cache. If persistence is required, design an explicit, narrowly scoped
   Sail bind mount or baseline copy process and obtain approval for that infrastructure change.
9. Record baseline duration, replay duration, affected/uncached/replayed counts, coverage-driver name, and persistence
   result.

### Commands

```bash
vendor/bin/sail php -m
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail php vendor/bin/pest --parallel --tia --fresh
vendor/bin/sail php vendor/bin/pest --parallel --tia
vendor/bin/sail php vendor/bin/pest --baseline
vendor/bin/sail php vendor/bin/pest --parallel --coverage --min=90
```

If direct Pest parallel execution hits stale test databases, first prepare them with:

```bash
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
```

Then retry the exact TIA command. Do not claim TIA is working based only on a normal parallel Pest run.

### Completion criteria

- [ ] A coverage driver is verified inside Sail.
- [ ] A fresh TIA baseline completes successfully.
- [ ] A no-change replay is demonstrably faster and reports replayed results.
- [ ] An affected-change run selects a credible subset.
- [ ] Sail cache persistence behavior is known and recorded.
- [ ] The full non-TIA Pest suite still passes.

## Step 12 — Integrate TIA with CI and complete final acceptance

### Why

Local TIA state alone does not improve shared CI. Pest's documented shared-baseline flow assumes GitHub Actions and the
GitHub CLI, while this repository executes PHP commands inside Sail. The container boundary must be handled explicitly.

### Covered

- `.github/workflows/tests.yml`
- A dedicated TIA baseline workflow if the team adopts shared baselines
- GitHub CLI availability/authentication where Pest runs
- Upload/download path for TIA state created inside Sail
- Required full-suite, coverage, and quality gates

### Instructions

1. Update the existing test workflow to show Pest as the runner and keep a full-suite required gate during rollout.
2. Add a dedicated TIA baseline workflow based on Pest's current official example:
    - Run on pushes to the repository's chosen base branch.
    - Optionally run on a schedule and by manual dispatch.
    - Use full Git history (`fetch-depth: 0`).
    - Enable PCOV or Xdebug in the environment where Pest actually runs.
    - Create a fresh parallel TIA baseline.
    - Resolve the baseline directory with `pest --baseline`.
    - Upload an artifact named `pest-tia-baseline`, including hidden files.
3. Do not copy the official non-container workflow blindly. In this repository Pest runs inside Sail, while GitHub's
   artifact action runs on the host. Prove how the baseline moves from the container path to a narrow host-mounted
   artifact path.
4. If developer baseline fetching is enabled with `baselined()`, verify that `gh` is installed and authenticated on the
   same machine/container where Pest runs. Pest documents that a missing/unavailable `gh` falls back to a local rebuild.
5. Keep secrets and authentication out of committed files.
6. Initially run TIA as an optimization in addition to a full required suite. Only consider making an affected-only TIA
   job the primary PR gate after measured, stable results; retain a full suite on the base branch and/or schedule.
7. Run all final gates and reconcile results against Step 1.

### Final commands

```bash
vendor/bin/sail php vendor/bin/pest --version
vendor/bin/sail php vendor/bin/pest --compact --testsuite=Unit
vendor/bin/sail php vendor/bin/pest --compact --testsuite=Feature
vendor/bin/sail php vendor/bin/pest --compact
vendor/bin/sail artisan test --compact --parallel --processes=8 --recreate-databases
vendor/bin/sail php vendor/bin/pest --parallel --coverage --min=90
vendor/bin/sail php vendor/bin/pest --parallel --tia --fresh
vendor/bin/sail php vendor/bin/pest --parallel --tia
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer run test:types
vendor/bin/sail composer validate --strict
vendor/bin/sail yarn run test:unit
git diff --check
```

The frontend Vitest command remains a separate regression gate. Pest TIA does not port or replace
`tests/Frontend/*.spec.ts`.

### Final acceptance criteria

- [ ] The latest stable compatible Pest release is installed and recorded.
- [ ] All 75 Unit and Feature PHP files use Pest syntax.
- [ ] No PHPUnit test class, `#[Test]` method, or data-provider attribute remains in Unit/Feature.
- [ ] PHPUnit exists only as Pest's underlying dependency, not as the project's direct runner.
- [ ] Unit, Feature, serial, parallel, and 90% coverage gates pass.
- [ ] Business-rule assertions remain intact.
- [ ] Pint, Larastan, Composer validation, Vitest, and diff checks pass.
- [ ] TIA fresh baseline, replay, and affected-subset behavior are verified.
- [ ] Local Sail persistence and CI artifact behavior are documented from actual runs.
- [ ] Full-suite and TIA timing improvements are recorded without fabricated estimates.

## Progress log

Add one entry per completed step. Do not pre-fill results.

| Step | Date       | Agent/chat | Focused result               | Full Pest result                                     | Coverage/TIA result              | Notes                                                                                                                                                            |
|------|------------|------------|------------------------------|------------------------------------------------------|----------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 1    |            |            |                              | Not installed yet                                    |                                  |                                                                                                                                                                  |
| 2    |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
| 3    |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
| 4    | 2026-07-28 | Codex      | 89 passed, 286 assertions    | 612 passed, 4,623 serial; 612 passed, 4,618 parallel | Not in scope                     | Pest conversion and both full-suite gates passed; parallel run used 8 recreated databases.                                                                       |
| 5    | 2026-07-28 | Codex      | 126 passed, 686 assertions   | 612 passed, 4,618 serial; 612 passed, 4,620 parallel | Not in scope                     | All 27 Unit files are Pest-only; Unit gate 257 passed, 1,302 assertions. Parallel run used 8 recreated databases.                                                |
| 6    | 2026-07-28 | Codex      | 42 passed, 1,047 assertions  | 612 passed, 4,629 serial; 612 passed, 4,615 parallel | Not in scope                     | All 12 foundational Feature files are Pest-only; parallel run used 8 recreated databases. TIA and coverage remain in Steps 11–12.                                |
| 7    | 2026-07-28 | Codex      | 127 passed, 725 assertions   | 612 passed, 4,620 serial; 612 passed, 4,618 parallel | Not in scope                     | All 18 domain-supporting Feature files are Pest-only; parallel run used 8 recreated databases. TIA and coverage remain in Steps 11–12.                           |
| 8    | 2026-07-28 | Codex      | 186 passed, 1,548 assertions | 612 passed, 4,623 serial; 612 passed, 4,626 parallel | 90.1% coverage; TIA not in scope | All 18 controller Feature files are Pest-only; six focused groups passed. Parallel and coverage runs used 8 processes with recreated databases where applicable. |
| 9    |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
| 10   |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
| 11   |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
| 12   |            |            |                              |                                                      |                                  |                                                                                                                                                                  |
