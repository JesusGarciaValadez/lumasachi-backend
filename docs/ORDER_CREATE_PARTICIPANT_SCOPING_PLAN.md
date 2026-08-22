# Order Create Participant Scoping Plan

## Status and scope

**Status:** participant-scoping implementation complete; final repository-wide verification is partial because unrelated
PHPStan baseline errors and one unrelated browser financial-summary assertion remain.

This plan records the implementation sequence and verification for the authenticated Laravel/Inertia order-create flow
in `lumasachi-backend`. The implementation was explicitly authorized after the red-test stage. The React Native project
is out of scope and was not changed.

This plan complements [`ORDER_CREATE_STEP_1_REFINEMENT_PLAN.md`](ORDER_CREATE_STEP_1_REFINEMENT_PLAN.md); it covers
company-scoped customer and employee selection only.

**Priority criterion:** enforce the company boundary on the server before improving the form experience.

## Implementation decisions used in this run

The user request required implementation, while `Business_Rules.md` does not define the participant-company matrix. The
following least-privilege defaults were therefore recorded and used consistently in the tests and implementation:

- “Employees” means users with the exact `Employee` role; Administrators are not assignable employees.
- Super Administrators may select active companies only; participant lists contain active users only.
- An Administrator or Employee with a null `company_id` receives empty participant lists and cannot use null as a
  tenant.
- The existing lookup route semantics are changed in this repository; no external API consumer was identified locally.
- Super Administrator company selection is temporary validation context and is removed before order persistence.
- Super Administrators start with a blank required company selector, even if future data gives them a `company_id`.

If product requirements differ from these defaults, the role/company contract must be revised before changing the code.

## Objective and acceptance contract

The Step 1 order form must expose only valid participants for the actor's effective company:

| Actor               | Company selector                                    | Initial participant loading                          | Allowed participant scope                                            |
|---------------------|-----------------------------------------------------|------------------------------------------------------|----------------------------------------------------------------------|
| Super Administrator | Visible as the first field above the rest of Step 1 | Do not load participants until a company is selected | Active customers and employees belonging to the selected company     |
| Administrator       | Hidden                                              | Load on first render                                 | Active customers and employees belonging to the actor's `company_id` |
| Employee            | Hidden                                              | Load on first render                                 | Active customers and employees belonging to the actor's `company_id` |
| Other roles         | Cannot reach the create route                       | Not applicable                                       | Not applicable                                                       |

Required behavior:

- A Super Administrator's own nullable `company_id` must not be used as the participant scope. The role receives a
  company selector and may choose a different company for each new order.
- Changing the selected company must clear the current customer and employee selections before replacing the option
  lists. A stale response from a previous selection must not overwrite the newer selection.
- An Administrator or Employee with no `company_id` must not be treated as belonging to a shared “null company.” The
  recommended least-privilege behavior is empty participant lists plus an explanatory empty state.
- The server must reject forged customer, employee, or company IDs even when the UI is bypassed.
- The selected company is a lookup context unless the product explicitly decides that an order must persist its own
  company owner. No `orders.company_id` field, order-history event, or historical-record migration is part of this plan.

## Business-rule boundary

[`Business_Rules.md`](Business_Rules.md) defines the order's received pieces, the creation transition from `Recibido`
to `Esperando Revisión`, customer/audit notifications, and later lifecycle behavior. It does **not** define the
company-scoping rules for order participants. The role/company rules in this request therefore become an additional
authenticated order-intake contract; they must not be retroactively invented as historical order data.

The existing order implementation already creates the order, motor information, received items, and component rows in a
transaction and transitions the new order to `Awaiting Review`. That lifecycle and its history/notifications must remain
unchanged.

## Current-code audit

### Request and route flow

1. `routes/web.php:75-81` protects `GET /orders/create` with `can:create,Order`. `OrderPolicy::create()` currently
   allows Super Administrators, Administrators, and Employees, which matches the actor roles in this request.
2. `OrderPageController::create()` at `app/Http/Controllers/OrderPageController.php:44-47` renders
   `Orders/Create` without role, company, or company-option props.
3. `resources/js/pages/Orders/Create.vue:153-169` loads the catalog, customers, and employees once on mount through
   `useOrderApi`; there is no company selector or reload path.
4. `OrderController::store()` uses `StoreOrderWithItemsRequest` and passes its validated payload plus the authenticated
   actor to `OrderLifecycleService::createOrderWithMotorItems()`.

### Current participant queries

`app/Http/Controllers/UsersController.php:18-69` exposes:

- `GET /api/v1/users/employees`: filters by the actor's company, but if `company_id` is `null` it deliberately uses
  `whereNull('company_id')`. It excludes only `Customer`, so it currently returns every active non-customer role rather
  than only the `Employee` role.
- `GET /api/v1/users/customers`: deliberately returns customers from a different company, and for a non-null actor
  company also includes customers with no company. This is the inverse of the requested same-company behavior.
- `routes/api.php:162-166` protects both lookup routes with `auth:sanctum` only; it does not require the actor to have
  order-creation permission. The protected web page is therefore stricter than the lookup API, and an authenticated
  Customer can currently call these lists directly.
- Both endpoints use the broad `UserResource`, which includes email, phone, notes, preferences, and timestamps even
  though the order form only needs an option identity and display name.

`User` already has `customers()` and `employees()` query scopes, a nullable `company_id`, an enum-cast `role`, and a
`company()` relationship. `Company` already has `active()` and `inactive()` scopes. No new model or migration is needed
merely to filter existing participants.

### Current order write boundary

`app/Http/Requests/StoreOrderWithItemsRequest.php:27-52` checks that `customer_id` and `assigned_to` exist, but does not
currently check their role, active state, company, or relationship to the actor/selected company.
`OrderLifecycleService::createOrderWithMotorItems()` trusts those validated IDs before beginning the order transaction.
The UI lists are therefore a convenience only, not a security boundary.

`orders` currently stores `customer_id`, `assigned_to`, `created_by`, and `updated_by`, but no `company_id`. The
customer and assigned employee already make the intended company inferable for this selection use case. Adding a new
order-company column would be a separate data-model decision and could affect reporting, authorization, notifications,
and historical interpretation; do not add it as part of this fix.

### Existing tests and plans

- `tests/Feature/app/Http/Controllers/UsersControllerTest.php` currently asserts the old behavior: same-company
  employees and different-company customers, including special handling for null companies. Those assertions must be
  replaced or explicitly superseded before the new behavior can be considered tested.
- `tests/Frontend/OrderCreate.spec.ts` currently mocks one unscoped customer and employee response and verifies the
  existing no-selector form.
- `tests/Browser/OrderIntakeTest.php` and `tests/Browser/OrderIntakeAndReviewTest.php` currently use an Employee with a
  company and a Customer with `company_id = null`; their fixtures will need same-company participants for the new
  contract.
- `docs/USER_ADMINISTRATION_PLAN.md` already treats a nullable company as no administration scope for an Administrator
  and uses dedicated scoped queries/resources. Reuse that security direction, but do not overload its administration
  query for order-intake participants.

## Decisions required before implementation

These points are not defined by [`Business_Rules.md`](Business_Rules.md) or by the current order code. Do not silently
choose a product rule in an implementation chat.

1. **Meaning of “employees.”** The current endpoint means “active non-customer staff,” while the model has an explicit
   `UserRole::EMPLOYEE` scope. Recommended default: show only active users with the `Employee` role. If Administrators
   or Super Administrators may also be assigned to orders, confirm that the field is really a “staff/assignee” field and
   keep those roles intentionally.
2. **Company eligibility.** The current `Company` model has `is_active`, but the order rules do not say whether a Super
   Administrator may select inactive companies. Recommended default: list active companies only and list active users
   only. If inactive companies must remain selectable for historical/backlog intake, document that exception.
3. **No-company actors.** Recommended default: an Administrator or Employee with `company_id = null` sees no
   participants and cannot use null as a tenant. Confirm whether the product instead wants a blocked create page with a
   configuration error.
4. **API compatibility.** No in-repository caller other than `useOrderApi` and its tests was found for the two lookup
   routes, but external consumers are unknown. Confirm whether changing their semantics is safe, or whether new
   versioned order-participant routes are required.
5. **Persistence of company selection.** Recommended default: submit `company_id` as a temporary validation context for
   Super Administrators, then remove it before `Order::create()`. Confirm separately if the company must be stored on
   the order for reporting or audit.

The first three decisions block the red-test contract. The persistence decision blocks any schema work, but not the
minimal lookup-only implementation.

## Recommended small architecture

Keep the existing order page, order-create API, and named employee/customer routes. Add one explicit company-options
contract and make all participant queries resolve an effective company through one server-side rule:

1. `GET /api/v1/users/companies` — Super Administrator only; return the approved company options as
   `{ id, uuid, name }`.
2. `GET /api/v1/users/employees?company_id={id}` — for Super Administrators, require and authorize the selected company;
   for Administrators/Employees, derive the actor's company and do not allow a client-supplied override.
3. `GET /api/v1/users/customers?company_id={id}` — apply the same effective-company rule.
4. Protect all three lookup contracts with the same order-creation capability as the web page; authentication alone is
   insufficient.
5. Select only the fields needed by the form, order deterministically by last name, first name, and ID, and return a
   dedicated participant shape rather than broadening `UserResource`.
6. Use the same effective-company and role/active checks in the create request/service. A direct POST must receive the
   same answer as the lookup UI; option visibility must never be the only protection.

A combined participant endpoint could reduce two requests, and Inertia props could avoid a company-options request, but
neither is necessary for this narrow fix. Keeping the existing named lookup routes minimizes the change surface and lets
the current `useOrderApi` pattern continue to be used.

## Ordered implementation plan

### Group A — Contract and red specification (P0: security decisions before code)

#### Step 0 — Complete the product decision gate — **DONE**

- [x] Read `Business_Rules.md`, the existing Step 1 refinement plan, the user-administration plan, current routes,
  controllers, request, service, models, resources, Vue page, and related tests.
- [x] Confirm that the current defect is in participant scoping and selection, not in the order lifecycle or history.
- [x] Record the five unresolved decisions above rather than inventing answers.
- [x] Record the pre-implementation boundary; implementation authorization was subsequently provided by the user.

Completion gate: current behavior, source locations, unknowns, scope, and dependencies are written down. This gate is
complete for this planning turn.

#### Step 1 — Lock the accepted participant contract — **DONE — P0**

- [x] Record the meaning of “employee,” active-company behavior, null-company behavior, API compatibility, and whether
  the selected company is lookup-only in the implementation decisions above.
- [x] Record the final role matrix and error behavior for missing, invalid, or unauthorized company context in the
  backend Feature tests and Form Requests.
- [x] Super Administrators start with a blank required selector.

Completion gate: no later test or implementation step depends on an unrecorded role/company assumption.

#### Step 2 — Write/update the red tests first — **DONE — P0**

Production changes were held until the new contracts were expressed in tests and the first focused frontend run showed
the expected missing-behavior failures. PHPUnit classes were used for the new and modified PHP tests; no unrelated tests
were removed.

- [x] Replace the old `UsersControllerTest` expectations that asserted cross-company customers and null-company
  grouping.
- [x] Add backend coverage for:
    - Super Administrator with `company_id = null` receiving authorized company options.
    - A selected company returning only its active, role-correct employees and customers.
    - A Super Administrator receiving no participants before selecting a company.
    - Administrator and Employee receiving only their own company's active participants.
    - Administrator/Employee with no company receiving no null-company tenant data.
    - Cross-company, inactive, soft-deleted, wrong-role, malformed, and forged IDs being rejected.
    - Unauthenticated and unauthorized access to every lookup contract.
    - A valid order create for Super Administrator, Administrator, and Employee without changing lifecycle/history.
    - A direct POST that mixes a customer and assignee from different companies failing before any order rows are
      written.
- [x] Add frontend tests for the selector's position/visibility, deferred Super Administrator loading, initial
  Administrator/Employee loading, selection reset, empty/error states, and stale-response protection.
- [x] Update browser fixtures and add browser journeys for Super Administrator company switching and
  Administrator/Employee initial scoping, while retaining the existing order-intake lifecycle assertions.

Completion gate: the focused red run fails only because the requested participant behavior is absent; setup failures,
database migration failures, or browser/Vite failures are recorded separately.

### Group B — Server-side scope and write protection (P1: one authority for all callers)

#### Step 3 — Implement the effective-company lookup contract — **DONE — P1**

- [x] Add the Super Administrator company-options route and API contract.
- [x] Replace the current opposite-company customer query with an explicit same-company query.
- [x] Enforce the exact `Employee` role; no broad non-customer assignee query remains.
- [x] Treat a null effective company as no scope for non-Super actors; no shared `whereNull(company_id)` company match
  is used.
- [x] Use active-user filtering, active-company filtering, explicit selected columns, and deterministic ordering.
- [x] Keep the participant response redacted to option data only; email, phone, notes, preferences, and password-related
  attributes are not exposed.
- [x] Centralize the effective-company/participant constraints in `OrderParticipantQuery`, reused by lookups and order
  creation. No tenancy package, global User scope, or unrelated abstraction was introduced.

Completion gate: each actor receives only the approved participant list, and a caller cannot widen it by changing a
query parameter.

#### Step 4 — Defend the order-create write path — **DONE — P1**

- [x] Extend the active `StoreOrderWithItemsRequest` contract, not the unused legacy request, with the confirmed company
  context and role/company/active validation.
- [x] For Super Administrators, require a selected company context and require both IDs to belong to it.
- [x] For Administrators/Employees, derive the company from the authenticated actor and prohibit an override.
- [x] Validate all related IDs before the transaction starts; the final write path does not rely on the form's option
  lists.
- [x] Recheck the participant set in the service or shared scope boundary so a direct caller cannot bypass request-level
  filtering. Preserve the existing transaction and `created_by`/`updated_by` behavior.
- [x] Remove the transport-only company context before persistence. Selecting a company does not write a new
  order-history row.

Completion gate: valid orders still transition to `Awaiting Review` with the existing notifications/history, while
invalid or cross-company payloads fail atomically.

### Group C — Inertia/Vue Step 1 experience (P1: visible behavior after the API contract is stable)

#### Step 5 — Add typed client contracts and role-aware loading — **DONE — P1**

- [x] Use the existing server-shared authenticated user role, typed through `AppPageProps`, for presentation only; API
  authorization and order validation remain server-side.
- [x] Extend `useOrderApi` and the order types for company options and scoped participant requests.
- [x] Keep catalog loading independent from participant loading so a participant failure is reported accurately.
- [x] On Super Administrator pages, render the required company selector above all other Step 1 fields and keep the
  participant selects empty/disabled until a company is selected.
- [x] On Administrator/Employee pages, omit the selector and load the actor-derived company participants immediately.
- [x] Keep the existing order fields, item/component behavior, submission, validation preservation, and lifecycle
  payload unchanged except for the temporary company context required by the confirmed server contract.

Completion gate: the UI exposes the exact role-specific controls and cannot present stale or unscoped options.

#### Step 6 — Handle switching, accessibility, and failure states — **DONE — P1**

- [x] Clear `customer_id` and `assigned_to` when the company changes.
- [x] Use request identity plus guards in both success and error paths so an older company response cannot replace a
  newer one.
- [x] Disable the company-dependent selects while loading and provide localized loading, empty, and error messages.
- [x] Keep labels, `aria-invalid`, `aria-describedby`, and Dusk selectors consistent with the existing create form.
- [x] Add only the localization entries needed for the company selector and no-company/empty-participant state.

Completion gate: a user can switch companies without submitting an old participant, and failure preserves safe form
values while clearly identifying the failed lookup.

### Group D — Integrated verification and handoff (P2: prove the complete contract)

#### Step 7 — Run focused and browser verification — **P2 — DEPENDS ON STEPS 2–6**

- [ ] Run the affected PHP Feature tests serially through Sail, including the participant lookup and order-create tests.
- [ ] Run the focused Vitest file, then the broader frontend unit suite if the focused tests pass.
- [ ] Run Pint for modified PHP, targeted ESLint/Prettier/type checks for modified frontend files, and
  `git diff --check`.
- [ ] Build the frontend or start the required Vite service before Dusk; the browser suite must not depend on an
  unavailable `localhost:5173` dev server inside the Dusk container.
- [ ] Run the updated Dusk order-intake journeys against the dedicated Dusk service.
- [ ] Only after focused and browser checks pass, offer the full PHP suite; do not claim full-suite success from focused
  results.

Completion gate:

- [ ] Super Administrators can select a company and see only that company's valid participants.
- [ ] Administrators and Employees see only their actor-derived company participants from the first render.
- [ ] Direct requests cannot cross the effective company boundary.
- [ ] Existing order creation, `Awaiting Review`, notification, and history behavior remains intact.
- [ ] No historical order record is rewritten and no unrelated repository is changed.
- [ ] Exact commands and results are recorded before this step is marked done.

## Expected implementation files

Likely files, subject to the decisions above and sibling-file conventions:

| Area                   | Files to inspect/change during implementation                                                                                  |
|------------------------|--------------------------------------------------------------------------------------------------------------------------------|
| Routes/API             | `routes/api.php`, possibly a small order-participant Form Request                                                              |
| Lookup authority       | `app/Http/Controllers/UsersController.php` and/or a small participant query/service; do not overload `UserAdministrationQuery` |
| Response shape         | A dedicated participant resource or explicit minimal option payload; avoid broadening `UserResource`                           |
| Order write validation | `app/Http/Requests/StoreOrderWithItemsRequest.php`, and the smallest shared service boundary needed for defense in depth       |
| Order page props       | `app/Http/Controllers/OrderPageController.php` if the UI needs server-controlled role/company capability props                 |
| Frontend API/types     | `resources/js/composables/useOrderApi.ts`, `resources/js/types/orders.ts`                                                      |
| Frontend page          | `resources/js/pages/Orders/Create.vue`                                                                                         |
| Localization           | Existing backend/frontend locale catalogs, only where new messages are required                                                |
| PHP tests              | Existing lookup/order Feature coverage plus focused PHPUnit classes where needed                                               |
| Frontend/browser tests | `tests/Frontend/OrderCreate.spec.ts`, `tests/Browser/OrderIntakeTest.php`, and related intake helpers                          |

Do not change `lumasachi-react-native`, order-history schema, payment ledger behavior, or unrelated user-administration
features.

## Verification record

### Sail/environment recovery

The first preflight failed before Laravel ran:

```text
vendor/bin/sail artisan list
Docker or Podman is not running.
```

After permission was granted to start Docker Desktop, `docker desktop start` succeeded, `vendor/bin/sail up -d`
started the Laravel, PostgreSQL, Dusk, Selenium, Redis, Memcached, and Mailpit services, and the repeated
`vendor/bin/sail artisan list` confirmed Laravel `12.64.0`.

During implementation, two database-heavy Feature commands were accidentally started concurrently. PostgreSQL migration
setup then reported collisions such as missing `migrations` and duplicate `jobs`, `telescope_entries`, and
`companies` objects. The commands were rerun serially and passed. Database-heavy Sail tests must remain serial.

The first targeted `vendor/bin/sail php ...phpstan` retry also reported that Docker was unavailable while the normal
Sail Artisan/Composer commands still worked. Restarting Docker Desktop and retrying `vendor/bin/sail composer run
test:types` restored the analyzer run. This was recorded as an environment issue, not a code result.

### Planning-turn baselines

These are baselines for the current behavior, not proof of the requested behavior:

```text
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/UsersControllerTest.php
5 passed (33 assertions)

vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderControllerTest.php
19 passed (107 assertions)

vendor/bin/sail yarn run test:unit tests/Frontend/OrderCreate.spec.ts
1 file passed; 7 tests passed
```

The first suite passed because it asserted the old cross-company-customer contract; it was replaced before the final
implementation verification.

### Implementation verification

The accepted contract was implemented through the following groups:

- Group A: PHPUnit and frontend contracts were written first; the initial frontend run was 9 passed and 4 expected red
  tests before production behavior existed.
- Group B: `OrderParticipantQuery` now owns effective-company resolution for company options, participant lookups, and
  direct order-write checks. `StoreOrderWithItemsRequest` validates before persistence, and the service rechecks the
  same boundary.
- Group C: `Create.vue` now has the Super Administrator selector above Step 1, actor-derived initial loading for
  Administrator/Employee, selection reset, stale-response guards, localized states, and Dusk selectors.
- Group D: this record is the final handoff ledger; the participant-scoping requirements are green, but the unrelated
  repository-wide checks listed below prevent marking Step 7 fully complete.
- The final review found two existing valid-order fixtures that did not satisfy the new active same-company boundary;
  both were corrected and their suites were rerun serially.

Serial Sail PHP tests:

```text
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/UsersControllerTest.php
7 passed (67 assertions)

vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderCreateParticipantScopingTest.php
10 passed (37 assertions)

vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php
34 passed (236 assertions)

vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderControllerTest.php
19 passed (107 assertions)

vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
26 passed (131 assertions)

vendor/bin/sail artisan test --compact tests/Unit/app/Services/OrderLifecycleServiceTest.php
24 passed (45 assertions)
```

Frontend and formatting checks:

```text
vendor/bin/sail yarn run test:unit tests/Frontend/OrderCreate.spec.ts
13 passed (13 tests)

vendor/bin/sail yarn run test:unit tests/Frontend/i18n.spec.ts
10 passed (10 tests)

vendor/bin/sail yarn vue-tsc --noEmit
passed

vendor/bin/sail yarn eslint [modified frontend files]
passed

vendor/bin/sail yarn prettier --check [modified frontend files]
passed

vendor/bin/sail bin pint --dirty --format agent
passed

git diff --check
passed

vendor/bin/sail yarn run build
passed; production manifest generated
```

The broader `vendor/bin/sail yarn run test:unit` command reached 106/107 tests, but
`tests/Frontend/i18n.spec.ts` exceeded its fixed 5-second timeout under full-suite load. The same file passed alone
10/10 immediately afterward; no localization assertion failed.

Static analysis:

```text
vendor/bin/sail composer run test:types
failed with 56 existing errors outside the participant-scoping changes
```

The remaining errors are in existing authentication, user-administration, resource, order-model, payment, state-machine,
notification, and query files. The changed participant request/query/controller/resource files and the changed lifecycle
service have no PHPStan diagnostics in that run.

Browser checks, run after building assets, stopping the stale Vite process that recreated `public/hot`, and reloading
both Octane workers:

```text
vendor/bin/sail artisan dusk tests/Browser/OrderIntakeTest.php
4 passed (27 assertions)
```

That file covers Super Administrator company switching, Administrator first-render loading, and existing Employee order
intake. `OrderIntakeAndReviewTest.php` had 1 passing measurement-error journey and 1 failure in the existing
financial-summary assertion because `3,760.00` was not visible; the participant-loading and order-intake parts
completed. That price-presentation assertion is outside this task and was not changed.

The initial browser failures were environment/harness failures: the Dusk browser received
`ERR_CONNECTION_REFUSED` for `http://localhost:5173` because a stale `public/hot` marker pointed at a Vite process in a
different container. After using the built manifest and reloading Octane, the participant browser journeys passed.

The full PHP suite was not claimed because the full PHPStan baseline is already failing and the browser review file has
the unrelated financial-summary assertion failure.

## Progress table

| Step                                   | Status   | Evidence                                                                                             |
|----------------------------------------|----------|------------------------------------------------------------------------------------------------------|
| 0 — Current-code/business-rule audit   | Complete | Source audit, business-rule boundary, scope, and unknowns recorded above                             |
| 1 — Accepted participant contract      | Complete | Least-privilege defaults are recorded and covered by tests                                           |
| 2 — Red tests first                    | Complete | Backend/frontend contracts were written first; final focused tests are green                         |
| 3 — Scoped lookup contract             | Complete | Company options, exact roles, active filtering, redaction, and authorization pass                    |
| 4 — Server-side order validation       | Complete | Direct cross-company, malformed, inactive, wrong-role, and forged writes fail atomically             |
| 5 — Typed role-aware UI                | Complete | Super selector and Administrator/Employee initial loading are implemented and tested                 |
| 6 — Switching/accessibility states     | Complete | Reset, stale-response, disabled/loading/error, localization, and Dusk coverage pass                  |
| 7 — Focused/browser/final verification | Partial  | Participant checks pass; full PHPStan has 56 unrelated errors and one unrelated Dusk assertion fails |
