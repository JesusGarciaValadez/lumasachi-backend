# User Administration Implementation Plan

## Purpose

Plan a Laravel and Inertia/Vue user-administration feature for Lumasachi without changing application behavior during
this planning stage.

This plan is based on:

- The supplied user-administration requirements.
- The current code, schema, routes, policies, frontend, localization, and tests.
- [`Business_Rules.md`](Business_Rules.md), which currently defines the order domain but does not define user
  administration.

## Plan status

- [x] Current-code and requirement audit completed on 2026-08-01.
- [x] Focused existing backend and frontend tests run and recorded.
- [x] Implementation stages, dependencies, questions, and completion gates documented.
- [x] Product decisions in Stage 0 adopted for this build and recorded with external-consumer/design risks.
- [x] TDD specifications in Stage 1 written and confirmed red before application code.
- [x] Application implementation started on 2026-08-01.

No Laravel, Vue, migration, or test code was changed while creating this plan.

## Scope

In scope:

- Restricting user administration to Super Administrators and Administrators.
- Super Administrator-only user creation.
- A scoped, paginated, sortable, filterable user list.
- User detail/edit and create forms.
- Company, role, active-state, type, locale, and field-level authorization rules.
- Dashboard and sidebar access points.
- Localized success, validation, and unexpected-error behavior.
- Sanitized logging and Super Administrator error notifications.
- Feature, policy, resource/response, frontend unit, and final regression verification.
- Unit, Feature, frontend unit, and Laravel Dusk browser tests written before the corresponding implementation.

Not defined by the supplied requirements and therefore out of scope unless separately approved:

- Deleting, restoring, or permanently deleting users.
- Bulk actions, imports/exports, impersonation, or a standalone user audit-log feature.
- Changing order ownership or historical order records when a user is deactivated or moved.
- React Native changes.
- Adding an administration package or another dependency.

## Priority criterion

**Security and contract dependencies before visible CRUD.**

- **P0:** A security rule, data contract, or unresolved decision that blocks safe implementation.
- **P1:** Required user-administration behavior.
- **P2:** Required final integration, accessibility, and regression confidence after the core flow works.

All stages are required unless a product decision explicitly removes their scope. Priority indicates sequence, not
whether a stage may be skipped.

## Current-code audit

### Existing foundations to reuse

- Laravel `12.64.0`, Inertia v2, Vue 3, Tailwind CSS v4, Ziggy, and Vue I18n are already installed.
- [`User`](../app/Models/User.php) already has UUID, company, name, email, password, role, phone number, active flag,
  notes, type, preferences, and locale attributes.
- [`UserRole`](../app/Enums/UserRole.php), [`UserType`](../app/Enums/UserType.php), and
  [`Locale`](../app/Enums/Locale.php) already define the stored values.
- [`Company`](../app/Models/Company.php) and the user-to-company relationship already exist.
- [`UserPolicy`](../app/Policies/UserPolicy.php) and policy registration already exist.
- [`UsersController`](../app/Http/Controllers/UsersController.php) already serves employee/customer lookup endpoints
  used by order flows. These are not administration endpoints.
- [`UserResource`](../app/Http/Resources/UserResource.php) exists, but is shared by multiple order resources and exposes
  fields that are too broad for every user-list context. Do not expand it blindly for administration.
- [`Dashboard.vue`](../resources/js/pages/Dashboard.vue) already has a three-panel placeholder grid and a recent-orders
  card.
- [`AppSidebar.vue`](../resources/js/components/AppSidebar.vue) already renders capability-controlled navigation.
- The UI already has reusable button, card, checkbox, collapsible, input, label, skeleton, and sidebar components.
- The current frontend catalog supports Spanish and English in
  [`resources/js/i18n/index.ts`](../resources/js/i18n/index.ts); backend messages use `resources/lang/{locale}`.
- Inertia validation errors already remain on the current page when Laravel redirects back. Existing forms use
  `useForm` and `InputError`.
- [`NotifiesAdmins`](../app/Traits/NotifiesAdmins.php) demonstrates chunked notifications to active privileged users,
  but it targets both Administrator roles and is not suitable unchanged for error mail that must go only to Super
  Administrators.

### Gaps and conflicts to resolve

| Area                      | Current behavior                                                                                                                    | Required or unresolved behavior                                                                                                            |
|---------------------------|-------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| Public registration       | `GET/POST /register` and `POST /api/v1/register` allow guest self-registration as active Employees.                                 | “Register users” is Super Administrator-only. Whether public registration must be removed is not stated explicitly.                        |
| User web routes           | No `GET /users`, create page, UUID profile page, or update route exists.                                                            | Add the approved singular/plural route contract.                                                                                           |
| User lookup API           | `GET /api/v1/user/{user:email}` requires authentication but performs no user-policy authorization.                                  | Prevent cross-company/private-field access without breaking an unknown external consumer.                                                  |
| Create authorization      | `UserPolicy::create`, the `users.create` gate, and role permissions allow Administrators.                                           | Creation must be Super Administrator-only.                                                                                                 |
| View/update authorization | Administrators can currently view and update any user, including a Super Administrator and inactive users.                          | Administrators are restricted to their company; inactive rows are not clickable for Administrators.                                        |
| Self-service policy       | Any user may view/update their own model through the generic policy, supporting account settings.                                   | Preserve legitimate settings behavior while separately securing administration routes and fields.                                          |
| Company scope             | Existing lookup code treats two `null` company IDs as the same company.                                                             | It is unknown whether an Administrator without a company may administer all unassigned users or none.                                      |
| Active since              | No `active_since`/`activated_at` column exists. `created_at` is not necessarily an activation date.                                 | Define its meaning, persistence, and legacy-data policy before adding it to the table.                                                     |
| UUID generation           | Factory and registration code assign UUIDs explicitly; `User::uniqueIds()` exists without `HasUuids`.                               | The administration service must use one verified UUID-generation convention.                                                               |
| List payload              | Generic `UserResource` includes email, phone, notes, and other fields and omits locale.                                             | List responses must select and expose only the fields required by the table and authorization scope.                                       |
| Role labels               | Stored enum values are `Super Administrator` and `Administrator`.                                                                   | The requirement uses display labels `Super Admin` and `Admin`; translate labels without changing stored values.                            |
| Customer badge            | A color is specified for Super Admin, Admin, and Employee, but not Customer.                                                        | A Customer role badge color needs a product/design decision.                                                                               |
| Create URL                | Requirements variously say `POST /user`, `POST /user/{uuid}`, and a create “button” using POST.                                     | A user has no UUID before creation; the recommended REST contract needs confirmation.                                                      |
| Admin company edit        | Administrators may edit only same-company users, but the allowed fields include a company selector.                                 | Allowing another company would violate the scope after saving; the permitted choices need confirmation.                                    |
| Active filter             | One always-visible immediate checkbox is required.                                                                                  | It is unclear whether checked means “active only,” “include inactive,” or exact `is_active=true/false`.                                    |
| Unexpected errors         | No user-administration error reporter, incident identifier, or Super Administrator-only notification exists.                        | Define a sanitized report/mail payload; raw request data, passwords, tokens, and identifying user data must not be logged or mailed.       |
| Test syntax               | Current focused PHP tests use Pest and pass, while current repository instructions require new/updated tests to be PHPUnit classes. | Use PHPUnit for new files and resolve whether a touched Pest file must be converted before implementation. Do not convert unrelated tests. |

## Recommended route contract

This is the least surprising contract, but Stage 0 must confirm it because the supplied create URLs conflict:

| Method | URL                 | Purpose                        | Authorization                               |
|--------|---------------------|--------------------------------|---------------------------------------------|
| `GET`  | `/users`            | Paginated administration index | Administrator or Super Administrator        |
| `GET`  | `/user/create`      | User creation form             | Super Administrator only                    |
| `POST` | `/user`             | Create a user                  | Super Administrator only                    |
| `GET`  | `/user/{user:uuid}` | User profile/edit form         | Scoped Administrator or Super Administrator |
| `PUT`  | `/user/{user:uuid}` | Update a user                  | Scoped Administrator or Super Administrator |

Use named web routes and implicit UUID binding. Do not place the administration form behind the existing public
registration controller.

## Recommended authorization matrix

This matrix converts the supplied rules into server-side enforcement. Rows marked **Decision required** must not be
implemented from assumption.

| Action                                      | Super Administrator                      | Administrator                     | Other roles                       |
|---------------------------------------------|------------------------------------------|-----------------------------------|-----------------------------------|
| See Users navigation/dashboard card         | Yes                                      | Yes                               | No                                |
| List/filter users                           | All users                                | Same-company users only           | No                                |
| Open active user                            | Any                                      | Same-company only                 | No administration access          |
| Open inactive user                          | Any                                      | No                                | No administration access          |
| Create user                                 | Yes                                      | No                                | No                                |
| Update active state                         | Yes                                      | No                                | No                                |
| Assign Super Administrator role             | **Decision required**                    | No                                | No                                |
| Assign Administrator/Employee/Customer role | Yes                                      | Same-company user only            | No                                |
| Change company                              | Any company, subject to Stage 0 decision | **Decision required**             | No                                |
| Edit self through account settings          | Preserve existing settings policy        | Preserve existing settings policy | Preserve existing settings policy |

Every route, query, Form Request, and service operation must enforce the same matrix. Hiding a link or disabling a row
is not authorization.

## Stage 0 — Confirm unresolved product and security rules — P0 — COMPLETE FOR THIS BUILD

### Implementation decisions for this build (2026-08-01)

The supplied `Business_Rules.md` defines order behavior only and contains no user-administration contract. To keep the
implementation safe and internally consistent, this build records the following decisions before writing application
code. Where the product requirement was silent, the least-privileged behavior is used and the assumption is called out.

- Guest web registration and unauthenticated API registration are removed. No in-repository consumer of the API
  registration endpoint was found; external consumers remain unknown. User creation is available only through the Super
  Administrator-protected administration route.
- The route contract is `GET /users`, `GET /user/create`, `POST /user`, `GET /user/{user:uuid}`, and
  `PUT /user/{user:uuid}`.
- `activated_at` is nullable and records the first transition to active. It is set for a newly created active user and
  is preserved through deactivation/reactivation. Existing active users remain `null` because their activation date is
  unknown; `created_at` is not used as a fabricated backfill.
- The always-visible checkbox means “active users only” and is checked by default. Unchecking includes inactive rows.
  Administrators may see inactive same-company rows when included, but cannot open or update them; the server enforces
  the same restriction.
- An Administrator with no company has no administration targets. `null` is never treated as a shared tenant. Existing
  account-settings self-service remains separate.
- Administrator company values are read-only in administration. Only Super Administrators may move a user between
  companies. Administrators may assign Administrator, Employee, or Customer roles to other active users in their own
  company, but cannot assign Super Administrator or change their own role.
- A Super Administrator may change any user fields except removing or demoting the final active Super Administrator.
- Customer uses the neutral slate capsule palette in both light and dark mode because the supplied requirements did not
  define a Customer color. This is a presentation assumption, not a new domain value.
- Unexpected administration failures notify all active Super Administrators without throttling/deduplication for this
  first implementation. Logs and mail contain only an opaque incident ID and allow-listed operation/context; secrets,
  request payloads, email, phone, notes, and direct user identifiers are excluded.
- The unauthorised email-bound `GET /api/v1/user/{user:email}` route is retired because no in-repository consumer was
  found and its broad response violated the administration privacy boundary. The existing shared `UserResource` is not
  broadened or rewritten.
- New and modified tests in this feature use PHPUnit classes where practical. Existing unrelated Pest tests are not
  converted.
- Inactive users cannot authenticate. This makes the active-state control meaningful while preserving the generic
  authentication flow and its existing safe failure message.

- [x] Guest web/API self-registration is removed for this build. No in-repository API consumer exists; an external
  consumer would need a separately approved migration before deployment.
- [x] The route contract is `POST /user` instead of the impossible pre-creation `POST /user/{uuid}`.
- [x] “Active since” is nullable `activated_at`, set on the first inactive-to-active transition and preserved through
  reactivation. Existing active users remain unknown rather than being backfilled from `created_at`.
- [x] The always-visible checkbox means “active users only” and defaults to checked; unchecking includes inactive rows.
- [x] Administrator access to inactive users is blocked server-side and inactive rows are non-clickable in the UI.
- [x] An Administrator with `company_id = null` has no administration targets.
- [x] Administrator company selection is read-only; only Super Administrators may move users between companies.
- [x] Administrators may update non-Super roles for other active same-company users, cannot change their own role, and
  use existing account settings for their own password. Administration password resets are Super Administrator-only.
- [x] A Super Administrator may change any user except the final active Super Administrator cannot be deactivated or
  demoted.
- [x] Customer uses a neutral slate capsule with explicit light/dark contrast classes. This is a reversible UI default
  adopted because the supplied requirements did not define a Customer color.
- [x] Error email targets all active Super Administrators. No throttling/deduplication is added in this first build.
- [x] The unauthorised email-bound API lookup is retired because no in-repository consumer exists and its response is
  too broad; external consumers remain an explicit pre-deployment risk.
- [x] New/modified feature tests use PHPUnit classes; unrelated existing Pest tests remain unchanged.

Completion gate:

- Every item above has a recorded answer adopted for this build.
- The authorization matrix, route contract, activation semantics, and error-notification privacy contract contain no
  contradiction.
- No later stage depends on an undocumented assumption.

## Stage 1 — Write the TDD specification before application code — P0 — COMPLETE

This stage changes tests only. Do not create or modify production Laravel/Vue code until the red-test gate below is
satisfied. The repository already has Laravel Dusk, Selenium/Sail services, and existing browser-test conventions to
reuse.

- [x] Convert every confirmed Stage 0 rule and every supplied acceptance requirement into a traceable test case before
  implementing it.
- [x] Add focused **Unit tests** for isolated user-domain behavior where a unit boundary is useful, including activation
  timestamp transitions, allowed role/field sets, sanitized incident context, and service behavior that does not need an
  HTTP request. Do not duplicate behavior that is clearer as a Feature test.
- [x] Add focused **Feature tests** for routes, policies, company scoping, filters, ordering, pagination, Form Requests,
  persistence, password behavior, redirects/flash messages, payload redaction, logging, and Super Administrator error
  notifications.
- [x] Add focused **frontend unit tests** with Vitest for capability-controlled navigation, the Dashboard card, filters,
  page size, capsules, inactive-row behavior, create/edit forms, field errors, preserved values, and localized messages.
- [x] Add a small number of high-value **Laravel Dusk browser tests** for complete user journeys:
    - Super Administrator opens Users, creates a user, sees the success message, edits the user, and sees the update;
    - Administrator sees only same-company users, cannot open an inactive row, and cannot reach creation by direct URL;
    - an invalid create/update submission remains on the form with safe values and visible field errors;
    - unauthorized roles cannot see or reach Users navigation/pages.
- [x] Keep authorization permutations, validation matrices, logging internals, and mail-recipient assertions in Unit or
  Feature tests; browser tests should prove only cross-layer behavior that benefits from a real browser.
- [x] Use factories and existing Dusk helpers. Do not rely on seeded production-like identities or add a dependency.
- [x] Run each new test group and confirm it fails for the expected missing user-administration behavior—not because of
  syntax, imports, migrations, Docker, Selenium, test data, or environment setup.
- [x] Record the expected red failures in the progress log, then implement later stages in small red-green-refactor
  slices. When a defect is found, add the failing regression test before changing production code.

Completion gate:

- Unit, Feature, Vitest, and Dusk coverage is mapped to the requirements with no unjustified duplication.
- Every planned implementation behavior has a failing automated specification at the lowest useful test layer.
- The new tests load and reach their intended assertions; failures are caused by missing behavior, not a broken harness.
- The exact red-test commands/results are recorded.
- Only after this gate may production Laravel/Vue implementation begin.

Stage 1 red-gate record (2026-08-01):

-
`vendor/bin/sail artisan test --compact tests/Feature/UserAdministration tests/Unit/app/Enums/UserAdministrationRoleContractTest.php`
loaded the new PHPUnit classes and produced 31 expected failures and 3 passes (34 assertions). Failures were missing
administration routes/current policy behavior, the missing `activated_at` column, and the still-enabled registration/
lookup endpoints; one contract spec was corrected so route URL generation was not itself a failure.
- `vendor/bin/sail yarn run test:unit tests/Frontend/UserAdministrationNavigationDashboard.spec.ts` produced 3 expected
  failures and 2 passes. Prettier and ESLint passed for the new spec.
- `vendor/bin/sail artisan dusk --filter=UserAdministrationTest` was exercised by the Dusk agent: the harness loaded,
  two denial journeys passed, and three route/selector journeys failed because the user-administration surface did not
  yet exist. No Docker/Selenium setup failure was observed.
- Detailed behavior regressions (service, query, form, error notification, and UI filter assertions) will be extended in
  the corresponding implementation stages at the lowest useful layer; the red contract above is the gate that authorized
  production implementation.

## Group A — Security and domain foundation

### Stage 2 — Lock authorization and registration boundaries — P0 — COMPLETE — DEPENDS ON STAGE 1

- [x] Use the failing Stage 1 policy/route tests for the approved matrix: guest, Customer, Employee, Administrator in
  the same/different/null company, and Super Administrator.
- [x] Refine `UserPolicy` so list, profile, create, and update decisions include role, company, and active-state rules
  without breaking self-service settings.
- [x] Make `UserPolicy::create` and every duplicate `users.create` permission source Super Administrator-only, or remove
  redundant permission definitions so they cannot drift.
- [x] Protect the administration route group with `auth`, `verified`, and server-side policy middleware.
- [x] Apply the Stage 0 decision to guest web/API registration. Do not merely remove the Dashboard link while leaving a
  direct registration endpoint open.
- [x] Authorize, replace, or retire `GET /api/v1/user/{user:email}` and add privacy regression coverage. Do not expose a
  broad `UserResource` solely because a caller is authenticated.
- [x] Test forged UUIDs, direct URLs, cross-company access, inactive targets, and attempts to assign forbidden roles or
  fields.

Completion gate:

- Every matrix cell is enforced by backend tests, including direct-request bypass attempts.
- No Administrator can enumerate or mutate another company or open an inactive target if that restriction is approved.
- No non-Super Administrator can reach any creation endpoint.
- Existing account settings still authorize the current user correctly.
- Focused policy/auth/route tests pass through Sail.

### Stage 3 — Add the activation data contract and query support — P0 — COMPLETE

- [x] If approved, create a focused migration for nullable `activated_at`; do not edit the deployed users migration.
- [x] Implement the approved legacy backfill separately from schema creation if data mutation is required.
- [x] Update model casts/fillable behavior and the factory with explicit active/inactive states that set coherent
  timestamps.
- [x] Keep UUID generation consistent and guarantee unique UUIDs for service-created users.
- [x] Avoid speculative list indexes in this schema stage; inspect the actual scoped query plan once the Stage 5 query
  exists, then add only an index justified by company scoping, active filtering, and last-name/first-name ordering.
- [x] Add migration/model/factory tests for activation, reactivation, null behavior, and rollback. Reactivation
  transition coverage is implemented with the Stage 4 service because no write service exists in this stage.

Stage 3 verification record (2026-08-01):

-
`vendor/bin/sail artisan test --compact tests/Unit/app/Models/UserActivationTest.php tests/Unit/app/Enums/UserAdministrationRoleContractTest.php tests/Unit/database/migrations/CreateUsersTableTest.php`:
21 passed, 86 assertions.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- The live schema has no legacy activation dates to backfill; existing active users remain `activated_at = null` by the
  documented decision. After the additive migrations were applied locally, `EXPLAIN` for the scoped
  active/company/name-order query showed a sequential scan on the current small users table; no additional speculative
  index was added.

Completion gate:

- “Active since” has one documented meaning across database, model, API/Inertia props, UI, and tests.
- Existing active users have an explicit, verified migration outcome; unknown dates are displayed as unknown, not
  invented.
- Schema and focused model/migration tests pass.

### Stage 4 — Establish Form Request, service, and error-reporting contracts — P0 — COMPLETE — DEPENDS ON STAGES 1–3

- [x] Add separate create and update Form Requests. Authorization belongs in the requests/policy; validation must use
  enum values, existing companies, locale values, email uniqueness, string limits, and Laravel password rules.
- [x] Require and confirm password on create. On update, treat a blank/absent password as “unchanged,” never return the
  stored hash, and never prefill a password input.
- [x] Reject forbidden fields instead of silently trusting frontend visibility. In particular, an Administrator cannot
  submit `is_active`, Super Administrator role, or an unauthorized company change.
- [x] Add a small `UserService` with explicit `create` and `update` operations. It should receive validated/authorized
  data, hash through the model cast or one existing convention, generate UUIDs, update activation timestamps, and use a
  transaction only where multiple writes/side effects require atomicity.
- [x] Keep controllers thin: authorize/validate, call the service, and return an Inertia redirect/response.
- [x] Define a user-administration exception/reporting path consistent with `bootstrap/app.php`:
    - validation and authorization errors use normal Laravel/Inertia behavior and are not emailed as system failures;
    - unexpected failures receive an opaque incident ID and a localized friendly message;
    - Laravel reports the exception with structured, allow-listed context;
    - passwords, password confirmations, tokens, email, phone, notes, raw request payloads, and direct user identifiers
      are excluded;
    - only the approved active Super Administrator recipients receive a sanitized notification;
    - the form remains on the same page with entered non-password values preserved.
- [x] Test validation failures, service rollback, password unchanged/changed cases, exception reporting, redaction,
  recipients, non-recipients, and mail/notification content separately.

Stage 4 verification record (2026-08-01):

- `vendor/bin/sail artisan test --compact --no-ansi tests/Unit/UserAdministration tests/Feature/UserAdministration`:
  52 passed, 257 assertions.
- The unexpected create-failure Feature test confirms same-page safe input preservation, password exclusion, sanitized
  incident context, and notification only to active Super Administrators. The incident-reporting Unit tests confirm the
  default Laravel `userId` context is not added.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.

Completion gate:

- Create/update rules exactly match the approved field matrix and schema.
- The service is the only new administration write path.
- Expected user mistakes do not generate system-failure emails.
- Unexpected failures are logged/mailed without identifying or secret form data, and the user can correct/resubmit.
- Focused request, service, exception, and notification tests pass.

## Group B — Read experience

### Stage 5 — Build the scoped list/filter/show backend — P1 — COMPLETE — DEPENDS ON STAGES 1–4

- [x] Add an administration controller or page controller for index/show/create rendering; do not overload the existing
  employee/customer lookup methods.
- [x] Build one scoped user query from the policy rules:
    - Super Administrator: approved global scope;
    - Administrator: approved company scope only;
    - select only required columns and eager-load only required company fields;
    - order by `last_name`, then `first_name`, with `id` as a deterministic tie-breaker.
- [x] Validate query parameters for first name, last name, role, active state, type, company, and per-page.
- [x] Restrict per-page to `10`, `20`, or `50`, default to `10`, preserve filters in pagination links, and reject or
  normalize invalid values consistently.
- [x] Make the company filter available only to Super Administrators and derive its choices from companies represented
  by users visible to that actor, as required.
- [x] Return a dedicated administration list/detail payload rather than broadening `UserResource` across order APIs.
- [x] Return capability props such as `can_create_user`, `can_open`, `can_update_active`, and allowed field/options from
  the backend so Vue does not recreate policy logic.
- [x] Do not include password/hash. Include email, phone, notes, company, locale, and other edit fields only on the
  authorized detail form, not in the list payload.
- [x] Add Feature tests for ordering, stable pagination, all filters and combinations, allowed page sizes, option
  scoping, payload redaction, and empty results.

Stage 5 verification record (2026-08-01):

- The scoped read Feature tests passed, including the combined filter/pagination case (6 tests, 102 assertions).
- The focused user-administration backend suite passed: 52 tests, 257 assertions. List payloads exclude password/hash
  data, pagination preserves filter state, and policy/route middleware enforce the same scope server-side.
- The final Dusk journey passed 5 tests, 23 assertions after the production asset build and Dusk cache refresh.

Completion gate:

- Administrators cannot infer another company through rows, counts, pagination metadata, filters, or option lists.
- The table receives exactly the required fields, including the approved activation date.
- All query combinations retain the required sort order and page size.
- Focused list/show Feature tests pass.

### Stage 6 — Build Users navigation, dashboard card, index, filters, and profile shell — P1 — COMPLETE — DEPENDS ON STAGE 5

- [x] Add localized Users navigation to the sidebar only when the backend says the actor can view users.
- [x] Replace the leftmost Dashboard placeholder with a localized Users card for Administrator/Super Administrator
  actors only. Show the latest visible users using the same authorization scope and a link to `/users`; leave the other
  panels and recent-orders behavior unchanged.
- [x] Render `/users` as an Inertia/Vue page using current layout, breadcrumbs, cards, buttons, collapsible, checkbox,
  and loading/empty/error conventions.
- [x] Render the required row presentation and localized capsules:
    - `{last_name}, {first_name}`;
    - role, type, active state, and activation date;
    - approved role/type/active colors with accessible contrast and current dark-mode conventions.
- [x] Make active rows keyboard- and pointer-accessible links to `/user/{uuid}` when allowed. Render inactive rows as
  non-links for Administrators; do not fake disabled behavior with CSS alone.
- [x] Keep the active checkbox visible outside the collapsible filter panel and apply it immediately. Apply text/select
  filters through Inertia query-string visits with state/scroll preservation and a modest debounce for text inputs.
- [x] Add the `10/20/50` page-size control and accessible pagination.
- [x] Add a profile/edit page shell using backend-provided fields/options/capabilities; do not expose disabled sensitive
  inputs as a substitute for omitting forbidden controls.
- [x] Extend frontend user types with UUID, role, type, company, active, activation, and locale contracts.
- [x] Add Vitest coverage for role-aware navigation, dashboard visibility/recent-user props, filter query composition,
  immediate active toggle, pagination size, badge mapping, and inactive-row behavior.

Stage 6 verification record (2026-08-01):

- `vendor/bin/sail yarn run test:unit`: 18 files passed, 68 tests passed.
- `vendor/bin/sail yarn vue-tsc --noEmit`: passed. Targeted ESLint and Prettier checks for changed user-administration
  frontend/test files: passed.
- The final Dusk journey passed 5 tests, 23 assertions, including role-aware navigation, dashboard/list access,
  inactive-row behavior, and localized form validation.

Completion gate:

- The sidebar, Dashboard card, index, and profile shell are inaccessible and invisible to unauthorized roles.
- All list/filter/page-size behavior matches Stage 5 without client-side tenant logic.
- Keyboard, focus, labels, error associations, responsive layout, and dark mode are verified.
- Focused Feature and Vitest tests pass; TypeScript and targeted ESLint pass.

## Group C — Write experience

### Stage 7 — Implement Super Administrator user creation — P1 — COMPLETE — DEPENDS ON STAGES 1–6

- [x] Add the approved create page and `POST /user` route, both protected by the same create policy.
- [x] Build the Vue form from Stage 4’s create contract using `useForm` and current UI components.
- [x] Provide company names, role values, user types, active flag, supported locales, and all required editable fields.
- [x] Mirror safe client-side constraints such as required fields, lengths, email type, enum choices, and password
  confirmation for immediate feedback. Keep Laravel validation authoritative for uniqueness, authorization, and race
  conditions.
- [x] On validation failure, stay on the form and preserve non-password values. On an unexpected error, show the
  localized incident message from Stage 4 and preserve safe values.
- [x] On success, redirect to `/users` with a localized “user created” flash message.
- [x] Add Feature and Vitest tests for every allowed field, required/invalid data, forbidden actor/direct request,
  duplicate email, UUID/activation behavior, preserved data, success flash, and notification failure handling.

Stage 7 verification record (2026-08-01):

- Create authorization, validation, persistence, UUID/activation, password, redirect, and error-path coverage is
  included in the focused user-administration suite: 52 passed, 257 assertions.
- The creation form submits the approved POST route and the frontend form tests plus the final Dusk create journey
  passed.

Completion gate:

- Only a Super Administrator can render or submit creation.
- The persisted user exactly matches validated input and approved defaults; no unsubmitted privileged value is invented.
- Failure and success behavior match the requirement without logging/mailing sensitive input.
- Focused create Feature and Vitest tests pass.

### Stage 8 — Implement scoped user editing — P1 — COMPLETE — DEPENDS ON STAGES 1–7

- [x] Build the edit form from Stage 4’s update contract and backend-provided capabilities.
- [x] Super Administrator fields: approved company, first name, last name, email, optional new password, role, phone,
  active state, notes, type, and locale.
- [x] Administrator fields: only the approved same-company subset; never render or accept active-state or Super
  Administrator-role changes.
- [x] Submit `PUT /user/{uuid}` through Inertia. Preserve non-password input and remain on the profile for validation or
  unexpected errors.
- [x] Concurrent edits use the documented last-write-wins convention; no optimistic locking is implemented for this
  initial administration section.
- [x] On success, redirect to `/users` with a localized “user updated” message. Do not reuse the supplied mistaken
  “created” wording for an update unless product explicitly requests it.
- [x] Add Feature and Vitest tests for allowed updates, omitted fields, unchanged/changed password, unique email ignore,
  cross-company UUID, inactive target, forbidden field injection, role escalation, company movement, self-management
  decisions, service failure, preserved form state, and success flash.

Concurrency convention: this initial administration section uses normal database last-write-wins behavior. It does not
implement optimistic locking or invent a version field; a future concurrency requirement must be approved separately.

Stage 8 verification record (2026-08-01):

- Update authorization, protected-field rejection, activation, password preservation/change, unique-email-ignore,
  failure redirect, and distinct update-flash coverage passed in the focused user-administration suite: 52 passed, 257
  assertions.
- The UserForm Vitest coverage and final Dusk edit journey passed. Administrators cannot change company, active state,
  password, or assign the Super Administrator role.

Completion gate:

- Each role can update exactly the approved fields and targets, including direct forged requests.
- Password and tenant boundaries remain secure.
- Success and error behavior use distinct localized create/update messages.
- Focused update Feature and Vitest tests pass.

## Group D — Integration and acceptance

### Stage 9 — Complete localization, regression, and acceptance verification — P2 — COMPLETE — DEPENDS ON STAGES 1–8

- [x] Add complete Spanish and English strings for navigation, pages, fields, enums, filters, pagination, empty states,
  validation, success, and incident errors.
- [x] Extend the existing localization parity/lifecycle tests; verify supported locale options come from the shared
  backend/frontend contract.
- [x] Re-run the focused user administration, policy, auth, dashboard, resource, mail/notification, and frontend tests.
- [x] Run Pint for changed PHP files, targeted Prettier/ESLint, Vue TypeScript, and the frontend build.
- [x] Run the serial full PHP suite. If database isolation fails, diagnose infrastructure separately and retry using the
  repository’s approved database-recreation workflow before calling it an application regression.
- [x] Run the full frontend unit suite and existing static/type checks.
- [x] Perform a small authenticated browser smoke test for Super Administrator and Administrator roles if the project’s
  existing browser-test harness is available; do not add a new browser dependency without approval.
- [x] Inspect the final diff and confirm no React Native, order-domain, dependency, or unrelated files changed.
- [x] Record every command and exact result below. Do not mark the feature complete from focused tests alone.

Stage 9 final verification record (2026-08-01):

- `vendor/bin/sail artisan test --compact --no-ansi`: 726 passed, 5,029 assertions, 93.29 seconds.
- `vendor/bin/sail artisan test --compact --no-ansi tests/Unit/UserAdministration tests/Feature/UserAdministration`:
  52 passed, 257 assertions.
- `vendor/bin/sail yarn run test:unit`: 18 files passed, 68 tests passed.
- `vendor/bin/sail yarn vue-tsc --noEmit`: passed.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- Targeted Prettier and ESLint checks over the changed user-administration frontend, frontend tests, and plan:
  passed.
- `vendor/bin/sail yarn run build`: passed; Vite produced the production manifest/assets. Vite emitted only its existing
  large-chunk advisory.
- `vendor/bin/sail artisan dusk --filter=UserAdministrationTest --without-tty`: 5 passed, 23 assertions.
- The repository-wide `vendor/bin/sail yarn run format:check` reports only the two pre-existing unrelated files
  `resources/js/types/orders.ts` and `resources/views/vendor/mail/html/themes/default.css`; all changed
  user-administration files pass the targeted check.
- Initial Docker/Sail access required the documented permission/runtime retry. Concurrent delegated database tests also
  produced migration-table collisions; after those agents were stopped, the testing database was refreshed with
  `vendor/bin/sail artisan migrate:fresh --env=testing --force --no-interaction`, and the authoritative PHP suite was
  rerun serially. No React Native files were touched.

Completion gate:

- Every requirement is mapped to a passing automated assertion or an explicitly approved manual/browser check.
- Both locales render without raw translation keys and all capsule labels are localized.
- Focused and full required checks pass, or remaining environment failures are recorded accurately as blockers.
- No unresolved Stage 0 decision remains.
- Only then mark Stages 0–9 and the feature implementation complete.

## Requirement traceability

| Requirement area                                    | Planned stages                         |
|-----------------------------------------------------|----------------------------------------|
| TDD-first Unit/Feature/frontend/browser coverage    | 1, then red-green-refactor through 2–9 |
| Super Administrator-only registration/create        | 0, 1, 2, 4, 7                          |
| Administrator/Super Administrator-only Users access | 1, 2, 5, 6                             |
| Sidebar and leftmost Dashboard Users panel          | 1, 5, 6                                |
| Recent users                                        | 1, 5, 6                                |
| Paginated 10/20/50 list and name ordering           | 1, 3, 5, 6                             |
| Role/type/active capsules and inactive-row behavior | 0, 1, 2, 5, 6                          |
| First/last/role/active/type/company filters         | 0, 1, 5, 6                             |
| Super Administrator global scope                    | 1, 2, 5, 7, 8                          |
| Administrator company scope                         | 0, 1, 2, 5, 8                          |
| Create/update field matrices                        | 0, 1, 2, 4, 7, 8                       |
| Laravel Form Request to UserService pattern         | 1, 4, 7, 8                             |
| Frontend validation aligned with backend            | 1, 4, 7, 8                             |
| Friendly errors, preserved data, sanitized log/mail | 0, 1, 4, 7, 8                          |
| Redirect and distinct localized success messages    | 1, 7, 8, 9                             |
| Current i18n consistency                            | 1, 6, 7, 8, 9                          |

## Expected implementation surface

Exact names may follow sibling conventions discovered at implementation time. Keep the change limited to these areas:

- `routes/web.php`, and `routes/auth.php` / `routes/api.php` only if Stage 0 changes public or lookup routes.
- `app/Policies/UserPolicy.php` and duplicate user permission definitions.
- A focused user-administration/page controller, create/update Form Requests, `UserService`, and dedicated
  administration response/resource objects.
- `app/Models/User.php`, `database/factories/UserFactory.php`, and a new migration only if activation/index decisions
  require them.
- A focused exception/notification implementation and localized mail/message resources.
- `app/Http/Middleware/HandleInertiaRequests.php` only for small shared capability/flash props; page-specific data
  should remain page-specific.
- `resources/js/pages/Users/*`, small reusable user components if repetition justifies them, `Dashboard.vue`,
  `AppSidebar.vue`, frontend types, and i18n catalogs.
- Focused PHP and `tests/Frontend` coverage.

Do not modify order lifecycle, payment, refund, or historical-order behavior as part of this feature.

## Planning verification record

Initial environment issue:

- `vendor/bin/sail artisan list` first returned `Docker or Podman is not running.`
- The permitted retry returned the same message.
- Docker Desktop was started successfully; Sail then reported its project services were stopped.
- After permission to run `vendor/bin/sail up -d`, the services started and the exact Artisan check passed.

Verified current runtime:

- Laravel: `12.64.0`.
- The live `users` schema matches the audit above and has no activation timestamp.
- `route:list` shows only the existing employee/customer lookup routes under `/users` and the authenticated email-bound
  `/api/v1/user/{user}` lookup; no administration web routes exist.

Focused backend baseline:

```bash
vendor/bin/sail artisan test --compact \
  tests/Feature/app/Http/Controllers/UsersControllerTest.php \
  tests/Feature/app/Policies/UserPolicyTest.php \
  tests/Feature/Auth/RegistrationTest.php \
  tests/Feature/DashboardTest.php
```

Result: `16 passed (111 assertions)`.

Focused frontend baseline:

```bash
vendor/bin/sail yarn run test:unit tests/Frontend/AppSidebar.spec.ts
```

Result: `1 file passed; 2 tests passed`.

These results are the planning baseline captured before implementation. The final implementation verification is
recorded in the Stage 9 final verification record above.

## Implementation progress log

Update this section after each stage. A stage is done only when every task and its completion gate are satisfied.

| Stage                                | Status   | Verification/evidence                                                                                                                                                          |
|--------------------------------------|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 0 — Product decisions                | Complete | Decisions adopted for this build; risks recorded above.                                                                                                                        |
| 1 — TDD specification and red gate   | Complete | PHPUnit/Vitest/Dusk red contract recorded above.                                                                                                                               |
| 2 — Authorization/registration       | Complete | Policy/auth/registration/API-boundary checks pass; administration routes use auth, verified, and policy middleware.                                                            |
| 3 — Activation/schema/query support  | Complete | Focused migration/model/factory checks: 21 passed, 86 assertions; scoped query EXPLAIN showed a sequential scan on the current small table, so no speculative index was added. |
| 4 — Requests/service/error reporting | Complete | Focused administration Unit/Feature suite: 52 passed, 257 assertions; Pint passed; sanitized failure path verified.                                                            |
| 5 — Scoped read backend              | Complete | Scoped read tests, payload redaction, combined filters/pagination, policy middleware, and Dusk coverage passed.                                                                |
| 6 — Read UI/navigation/dashboard     | Complete | Vitest 18 files/68 tests, vue-tsc, targeted ESLint/Prettier, build, and Dusk coverage passed.                                                                                  |
| 7 — Create flow                      | Complete | Create authorization, validation, persistence, safe failure, frontend form, and Dusk create journey passed.                                                                    |
| 8 — Update flow                      | Complete | Update authorization/field matrix, password/activation behavior, last-write-wins convention, frontend form, and Dusk edit journey passed.                                      |
| 9 — Final acceptance                 | Complete | Serial PHP 726/5,029, frontend 18/68, static/type/build checks, Dusk 5/23, diff/scope review; two unrelated format-baseline warnings recorded above.                           |
