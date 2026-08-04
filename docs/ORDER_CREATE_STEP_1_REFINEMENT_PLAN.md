# Order Create Step 1 Refinement Plan

## Status and scope

**Status:** implementation complete for the scoped Step 1 refinement. Focused tests and browser verification pass. The
repository-wide PHPStan command still reports pre-existing findings outside this change; the changed backend request
passes focused PHPStan.

**Scope:** the authenticated Laravel/Inertia order-create flow in `lumasachi-backend`. The React Native project is out
of scope.

**Priority criterion:** user-visible correctness with the smallest change that preserves the existing order domain.

## Objective

Refine Step 1 of the create-order form so that:

1. The description and notes textareas have the same visible border weight, color, and rounded treatment as the other
   fields in the form.
2. The advance-payment field is absent from the Step 1 UI and from the payload created by that form.
3. A successful submission takes the authenticated user to the orders dashboard and shows a one-time “new order was
   created” message.
4. A failed submission leaves the user on the create form with the entered values intact and shows the validation/error
   state.
5. Server-side validation remains the authority and rejects data that cannot be stored under the current schema or
   business rules.

## Completed analysis gate

- [x] Read `docs/Business_Rules.md`, the current create page, API request/controller/service, final database schema,
  existing frontend tests, and relevant Dusk/Feature tests.
- [x] Confirmed the current working tree was clean before planning.
- [x] Confirmed the installed context through Laravel Boost: Laravel `12.64.0`, Inertia Laravel `2.0.24`, Inertia Vue
  `2.3.14`, Vue `3.5.28`, Tailwind `4.1.18`, PostgreSQL, Pest `4.7.5`, and PHPUnit `12.5.30`.
- [x] Used this analysis as the implementation gate and kept the React Native project out of scope.

## Current implementation audit

### Existing request flow

1. `routes/web.php:75-81` exposes the authenticated orders list at `web.orders.index` (`/orders`) and the create page at
   `web.orders.create` (`/orders/create`).
2. `OrderPageController::create()` renders `Orders/Create`; the page loads the catalog, customers, and employees on
   mount through `useOrderApi` (`resources/js/pages/Orders/Create.vue:153-169`).
3. The form currently submits with `fetch` through `api.orders.store`, not through an Inertia `<Form>` or `useForm`
   submission (`resources/js/pages/Orders/Create.vue:127-151`,
   `resources/js/composables/useOrderApi.ts:145-189,218-220`).
4. The API returns JSON `201` from `OrderController::store()` (`app/Http/Controllers/OrderController.php:82-97`). The
   page currently navigates to `web.orders.show` after success (`Create.vue:145`), so it does not yet go to the orders
   list or show a creation message there.
5. On a rejected API request, `Create.vue` stores the `OrderApiError` and does not reset or replace `form`; the existing
   behavior already preserves entered values in memory. This must be proved by a regression test rather than assumed.
6. The service receives the authenticated user and writes `created_by`/`updated_by`, then creates the order and its
   related records transactionally before transitioning it to `Awaiting Review`
   (`app/Services/OrderLifecycleService.php:32-83`).

### Business-rule and payment findings

`docs/Business_Rules.md` defines motor fields and received pieces/components at lines 4-52, requires the creation
transition from `Recibido` to `Esperando Revisión` at lines 55-57, and later describes an advance payment after customer
approval at lines 192-195.

The document also mentions a creation-time advance at line 53. The current implementation has since moved payment data
to the append-only `order_payments` ledger: `2026_07_29_143515_remove_legacy_payment_columns_from_order_motor_info.php`
removes the old payment columns from `order_motor_info`, while `OrderLifecycleService` records a positive API-supplied
creation advance as a payment row (`:47-59`). The live schema confirms that `order_motor_info` has no `down_payment` and
that `order_payments.amount` is `numeric(12,2)`.

**Working interpretation for this request:** remove advance payment from this UI form only. Do not remove the payment
ledger, the later customer-approval advance flow, or existing direct-API validation/tests unless the product explicitly
means to retire creation-time advances from every API caller.

### Form-to-schema validation map

The final PostgreSQL schema was inspected, not inferred from the original migrations alone.

| Step 1 input           | Final storage definition                                                     | Current server validation                                                       | Plan treatment                                                                                                                                                                                        |
|------------------------|------------------------------------------------------------------------------|---------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `customer_id`          | Required foreign key to `users`                                              | `required\|exists:users,id`                                                     | Preserve and test the required/unknown-ID failure path. Do not invent role or active-user rules not stated by the business rules.                                                                     |
| `title`                | `varchar(255)`, non-null                                                     | `required\|string\|max:255`                                                     | Preserve; the UI has no `maxlength`, so server rejection must keep the form state.                                                                                                                    |
| `description`          | `text`, non-null                                                             | `required\|string`                                                              | Preserve.                                                                                                                                                                                             |
| `priority`             | Four-value database check constraint                                         | `required` plus `Rule::in(OrderPriority::cases())`                              | Preserve.                                                                                                                                                                                             |
| `assigned_to`          | Required foreign key to `users`                                              | `required\|exists:users,id`                                                     | Preserve and test the unknown-ID failure path.                                                                                                                                                        |
| `estimated_completion` | Nullable timestamp                                                           | `nullable\|date\|after:today`                                                   | Preserve the schema-compatible date conversion and existing future-date rule.                                                                                                                         |
| `notes`                | Nullable `text`                                                              | `nullable\|string`                                                              | Preserve.                                                                                                                                                                                             |
| Motor Step 1 fields    | Nullable `varchar(255)` columns                                              | Nullable strings with current tighter max lengths                               | Keep the current domain limits unless a product decision requires exact `255`-character limits. Do not add the later torque/gap/clearance fields to Step 1; they are not present in the current form. |
| `items`                | At least one `order_items` row; `item_type` is non-null and unique per order | `required\|array\|min:1`; enum validation                                       | Preserve the at-least-one-piece rule. Check whether duplicate `item_type` values need explicit request validation to protect the database unique index.                                               |
| `items.*.components`   | `component_name` is non-null and unique per item                             | Array/string/max validation plus item-type membership `after()` validation      | Preserve optional/empty components. Check whether duplicate component values need explicit request validation to protect the database unique index.                                                   |
| Advance payment        | Not a final `order_motor_info` column; ledger amount is `numeric(12,2)`      | Accepted only by the direct API request and converted to an append-only payment | Remove only from the Step 1 UI/type/payload under this plan; preserve the separate ledger contract.                                                                                                   |

The existing request is `app/Http/Requests/StoreOrderWithItemsRequest.php:27-52`; the transaction and related-record
creation are in `app/Services/OrderLifecycleService.php:39-83`. No migration is needed for the requested UI removal.

### Existing feedback infrastructure

- `HandleInertiaRequests::share()` already exposes session `flash.success` and `flash.error`
  (`app/Http/Middleware/HandleInertiaRequests.php:51-70`).
- `resources/js/pages/Users/Index.vue:31-37,162-170` is the existing visual pattern for a flash status message.
- `resources/js/pages/Orders/Index.vue:59-119` currently renders the orders list but does not render flash data.
- The backend already has localized creation messages in `resources/lang/es/orders.php` and
  `resources/lang/en/orders.php`; the frontend i18n catalog does not currently expose an equivalent nested order
  creation message.

## Implementation sequence

### Group 1 — Lock the contract that blocks implementation

**Dependency:** none. This group defines the target before tests or production changes.

- [x] **1.1 Use `/orders` as the working meaning of “orders dashboard.”** The current code calls `web.orders.index` the
  orders list, and the global `/dashboard` is a separate page containing recent orders. If the intended destination is
  `/dashboard`, change the later navigation and flash-rendering targets before implementation.
- [x] **1.2 Interpret “remove advance payment field” as UI/payload scope.** Keep the append-only `order_payments`
  ledger, the post-approval advance payment control, and the direct-API legacy coverage. Escalate only if the
  requirement is intended to remove creation-time advances from the API as well.
- [x] **1.3 Keep Step 1 limited to the fields already present.** Do not add torque, ring-gap, clearance, or other fields
  merely because nullable columns exist in `order_motor_info`.

### Group 2 — Red specification gate (TDD)

**Dependency:** Group 1. Write or update these expectations before changing production files. Existing tests use the
repository’s current Vitest and Pest/Dusk conventions; do not create a parallel test harness.

- [x] **2.1 Update `tests/Frontend/OrderCreate.spec.ts`.** Add focused expectations that:
    - the advance-payment control is absent;
    - the create payload contains no `motor_info.down_payment` key;
    - description and notes expose the intended `border`/`border-input` treatment;
    - a successful create does not navigate to the order detail, navigates to `web.orders.index`, and publishes the
      success flash using the chosen implementation;
    - a failed create leaves entered title, description, notes, motor values, selected customer/assignee, and received
      item/component selections unchanged, while showing the error and performing no redirect.
- [x] **2.2 Add the smallest orders-list flash assertion.** Extend an existing `Orders/Index` frontend test if one is
  introduced by the implementation; otherwise add one focused `tests/Frontend/OrdersIndex.spec.ts` case that renders the
  shared success flash and checks the accessible status element. Reuse the existing `Users/Index.vue` pattern rather
  than creating a toast system.
- [x] **2.3 Update the browser contract only where the UI contract changed.** The current
  `tests/Browser/OrderIntakeAndReviewTest.php` and `tests/Browser/OrderIntakeTest.php` expect a successful redirect to
  the new order page and one test targets `@motor-down-payment`. Replace those assertions with:
    - successful navigation to `/orders` and a visible creation message;
    - a failure scenario using an existing server validation rule that does not depend on the removed field, proving the
      browser remains on `/orders/create` and preserves the entered values. Keep the existing Feature tests that prove
      direct API advance-payment ledger behavior; do not silently delete them.
- [x] **2.4 Add backend validation tests only for backend changes.** If the request rules are changed to protect the
  `order_items(order_id,item_type)` or `order_item_components(order_item_id,component_name)` unique indexes, first add
  focused atomic-rejection tests to `OrderLifecycleControllerTest.php` or the existing order edge-case file. If no
  backend rule changes are needed, leave the existing API contract tests intact.

### Group 3 — Apply the minimal form and payload changes

**Dependency:** Group 2’s red frontend specification.

- [x] **3.1 Match the textarea field styling.** In `resources/js/pages/Orders/Create.vue`, update both description and
  notes textareas to use the same one-pixel `border border-input`, rounded, background, padding, and focus treatment as
  the existing form inputs. `resources/js/components/users/UserForm.vue:238-242` is the closest existing textarea
  convention. Preserve each textarea’s current rows and accessibility/error bindings.
- [x] **3.2 Remove advance payment from the Step 1 form model.** Remove `down_payment` from the local form state, the
  rendered motor-information field, its Dusk selector/error binding, and the form-specific create payload type/state.
  Ensure the normalization in `submit()` cannot reintroduce the key.
- [x] **3.3 Preserve failed submissions.** Keep the current form object as the source of truth on `OrderApiError`; clear
  only the previous error before retrying. Do not reset, remount, or replace the form after a failed request.
- [x] **3.4 Do not change migrations or unrelated response types for this UI-only removal.** The final database already
  lacks `order_motor_info.down_payment`; the separate `MotorInfoPayload` may still contain legacy optional fields used
  by other API/resource contracts and should be changed only with direct evidence.

### Group 4 — Redirect and one-time success feedback

**Dependency:** Group 3’s form submission contract.

- [x] **4.1 Use the existing API mutation and Inertia v2 client-side flash path.** After the API returns a created
  order, visit `route('web.orders.index')` and publish the localized success message from the visit `onSuccess`
  callback. The callback is required because the installed Inertia core does not carry a client flash through a
  cross-component visit until the destination page is set. This avoids adding a duplicate web POST endpoint while the
  page already uses the JSON API.
- [x] **4.2 Add the frontend order-created message in both supported locales** under the existing orders translation
  namespace, or expose the already-localized API response message if the implementation keeps that response value. Do
  not hard-code a single language in the page.
- [x] **4.3 Render the shared flash on `resources/js/pages/Orders/Index.vue`.** Use `usePage<AppPageProps>()`, the
  existing
  `flash.success`/`flash.error` shape, an accessible `role="status"`, and the established border/color treatment from
  `Users/Index.vue`. Give it a stable selector for the frontend/browser assertion.
- [x] **4.4 Verify the success path end-to-end.** The created order must exist before the navigation, the destination
  must be `/orders`, the newly created order must be present after the list API reloads, and the success message must be
  visible once. Do not claim the message is implemented until the installed Inertia version carries the client flash
  through the visit in a passing test.
- [x] **4.5 Fallback only if required by a failing contract test.** Not required: the destination `onSuccess` callback
  carries the client-side flash successfully in the passing browser contract.

### Group 5 — Validation and domain safety review

**Dependency:** Groups 2-4. This group is required only to the extent that focused tests expose a schema/rule gap.

- [x] **5.1 Re-check every submitted field against the final schema and current business rules.** Keep validation on the
  server; browser attributes are only usability aids. Preserve required non-null fields, nullable optional fields,
  enum/check-constrained priority values, foreign-key existence checks, date parsing, motor string limits, and the
  at-least-one received piece rule.
- [x] **5.2 Protect database uniqueness before mutation if the API can receive duplicates.** Duplicate item types and
  component names now receive request-level rejection before mutation, with focused atomicity tests. The existing
  transaction remains unchanged.
- [x] **5.3 Preserve ownership and lifecycle invariants.** The acting authenticated user remains `created_by`/
  `updated_by`, creation still transitions to `Awaiting Review`, and customer/audit notification behavior remains
  unchanged. The form refinement must not change payment ledger semantics or lifecycle state transitions.
- [x] **5.4 Review any request-rule change against sibling tests before declaring it complete.** Do not broaden
  customer, assignee, active-user, or role restrictions unless a current business rule or an explicit product decision
  requires it.

## Verification sequence and failure handling

Run the minimum affected checks through Sail, serially for database-heavy PHP tests:

1. `vendor/bin/sail yarn run test:unit -- tests/Frontend/OrderCreate.spec.ts` and the orders-index test if added.
2. Run each changed PHP Feature file individually, starting with the order lifecycle validation file and
   `tests/Feature/app/Http/Controllers/OrderRouteTest.php` when backend behavior or route contracts change.
3. Run the changed browser file (s) through the project’s Dusk command when browser assertions change; do not substitute
   host PHP, host Node, or a different database.
4. If PHP files changed, run `vendor/bin/sail bin pint --dirty --format agent`.
5. For frontend changes, run the narrow ESLint/Prettier checks for the changed Vue/TypeScript files; run the frontend
   build if the Inertia/page bundle requires it.
6. Inspect `git diff --check` and the final diff for unintended changes. Do not claim the full suite, full browser
   suite, or production readiness from focused checks.

If Sail/Docker/PostgreSQL/Selenium is unavailable, record the exact command and infrastructure error in the
implementation notes, request the required permission or service recovery, then retry the same command. Do not work
around an environment failure with host tooling.

Recorded implementation notes:

- The first Dusk attempt reached the browser stage but failed before login with `NoSuchElementException` for
  `[@dusk="login-email"]`; a concurrent Sail retry also reported `Docker or Podman is not running`. After checking the
  containers and restarting only `docker compose restart dusk`, the exact Dusk command passed unchanged.
- The repository-wide `vendor/bin/sail composer run test:types` command completed with 59 existing PHPStan findings in
  unrelated authentication, user-administration, resource, order-money, notification, and service files. The focused
  command `vendor/bin/sail php vendor/bin/phpstan analyse app/Http/Requests/StoreOrderWithItemsRequest.php
  --configuration=phpstan.neon --memory-limit=2G` passed with no errors.

## Completion gate

The implementation may be marked complete only when all of the following are true:

- [x] Both textareas visibly use the same border weight/color convention as the other form fields.
- [x] No advance-payment control or Step 1 payload key remains, while separate ledger and later approval behavior is
  preserved.
- [x] A successful save redirects the authenticated user to `/orders`, shows the new-order message once, and lists the
  created order.
- [x] A failed save stays on `/orders/create`, preserves all entered data, shows validation feedback, and creates no
  partial records.
- [x] Server validation and focused tests cover the final schema/business-rule contract actually in scope.
- [x] Every changed test and verification command has a recorded passing result; the repository-wide PHPStan baseline
  exception is recorded above.

## Files expected to be relevant

Primary:

- `resources/js/pages/Orders/Create.vue`
- `resources/js/pages/Orders/Index.vue`
- `resources/js/i18n/index.ts`
- `tests/Frontend/OrderCreate.spec.ts`
- `tests/Browser/OrderIntakeTest.php`
- `tests/Browser/OrderIntakeAndReviewTest.php`

Conditional, only if focused validation tests prove a gap:

- `resources/js/types/orders.ts`
- `app/Http/Requests/StoreOrderWithItemsRequest.php`
- `tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php`
- `tests/Feature/app/Http/Controllers/OrderRouteTest.php`

No dependency change or database migration is expected for the stated UI refinement.
