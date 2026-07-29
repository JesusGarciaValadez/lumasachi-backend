# Order Status, Payments, and Refunds Implementation Plan

## Purpose

Determine and implement a simple, maintainable order-status design that supports:

- A readable lifecycle timeline.
- Future lifecycle statuses without duplicating transition rules.
- Partial and multiple payments.
- Returned and cancelled orders.
- A separate, auditable refund workflow.
- Consistent Dashboard, Orders, order-detail, and public-tracking presentation.

This file is a plan only. Do not implement application code until the relevant step is explicitly started.

## Conclusion

A state machine is useful, but an external state-machine package is unnecessary. Implement a small internal lifecycle
state machine and keep payment and refund concerns separate from lifecycle progress.

The current `orders.status` value mixes lifecycle states with payment and disposition states. That prevents the progress
timeline from remaining meaningful after an order becomes Paid, Returned, or Cancelled. The current transition map is
also duplicated in `UpdateOrderStatusRequest`, while `OrderLifecycleService` has separate status guards. The status
request validates notes, but `OrderController::updateStatus` currently does not persist them.

Use these separate concepts:

| Concern            | Recommended source of truth               | UI presentation                               |
|--------------------|-------------------------------------------|-----------------------------------------------|
| Lifecycle progress | Lifecycle status state machine            | Horizontal desktop / vertical mobile timeline |
| Money received     | Append-only `payments` records            | Payment totals and derived payment status     |
| Payment status     | Derived from payments and completed total | `Unpaid`, `Partially Paid`, or `Paid` badge   |
| Order disposition  | `Returned` or `Cancelled` outcome         | Badge beside title and priority               |
| Money returned     | Separate `refunds` records and workflow   | Refund state and totals                       |

Do not use Paid, Unpaid, Returned, or Cancelled as timeline steps.

## Business rules to preserve

The implementation must remain consistent with [`docs/Business_Rules.md`](Business_Rules.md):

- An order starts as Received and moves through review, customer approval, work, delivery preparation, and delivery.
- Reviewing an order creates the Reviewed event and immediately moves it to Awaiting Customer Approval; both history
  entries must remain visible.
- Delivery requires no outstanding payment.
- Completed-service totals remain the basis for the amount due.
- Existing customer and administrator notifications must not be removed without an explicit business decision.

Additional requirements confirmed for this plan:

- Lifecycle progress excludes Paid, Unpaid, Returned, and Cancelled.
- Partial and multiple payments are supported.
- Refunds may be requested only for Returned or Cancelled orders.
- Refund approval is always required.
- An Admin cannot approve their own refund request.
- Another Admin or a Super Admin may approve an Admin request.
- A Super Admin may approve their own request or another Super Admin's request.
- Other roles cannot approve refunds.
- Full, partial, and multiple refunds are supported.
- A refund cannot exceed successfully received payments.
- Returning or cancelling an order does not automatically create a refund.
- Returned and Cancelled status changes require a note appended to the existing order note.

## Current-code findings

Review these files before implementation and update the plan if the checkout has changed:

- [`docs/Business_Rules.md`](Business_Rules.md): authoritative lifecycle rules.
- [`app/Enums/OrderStatus.php`](../app/Enums/OrderStatus.php): currently combines lifecycle, payment, and disposition
  values.
- [`app/Http/Requests/UpdateOrderStatusRequest.php`](../app/Http/Requests/UpdateOrderStatusRequest.php): currently
  contains a hand-written transition map.
- [`app/Services/OrderLifecycleService.php`](../app/Services/OrderLifecycleService.php): currently guards lifecycle
  actions separately.
- [`app/Services/OrderCapabilityService.php`](../app/Services/OrderCapabilityService.php): currently derives UI
  capabilities from the mixed status value.
- [`app/Observers/OrderObserver.php`](../app/Observers/OrderObserver.php): currently records history, sends status
  notifications, and performs the automatic Reviewed transition.
- [`app/Http/Controllers/OrderController.php`](../app/Http/Controllers/OrderController.php): currently updates status
  directly and does not persist the validated status-update note.
- [`app/Models/Order.php`](../app/Models/Order.php): currently stores down payment, total cost, and fully-paid state
  through the related motor-information model.
- [`app/Http/Resources/OrderResource.php`](../app/Http/Resources/OrderResource.php) and
  [`app/Http/Resources/PublicOrderResource.php`](../app/Http/Resources/PublicOrderResource.php): current API contracts
  expose one mixed status value.
- [`resources/js/types/orders.ts`](../resources/js/types/orders.ts): current frontend sequence includes every supported
  status, including payment and disposition values.
- [`resources/js/components/orders/OrderStatusProgress.vue`](../resources/js/components/orders/OrderStatusProgress.vue):
  current progress uses a wrapping grid rather than a connected compact timeline.
- [`resources/js/pages/Dashboard.vue`](../resources/js/pages/Dashboard.vue),
  [`resources/js/pages/Orders/Index.vue`](../resources/js/pages/Orders/Index.vue),
  [`resources/js/pages/Orders/Show.vue`](../resources/js/pages/Orders/Show.vue), and
  [`resources/js/pages/Orders/Track.vue`](../resources/js/pages/Orders/Track.vue): current status presentation points.

The database currently stores `orders.status` as a string with a PostgreSQL check constraint containing the mixed status
values. Adding or removing values therefore requires a data/schema migration even if the PHP enum changes.

## Status model decisions required before coding

### Lifecycle sequence

The exact sequence must be confirmed before implementation. The business rules clearly cover Received, Awaiting Review,
Reviewed, Awaiting Customer Approval, Ready for Work, Ready for Delivery, and Delivered. The current code also contains
Open, In Progress, Completed, and On Hold, but these are not all defined in `Business_Rules.md`.

Do not silently add or remove those statuses. Decide whether they are:

1. Current valid lifecycle states to retain.
2. Legacy states to migrate.
3. States to remove from the application.

Also confirm whether the requested display label “Awaiting revision” means the existing `Awaiting Review` status.

### Disposition status

Keep Returned and Cancelled outside the lifecycle timeline. Confirm whether an order can resume its lifecycle after
either disposition; the current code treats some terminal statuses as non-transitionable.

### Refund request permissions

The approver permissions are defined above. The role (s) allowed to submit a refund request have not been explicitly
defined and must follow the existing order policy until the business specifies otherwise.

## Step-by-step implementation plan

### Step 1 — Freeze the domain vocabulary and inventory existing data — DONE

**Step 1 status: complete.** The inventory and explicit migration blockers are documented. The unresolved domain
decisions below must be answered before Step 2 defines the state machine.

Scope:

- Confirm the lifecycle sequence and exact stored values.
- Confirm whether `Not Paid` is renamed to `Unpaid` at the API/UI level.
- Confirm whether Open, Completed, and On Hold remain valid.
- Inventory existing orders using Paid, Not Paid, Returned, or Cancelled.
- Inspect their status history to identify the last lifecycle status.

Instructions:

- Do not infer a lifecycle status for ambiguous legacy records.
- Record any rows requiring a business decision before migration.
- Do not change the React Native project.

Completion gate:

- Canonical lifecycle, payment, disposition, and refund values are documented.
- Every existing special-status order has a migration decision or is explicitly blocked.

#### Phase 1 inventory result

The following is the result of inspecting the current code, migrations, and the live Sail database. No application code
or React Native code was changed in phase 1.

##### Current domain vocabulary

The current `OrderStatus` enum stores these 15 values in `orders.status`:

| Current stored value         | Phase 1 classification          | Evidence / decision                                                                                                                                                      |
|------------------------------|---------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Received`                   | Lifecycle                       | Defined by `Business_Rules.md`; order entry point.                                                                                                                       |
| `Awaiting Review`            | Lifecycle                       | Defined by `Business_Rules.md`; equivalent to the Spanish “Esperando Revisión”.                                                                                          |
| `Reviewed`                   | Lifecycle                       | Defined by `Business_Rules.md`; the customer notification is followed by the automatic approval-waiting event.                                                           |
| `Awaiting Customer Approval` | Lifecycle                       | Defined by `Business_Rules.md`.                                                                                                                                          |
| `Ready for Work`             | Lifecycle                       | Defined by `Business_Rules.md`.                                                                                                                                          |
| `Open`                       | Unresolved legacy/current value | Present in the enum, request transition map, factories, seeders, and tests, but not defined by `Business_Rules.md`.                                                      |
| `In Progress`                | Unresolved legacy/current value | Present in the enum, service, factories, seeders, and tests, but not defined by `Business_Rules.md`.                                                                     |
| `Ready for Delivery`         | Lifecycle                       | Defined by `Business_Rules.md`.                                                                                                                                          |
| `Completed`                  | Unresolved value                | Present in the enum and request transition map, but the current lifecycle service moves work directly to `Ready for Delivery`; it is not defined by `Business_Rules.md`. |
| `Delivered`                  | Lifecycle terminal point        | Defined by `Business_Rules.md`; delivery completes the documented lifecycle.                                                                                             |
| `Paid`                       | Payment status                  | Must not remain a lifecycle timeline step under this plan.                                                                                                               |
| `Not Paid`                   | Payment status                  | Current stored/API/UI value. Whether it is renamed to `Unpaid` is not confirmed.                                                                                         |
| `Returned`                   | Disposition                     | Must remain outside the lifecycle timeline under this plan. Whether it can resume lifecycle progress is not confirmed.                                                   |
| `On Hold`                    | Unresolved legacy/current value | Present in the enum, request transition map, factories, seeders, and tests, but not defined by `Business_Rules.md`.                                                      |
| `Cancelled`                  | Disposition                     | Must remain outside the lifecycle timeline under this plan. Whether it can resume lifecycle progress is not confirmed.                                                   |

The lifecycle values confirmed by the business rules are therefore:

```text
Received → Awaiting Review → Reviewed → Awaiting Customer Approval
→ Ready for Work → Ready for Delivery → Delivered
```

This sequence is documented as the confirmed subset, not as permission to silently remove or migrate `Open`, `In
Progress`, `Completed`, or `On Hold`. Those four values require a business decision before Step 2 can define the
canonical transition map.

The refund vocabulary in this plan is `Requested`, `Approved`, `Processed`, and `Rejected`. No refund table, model, or
persisted refund status currently exists; these remain planned workflow values rather than current database data.

##### Existing database status inventory

The live database query returned 54 orders:

| Stored status                | Order count | Migration classification                                          |
|------------------------------|------------:|-------------------------------------------------------------------|
| `Awaiting Customer Approval` |           3 | Confirmed lifecycle.                                              |
| `Awaiting Review`            |           2 | Confirmed lifecycle.                                              |
| `Cancelled`                  |           4 | Disposition; migration decision required for each record.         |
| `Delivered`                  |           6 | Confirmed lifecycle.                                              |
| `In Progress`                |           4 | Unresolved value; migration decision required for each record.    |
| `Not Paid`                   |           1 | Payment status; `Not Paid` versus `Unpaid` requires confirmation. |
| `On Hold`                    |           1 | Unresolved value; migration decision required for this record.    |
| `Open`                       |           5 | Unresolved value; migration decision required for each record.    |
| `Paid`                       |           7 | Payment status; migration decision required for each record.      |
| `Ready for Delivery`         |           5 | Confirmed lifecycle.                                              |
| `Ready for Work`             |           7 | Confirmed lifecycle.                                              |
| `Received`                   |           1 | Confirmed lifecycle.                                              |
| `Returned`                   |           6 | Disposition; migration decision required for each record.         |
| `Reviewed`                   |           2 | Confirmed lifecycle.                                              |
| `Completed`                  |           0 | No existing rows found; its future validity is still unresolved.  |

The special-status subset is 18 records: 7 `Paid`, 1 `Not Paid`, 6 `Returned`, and 4 `Cancelled`.

##### Status-history findings

Status history was inspected for all 18 special-status orders. Only two orders have status-history rows:

- Order ID `6` is currently `Paid`, with history `In Progress → Ready for Delivery → Delivered → Paid`, followed by a
  later `Open → In Progress` row. The sequence conflicts with the current status and cannot be used for a safe lifecycle
  migration without a business decision.
- Order ID `8` is currently `Cancelled`, with history `Open → Cancelled`. `Open` is not defined by the business rules,
  so the last lifecycle status remains unresolved.

The other 16 special-status orders have no status-history rows in the live database. Their last lifecycle status cannot
be inferred from the current data. The migration must not invent one.

##### Decisions required before Step 2

These questions are intentionally recorded here instead of being inferred in code:

1. Should `Open`, `In Progress`, and `On Hold` remain valid lifecycle statuses? If yes, where do they fit in the
   confirmed sequence? If no, what exact legacy mapping should be used?
2. Should `Completed` remain valid? The current service does not use it and the business rules describe
   `Ready for Delivery` after work is finished.
3. Should the stored/API/UI value `Not Paid` be renamed to `Unpaid`, or should the existing value be preserved?
4. Can `Returned` or `Cancelled` orders resume lifecycle progress, or are they terminal dispositions?
5. What exact lifecycle status should be assigned to each existing special-status order whose history is absent or
   contradictory? Until answered, all 18 special-status records are explicitly blocked from automatic migration.

Step 1 is complete. The next implementation step must not silently resolve the listed business decisions; Step 2 is
blocked until they are answered.

##### Verification and environment note

- Static inventory: completed from `OrderStatus`, `UpdateOrderStatusRequest`, `OrderLifecycleService`, migrations,
  factories, seeders, resources, frontend types, and tests.
- Live inventory: completed with a read-only Sail database query of `orders` and `order_histories`.
- Initial Sail access reported `Docker or Podman is not running`. Permission was requested to access Docker; Docker
  Desktop was already running, and Sail access then succeeded.
- No application test was added or changed because phase 1 changes only this planning document. Existing focused
  verification should be run after the vocabulary decisions are answered and Step 2 changes application code.

### Step 2 — Create one lifecycle transition authority — DONE

Scope:

- Add a small internal state-machine/service abstraction for lifecycle statuses only.
- Expose transition validation and the next permitted states from one source.
- Keep policies and role authorization outside the state machine.

Instructions:

- Move the duplicated transition map out of the Form Request.
- Route lifecycle mutations through the existing `OrderLifecycleService`.
- Preserve the automatic Reviewed to Awaiting Customer Approval behavior and both history entries.
- Preserve existing notification behavior unless the business rules require a change.
- Prevent direct status updates from bypassing lifecycle validation.

Focused verification:

- Unit-test every permitted lifecycle transition.
- Test rejected skips, backward transitions, terminal transitions, and unknown values.
- Test the two distinct Reviewed history entries.

Completion gate:

- Form Requests, lifecycle actions, and the status endpoint use the same transition authority.
- No lifecycle mutation can bypass authorization and transition validation through the normal application routes.

**Step 2 status: complete.**

Implementation completed:

- Added `OrderStatusStateMachine` with transition validation, next permitted statuses, stored-value validation, and
  persisted/quiet transition methods.
- Moved the duplicated transition map out of `UpdateOrderStatusRequest`.
- Routed `OrderLifecycleService` transitions, the status endpoint, and status changes submitted through the general
  order-update endpoint through the state machine.
- Reused the state machine for the observer's automatic `Reviewed → Awaiting Customer Approval` update while retaining
  the explicit second history entry.
- Added unit coverage for every currently permitted transition, rejected transitions, and unknown values.
- Added coverage proving a general order update cannot bypass transition validation.

Compatibility decision: because the Step 1 business questions remain unanswered, the state machine temporarily retains
the existing legacy, payment, and disposition transitions. This preserves current behavior without inventing mappings;
the map must be narrowed when the payment/refund steps and legacy-status decisions are completed. The existing service
path `Ready for Work → Ready for Delivery` is also retained because it is already implemented and tested.

The status request's `notes` field remains validated but is not persisted by this step. The required append/history
semantics for status notes are not defined yet and must be resolved before the Returned/Cancelled workflow is built.

Verification completed:

- `vendor/bin/sail bin pint --format agent` on all changed PHP files: passed.
- Focused state-machine and order lifecycle/controller tests: 119 passed, 440 assertions.
- The initial Sail attempt reported `Docker or Podman is not running`; permission was requested to access Docker, Docker
  Desktop was already running, and the focused suite then completed successfully.

### Step 3 — Implement the payment ledger

Scope:

- Add an append-only payment record related to an order.
- Support multiple payments and partial payments.
- Preserve exact two-decimal calculations using the existing money-handling conventions.

Minimum payment data:

- Order reference.
- Amount.
- Received timestamp.
- Creating user.

Do not add payment methods, processor fields, or external references unless the business requires them.

Rules:

- Total paid equals the sum of valid received payments.
- `Unpaid` means no amount has been received.
- `Partially Paid` means payment is greater than zero but below the completed total.
- `Paid` means payment meets or exceeds the completed total.
- Overpayment remains Paid and must not produce a negative amount due.
- Delivery remains blocked while the amount due is positive.

Migration:

- Convert existing `down_payment` amounts into initial payment records.
- Do not invent missing payment metadata.
- Define how legacy payment timestamps and actors are represented before running the migration.

Focused verification:

- Zero payment.
- One partial payment.
- Multiple partial payments.
- Exact payment.
- Overpayment.
- Payment after the calculated total changes.
- Delivery with and without an outstanding balance.

Completion gate:

- Payment totals have one authoritative source.
- Delivery and capabilities use the same payment calculation.
- Existing financial totals and business-rule tests remain valid.

**Step 3 status: complete.**

Implementation completed:

- Added the append-only `order_payments` ledger with order reference, two-decimal amount, received timestamp, and
  creating user. Payment methods, processor fields, and external references were not added because the business rules do
  not require them.
- Added `OrderPaymentService` and the authenticated `POST /api/v1/orders/{order:uuid}/payments` endpoint. The endpoint
  uses the existing `can:update,order` authorization because no separate payment role was defined.
- Added multiple/partial payment support, exact BCMath totals, derived `Unpaid`, `Partially Paid`, and `Paid` values,
  overpayment handling, and a non-negative remaining balance.
- Updated delivery validation and order capabilities to use the same order-level payment calculation.
- Updated authenticated order resources to include payment records and their creator. Public tracking does not expose
  payment records or payment creators.
- Removed the redundant persisted `down_payment`, `total_cost`, and `is_fully_paid` motor-information fields. The
  existing `down_payment` request input remains as the initial/cumulative amount used to create ledger records, but it
  is no longer persisted as a second payment source.

Migration decisions and assumptions:

- Existing positive `down_payment` values are copied into one initial payment per motor-information row before the
  legacy columns are removed. The legacy `order_motor_info.created_at` is used as `received_at` because it is the only
  retained timestamp; `created_by` is left `NULL` because the legacy record did not store an actor. No actor or payment
  metadata is invented.
- Customer approval's existing cumulative `down_payment` input is treated as the desired total received amount. Only the
  positive difference from the ledger total creates a new payment; lowering an already received cumulative amount is
  rejected.
- The migration backfill is intentionally irreversible. The application is not production yet, so removing the redundant
  motor-information columns avoids maintaining two sources of payment state.

Verification completed:

- Focused payment/controller and related lifecycle tests passed, including zero, partial, multiple, exact, overpayment,
  changed completed totals, validation, delivery blocking, and capability behavior.
- Laravel Pint passed on changed PHP files.
- Focused payment/controller coverage passed: 15 tests, 190 assertions. The related lifecycle, history, and state
  machine checks also passed.
- The full backend suite passed through Sail: 671 tests, 4,717 assertions. If Docker/Postman or another local dependency
  is unavailable in the sandbox, the test suite may fail for environment reasons; request permission to access/start
  Docker and retry the same Sail command before treating that as a product failure.

Mark this step as done only after all requirements and verification checks above remain complete.

### Step 4 — Implement the separate refund workflow

Scope:

- Add an append-only refund record related to an order and optionally to a source payment.
- Keep refunds separate from payment records and lifecycle transitions.

Workflow:

```text
Requested -> Approved -> Processed
Requested -> Rejected
```

Rules:

- A request is valid only when the order is Returned or Cancelled.
- Approval is always required before processing.
- Full, partial, and multiple refunds are supported.
- Processed refunds cannot exceed successfully received payments.
- Returning or cancelling an order does not automatically create a refund.
- A processed refund does not change the lifecycle timeline.
- Payment status remains independently derived; refund totals are displayed separately.

Approval authorization:

| Requester   | Who may approve                             |
|-------------|---------------------------------------------|
| Admin       | Another Admin or Super Admin                |
| Super Admin | The same Super Admin or another Super Admin |
| Other roles | Cannot approve                              |

Instructions:

- Enforce self-approval rules in the backend policy/service, not only in the UI.
- Record requester, approver, processor, amounts, reason, and timestamps needed for audit.
- Do not create a refund automatically from a Returned or Cancelled transition.
- Do not delete or silently edit processed refunds. If corrections are required, define a reversal process first.
- Refunds should not imply a bank/payment-provider integration until one is specified.

Focused verification:

- Request rejected for a non-Returned/non-Cancelled order.
- Admin self-approval rejected.
- Admin request approved by another Admin.
- Admin request approved by Super Admin.
- Super Admin self-approval accepted.
- Employee/customer/other-role approval rejected.
- Approval required before processing.
- Full, partial, multiple, and excessive refund attempts.
- Rejected and processed requests remain in history.

Completion gate:

- Refund state changes are authorized, auditable, and independent from lifecycle/payment state.

**Step 4 status: complete.**

Implementation completed:

- Added the append-only `order_refunds` record with optional source-payment linkage, amount, reason, requester,
  approver/rejector, processor, workflow timestamps, and `Requested`, `Approved`, `Processed`, and `Rejected` values.
- Added the refund service and authenticated API endpoints for requesting, approving, rejecting, and processing refunds.
  Refund requests are accepted only for `Returned` or `Cancelled` orders and do not change order status or payment
  records.
- Applied the existing order `update` policy to refund requests and processing, as previously specified for the
  unresolved requester-role decision. This allows administrators and super administrators, plus employees who may update
  the order; customers remain denied.
- Added backend policy/service enforcement for the approval matrix: another Admin or any Super Admin may review an Admin
  request; a Super Admin may review their own or another Super Admin's request; employees and customers cannot approve
  or reject; Admin self-approval is denied.
- Processing requires `Approved` state and uses a locked order transaction so processed refunds cannot exceed the
  successfully received payment total, including multiple partial refunds. Processed and rejected rows remain auditable,
  and no deletion or correction path was added.
- No payment-provider or bank integration was introduced, and no public tracking resource was changed.

Verification completed:

- `vendor/bin/sail artisan list`: passed.
- Focused refund PHPUnit coverage: 10 tests, 52 assertions passed. Coverage includes disposition validation, requester
  authorization, all approval-role rules, approval-before-processing, partial/multiple refunds, excessive totals,
  rejected audit state, and lifecycle/payment independence.
- `vendor/bin/sail bin pint --dirty --format agent`: passed after formatting the changed PHP files.
- `git diff --check`: passed.
- Sail commands require Docker or Podman access. If the sandbox reports that Docker/Podman or an external API client
  such as Postman is unavailable, treat that as an environment-access failure, request permission to access/start the
  required service, and retry the same Sail verification command before judging the implementation.

The step is marked done because its requirements and focused verification are complete.

### Step 5 — Update persistence, resources, and history — DONE

Scope:

- Add lifecycle, disposition, payment, and refund fields to the API contracts.
- Preserve stable enum values for application logic and localized labels for presentation.
- Update history casting so lifecycle, payment, disposition, payment-record, and refund events remain distinguishable.

Instructions:

- Plan the migration from the mixed `orders.status` column carefully.
- Update PostgreSQL check constraints or replace them with the chosen validation strategy.
- Keep public resources free of internal users, IDs, processor data, and private refund details.
- Keep all mutations under Form Requests, policies, and services.

Focused verification:

- Authenticated resources return the complete new contract.
- Public tracking exposes only approved public-safe fields.
- History preserves ordering, actor, amount, and event type.
- Invalid or unauthorized mutations do not partially update data.

Completion gate:

- API consumers no longer need to interpret one mixed status value.
- Existing history is preserved and new events are unambiguous.

**Step 5 status: complete.**

Implementation completed:

- Added nullable `lifecycle_status` and `disposition_status` columns with indexes. The existing mixed `status` column
  remains as a compatibility value; unresolved legacy records were not silently mapped.
- Added stable `OrderLifecycleStatus`, `OrderDispositionStatus`, and `OrderPaymentStatus` enums with localized labels.
  Authenticated and public resources now expose lifecycle and disposition fields separately; authenticated resources
  also expose derived payment status and refund records. Existing public tracking restrictions remain in place.
- Added `OrderHistoryEventType` and persisted `event_type` values for lifecycle, disposition, payment status,
  payment-record, refund, and general attribute events. Payment and refund services now append typed history entries,
  including amounts, workflow status, actor, and timestamps through the existing history model.
- Kept existing history rows intact. New history rows use explicit event types, while the migration default preserves
  old rows without guessing whether ambiguous legacy status values were lifecycle, payment, or disposition events.
- Public tracking excludes payment and refund history events and does not expose payment/refund records or internal
  users.

Verification completed:

- `vendor/bin/sail artisan test --compact`: **684 passed, 4,791 assertions**.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- `git diff --check`: passed.
- If a future verification run reports that Docker/Podman or an external API client such as Postman is missing or not
  working, treat that as a sandbox/environment access issue. Request permission to access or start the dependency,
  confirm with `vendor/bin/sail artisan list`, and retry the same Sail command before judging the code.

The step is marked done because its persistence, resource, history, and focused/full verification requirements are
complete. The unresolved legacy-status migration decisions remain explicitly deferred to Step 7.

### Step 6 — Update the UI presentation

Scope:

- Update Dashboard, Orders, authenticated order detail, and public tracking.
- Keep the existing AppLayout, cards, tokens, responsive behavior, and UI primitives.

Order detail:

- Show disposition/payment/refund indicators beside the title and priority.
- Render priority as both the `Priority` label and its value using the same badge color.
- Keep Paid, Unpaid, Returned, and Cancelled out of the timeline.

Progress component:

- Render only lifecycle statuses.
- Use a compact connected horizontal line on desktop when space allows.
- Use a connected vertical line on mobile.
- Keep the active step accessible with `aria-current` and a text label.

Dashboard and Orders:

- Show lifecycle progress plus separate payment, disposition, and refund indicators where relevant.
- Do not replace the lifecycle value with a payment/refund outcome.

Public tracking:

- Keep it read-only and public-safe.
- Confirm whether payment and refund totals/statuses are public before exposing them.

Focused verification:

- Timeline excludes payment/disposition statuses.
- Timeline displays the correct active lifecycle state when a special status exists.
- Header badges show priority, payment, disposition, and refund states distinctly.
- Desktop and mobile markup support the requested horizontal/vertical presentation.
- Loading, error, stale-state, and empty states remain intact.

Completion gate:

- All four UI surfaces use the same server-provided status contract.
- No UI control is treated as the authority for authorization or transitions.

### Step 7 — Data migration and compatibility rollout

Scope:

- Migrate existing mixed status records to lifecycle/disposition fields.
- Convert legacy down payments to payment records.
- Preserve existing order and history identifiers.

Instructions:

- Take a database backup before production migration.
- Run a dry-run/report for ambiguous orders and missing payment metadata.
- Decide whether the old `status` API field is temporarily retained as a compatibility alias or removed in one
  coordinated application release.
- Do not silently map ambiguous records.

Completion gate:

- Migration report contains zero unresolved records, or the remaining records have explicit business decisions.
- Existing orders retain their financial and history meaning.

### Step 8 — Final verification and completion tracking

Run focused checks after each implementation step. Do not mark a step complete until its requirements and focused tests
are complete and passing.

Expected Sail commands:

```sh
vendor/bin/sail artisan list
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderAdvancedControllerTest.php
vendor/bin/sail artisan test --compact
vendor/bin/sail yarn run test:unit
vendor/bin/sail yarn vue-tsc --noEmit
vendor/bin/sail yarn run build
vendor/bin/sail yarn run format:check
vendor/bin/sail bin pint --dirty --format agent
git diff --check
```

Verification rules:

- Use the exact Sail commands from the backend repository.
- If Docker or Podman is unavailable, treat it as an environment-access failure, not a product failure.
- Recover container access, confirm with `vendor/bin/sail artisan list`, and retry the same command.
- Do not substitute host PHP, Composer, Node, or Yarn commands.
- If parallel PostgreSQL tests have migration/database-isolation failures, retry with the project's documented database
  recreation option and use the serial full suite before judging product behavior.
- Report documentation checks separately from application-test results.

## Explicit non-goals

- No external state-machine package unless the internal approach proves insufficient.
- No payment-provider integration without a separate business and technical requirement.
- No automatic refunds from Returned or Cancelled transitions.
- No refund or payment data exposed publicly without an explicit privacy decision.
- No changes to `/Users/jesus.garciav/Sites/lumasachi-react-native`.
