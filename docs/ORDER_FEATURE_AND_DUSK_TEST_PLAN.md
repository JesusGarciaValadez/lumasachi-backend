# Order Feature and Laravel Dusk Test Plan

## Purpose

Create a business-rule-driven suite of Laravel Feature tests and Laravel Dusk browser tests for the order lifecycle
described in [`Business_Rules.md`](Business_Rules.md).

This file is the implementation plan and verification record for the order Feature and Dusk test suite.

## Rules for using this plan

- Treat `docs/Business_Rules.md` as the primary acceptance source.
- Inspect the current implementation again before each stage because the code may have changed since this plan was
  written.
- Do not invent behavior to make a stage look complete. Record an unanswered question or implementation gap instead.
- Prefer extending an existing focused test file over duplicating the same scenario in a new file.
- Use Feature tests for validation, authorization, persistence, notifications, history, API resources, and state
  transitions.
- Use Dusk only for a small number of valuable end-to-end browser journeys and client-side failure states.
- Do not make a Dusk test repeat every backend validation already proven by a Feature test.
- Keep changes inside `lumasachi-backend` and limited to the order test feature.
- Do not add or change dependencies without approval.
- **Do not mark a stage done until every task and completion gate in that stage is satisfied.**
- When a stage is complete, change its checkbox from `[ ]` to `[x]` and record the verification command and result in
  the stage notes.

## Business-rule lifecycle under test

1. Staff creates an order with motor details, at least one received piece, and zero or more components for each piece.
2. Creation moves the order from `Received` to `Awaiting Review` and notifies the customer plus audit users.
3. Staff reviews the received pieces, selects budgeted services, and the totals use the catalog prices.
4. The order records `Reviewed`, notifies the customer and audit users, and immediately moves to
   `Awaiting Customer Approval`.
5. The owning customer approves all or some proposed services and may leave an advance payment.
6. The order moves to `Ready for Work`.
7. Staff marks the services actually completed. An authorized service may remain incomplete when it should not be
   charged.
8. Staff moves the order to `Ready for Delivery`, notifying the customer.
9. Delivery is allowed after the outstanding balance is paid. Delivery notifies the customer and audit users.
10. A public user can locate one order using its UUID and creation date and see the public order information, history,
    and attachments allowed by the product decision.

## Audit snapshot

Snapshot date: 2026-07-29.

### Current implementation

- Laravel reports version `12.64.0`.
- The PHP suite currently uses Pest 4 on top of PHPUnit, although some repository guidance requires new tests to be
  PHPUnit classes. Until that guidance changes, plan new Dusk tests as PHPUnit classes.
- Laravel 12 documentation recommends Pest browser testing for new projects, but this plan retains Laravel Dusk because
  Dusk is an explicit requirement for this suite.
- The order domain already separates lifecycle, payment, disposition, and refund states.
- `OrderLifecycleService` owns the main intake, budget, approval, completion, ready-for-delivery, and delivery
  operations.
- `OrderStatusStateMachine` enforces the forward lifecycle and terminal dispositions.
- `OrderObserver`, `OrderItemObserver`, and `OrderServiceObserver` create history and notification side effects.
- Payments are an append-only `order_payments` ledger. A creation-time or approval-time advance is recorded as a payment
  rather than stored on motor information.
- Authenticated order pages exist for index, creation, and detail.
- Public tracking exists at `GET /orders/track` and `POST /api/v1/orders/track`.
- The current order detail UI supports budget submission, customer approval, work completion, marking ready, and
  delivery.
- The current order detail UI displays payment totals but does **not** provide a UI for recording the final payment.
- The authenticated order detail UI lists attachment metadata and offers preview/download actions.
- Public tracking returns attachment metadata, but its public resource does **not** expose an attachment UUID or
  preview/download URL.

### Current test coverage

Relevant existing coverage is concentrated in:

- `tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php`
- `tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php`
- `tests/Feature/app/Http/Controllers/OrderAdvancedControllerTest.php`
- `tests/Feature/app/Http/Controllers/OrderPaymentControllerTest.php`
- `tests/Feature/app/Http/Controllers/OrderRouteTest.php`
- `tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php`
- `tests/Feature/app/Http/Controllers/OrderHistoryTrackingTest.php`
- `tests/Feature/app/Observers/OrderObserverAdvancedTest.php`
- `tests/Feature/app/Observers/OrderObserversPhase3Test.php`
- `tests/Feature/app/Mail/OrderCreatedMailTest.php`
- `tests/Frontend/OrderCreate.spec.ts`
- `tests/Frontend/OrderReviewBudgetPanel.spec.ts`
- `tests/Frontend/OrderCustomerApprovalPanel.spec.ts`
- `tests/Frontend/OrderShowDelivery.spec.ts`
- `tests/Frontend/Track.spec.ts`

There are currently no files under `tests/Browser`, `laravel/dusk` is not a direct Composer dependency, the Dusk Artisan
commands are unavailable, and `docker-compose.yml` has no Selenium service.

### Baseline verification performed during planning

The following existing order-focused Feature tests were run:

```bash
vendor/bin/sail artisan test --compact \
  tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php \
  tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php \
  tests/Feature/app/Http/Controllers/OrderPaymentControllerTest.php \
  tests/Feature/app/Http/Controllers/OrderRefundControllerTest.php \
  tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php \
  tests/Feature/app/Http/Controllers/OrderHistoryTrackingTest.php \
  tests/Feature/app/Observers/OrderObserverAdvancedTest.php \
  tests/Feature/app/Observers/OrderObserversPhase3Test.php
```

Result: `92 passed (690 assertions)`.

This baseline does not prove that the whole suite passes, and it does not include browser tests because Dusk is not
installed.

## Confirmed coverage to preserve

Do not rewrite these tests merely to create a new suite:

- Order intake persists motor information, pieces, components, initial payment, and the `Awaiting Review` state.
- Creation rejects missing received items, invalid item types, invalid components, and negative advance payments.
- Creation notifies the customer and all active Administrator and Super Administrator audit recipients.
- The business example totals are already asserted:
    - budgeted base `3760.00`, net `4361.60`;
    - authorized base `1880.00`, net `2180.80`;
    - completed net `1252.80`.
- Budget submission rejects foreign items, unreceived items, unknown or inactive catalog services, services for the
  wrong item type, and missing required measurements.
- Customer approval rejects foreign, unbudgeted, malformed, or duplicate service IDs and forbids a different customer.
- Work completion rejects foreign, unauthorized, already completed, malformed, duplicate, and mixed invalid selections
  without partial mutation.
- `Reviewed` and the automatic `Awaiting Customer Approval` transition both appear in history.
- Delivery is blocked while a balance remains and allowed when the balance is satisfied.
- Public tracking covers matching and mismatched UUID/date pairs, malformed input, empty/populated public collections,
  public field shapes, and throttling.
- Forward, skipped, backward, unauthorized, and terminal status changes have existing coverage.

## Questions and known product gaps

These decisions have been confirmed for this implementation.

### Q1. What does “none” mean for received items?

The business rules say an order may include none, some, or all listed options. The current API requires at least one
top-level piece, while allowing a selected piece to have no components.

Confirmed interpretation: at least one top-level piece is required because a customer leaves a piece for review; “none”
applies to the optional components inside that selected piece.

### Q2. What happens when the customer rejects every proposed service?

The business rules say the customer can approve “or not” all or some suggestions. The current request requires at least
one authorized service and then moves to `Ready for Work`.

Confirmed behavior: approving zero services means that the customer did not approve the quote, so the order must be
cancelled. Feature coverage must prove the cancellation and its history/notification behavior from the current
implementation contract.

### Q3. How is the final payment recorded in the browser?

The API supports append-only payments, but the current order page has no payment entry control. A true browser happy
path cannot pay the balance and then deliver the order entirely through the UI.

Confirmed boundary: no payment UI is required. Cash payment is recorded manually through the existing backend payment
operation; Dusk may set up the final payment before asserting that delivery is available. The browser test must not
claim to cover payment entry.

### Q4. What does “view attachments” mean on public tracking?

Public tracking currently shows attachment metadata only. It cannot preview or download the file because the public
resource exposes neither an attachment identifier nor a public file endpoint.

Confirmed behavior: public tracking needs full attachment behavior, including a secure public preview/download path. The
public resource and endpoint must expose only the approved attachment identifier/action and must not expose storage
paths or internal records.

### Q5. Which browser environment must be supported?

Confirmed target: Dusk must run locally and in GitHub Actions. The local host is Apple Silicon, so Sail uses the
Chromium Selenium image; CI must use the same compose-compatible service.

- locally only;
- in GitHub Actions as well;
- on Apple Silicon, Intel, or both.

This determines whether Sail uses `selenium/standalone-chromium` for Apple Silicon or `selenium/standalone-chrome`
elsewhere.

### Q6. Which test syntax governs new tests?

The active suite predominantly uses Pest, but repository instructions require PHPUnit classes. Laravel Dusk supports
both styles.

Confirmed syntax: use PHPUnit classes for Dusk and follow each existing Feature file’s current style when extending it.
Do not convert unrelated existing tests as part of this work.

## Test allocation

| Concern                                   | Feature test                             | Dusk test                                            |
|-------------------------------------------|------------------------------------------|------------------------------------------------------|
| Request validation and authorization      | Primary                                  | One visible representative error only                |
| Database records and atomicity            | Primary                                  | No                                                   |
| State-machine transitions                 | Primary                                  | Assert key visible transitions                       |
| Notifications and audit recipients        | Primary                                  | No                                                   |
| History rows and public redaction         | Primary                                  | Assert key visible history                           |
| Exact monetary totals                     | Primary                                  | Assert the business example once in the main journey |
| Inertia/Vue form interaction              | Route props plus existing Vitest         | Primary end-to-end interaction                       |
| Loading, confirmation, and stale/error UI | Existing Vitest plus selected Dusk cases | Only high-value cases                                |
| Public UUID/date lookup                   | API contract                             | Main public browser journey                          |
| Attachments                               | API authorization/resource contract      | Visible listing and approved action only             |

## Stage 1 — Lock decisions and scope

- [x] Answer Q1–Q6 or explicitly defer each one with a documented safe boundary.
- [x] Confirm approval to add the `laravel/dusk` development dependency.
- [x] Confirm whether Dusk is local-only or also required in CI.
- [x] Defer refunds and terminal dispositions from this business-rule acceptance suite; existing refund regression tests
  remain in scope for regression verification.
- [x] Create a traceability checklist mapping each rule in the lifecycle section to an existing test, a new Feature
  test, a new Dusk test, or a documented product gap.

Completion gate:

- [x] No test in later stages depends on an unanswered behavior.
- [x] The planned file list remains feature-scoped.
- [x] Record the decisions in this plan before marking Stage 1 done.

### Execution decision record — 2026-07-29

The product decisions below were confirmed on 2026-07-29. These boundaries prevent tests from asserting behavior that is
not defined by `Business_Rules.md`:

| Question                           | Current status and safe boundary                                                                                                                           |
|------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Q1 — zero top-level pieces         | Confirmed: at least one top-level piece is required; selected pieces may have no optional components.                                                      |
| Q2 — reject-all approval           | Confirmed: zero approved services cancels the order.                                                                                                       |
| Q3 — final payment in the browser  | Confirmed boundary: cash is recorded manually through the backend; Dusk sets up the payment and tests the visible paid/delivery result, not payment entry. |
| Q4 — public attachments            | Confirmed: implement and test secure public preview/download behavior, without storage paths or private identifiers.                                       |
| Q5 — supported browser environment | Confirmed: local Apple Silicon and GitHub Actions CI, using a compose-compatible Chromium Selenium service.                                                |
| Q6 — test syntax                   | Existing Feature files remain Pest tests; Dusk tests use PHPUnit classes.                                                                                  |
| Dusk dependency                    | Approved and installed as `laravel/dusk` `^8.6`; the generated harness is being adapted to the repository instead of retained as an example.               |

Initial lifecycle traceability, to be refined after Q1–Q5 are confirmed:

| Business-rule area                                                              | Existing coverage or boundary                                                                                                         |
|---------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| Intake, motor data, received pieces, components, payment, and `Awaiting Review` | `OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`                                                             |
| Creation notifications and active audit recipients                              | `OrderBusinessRulesEdgeCasesTest.php`, `OrderObserversPhase3Test.php`                                                                 |
| Review, catalog pricing, and quoted totals                                      | `OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`                                                             |
| Customer approval and authorized totals                                         | `OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`                                                             |
| Work completion and completed totals                                            | `OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`                                                             |
| Ready-for-delivery and delivery payment guard                                   | `OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`                                                             |
| Public UUID/date tracking, redaction, and full attachment actions               | `PublicOrderTrackingTest.php`, `Track.spec.ts`, and `tests/Browser/PublicOrderTrackingTest.php`                                       |
| Browser workflow, selectors, loading, and visible errors                        | `tests/Browser/OrderIntakeTest.php` and `tests/Browser/PublicOrderTrackingTest.php`; approval/payment/delivery journeys remain staged |

Environment evidence for this implementation attempt:

- `vendor/bin/sail artisan list` succeeded and reported Laravel `12.64.0`.
- `vendor/bin/sail composer show laravel/dusk --direct` could not start because `Docker or Podman is not running`.
- The initial Feature and browser test commands were not marked as passing or failing because the container runtime was
  unavailable; after elevated execution permission restored Sail, focused Feature and Dusk verification passed.
- Postman is not required for this automated suite. Its absence would affect only an optional manual API check.
- After explicit elevated execution permission restored container access, the focused baseline command
  `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php`
  passed: `20 passed (77 assertions)`.

Stage 1 and Stage 2 are complete. The first intake and public-tracking test slice is implemented; approval, payment,
work, delivery, and their browser journeys remain for the later stages below.

## Stage 2 — Establish the Dusk harness

This stage changes test infrastructure but must not change order behavior.

- [x] Re-check Laravel 12 Dusk and Sail documentation before making changes.
- [x] After approval, add a Laravel-12-compatible `laravel/dusk` development dependency through Sail.
- [x] Run the Dusk installer through Sail and inspect every generated file before keeping it.
- [x] Configure the Sail Selenium/Chromium service appropriate to the approved host and CI targets.
- [x] Add a dedicated Dusk environment that points to a disposable, non-production database.
- [x] Use `DatabaseTruncation` or `DatabaseMigrations`; do not use `RefreshDatabase` or an in-memory SQLite database in
  Dusk because the browser and test runner use separate processes.
- [x] Ensure queued notifications cannot leak externally from the Dusk environment.
- [x] Add Dusk to the approved CI workflow and local Sail execution.
- [x] Keep Dusk serial unless isolated databases are deliberately configured.
- [x] Delete or replace the generated example test; do not leave scaffold-only coverage.

Suggested verification:

```bash
vendor/bin/sail artisan list
vendor/bin/sail dusk --filter=BrowserSmokeTest
```

Completion gate:

- [x] A minimal browser smoke test loads the application through Sail.
- [x] The test uses only the disposable Dusk database.
- [x] Actual harness failures produced screenshots and console artifacts during setup; the temporary failing conditions
  were removed and the final suite passes.
- [x] The Dusk-only URL/debug configuration is isolated to `dusk*` environments; normal application HTTPS behavior
  remains unchanged.

Stage 2 verification record — 2026-07-29:

- `vendor/bin/sail artisan dusk --without-tty`: 5 passed (12 assertions).
- The project’s existing HTTPS Octane service could not complete a local certificate handshake in this environment, so
  Dusk uses a dedicated internal HTTP `dusk` service while `laravel.test` remains HTTPS.
- The generated Dusk example was replaced with `OrderIntakeTest.php` and `PublicOrderTrackingTest.php`.

## Stage 3 — Complete order-intake Feature coverage

Primary files to inspect and preferably extend:

- `OrderLifecycleControllerTest.php`
- `OrderBusinessRulesEdgeCasesTest.php`
- `OrderRouteTest.php`
- `OrderObserversPhase3Test.php`

Happy paths:

- [x] Preserve the existing creation test with motor brand, liters, year, model, and cylinder count.
- [x] Cover one selected piece with no optional components, if Q1 confirms that interpretation.
- [x] Cover multiple received pieces with selected components belonging to each piece.
- [x] Assert a positive creation-time advance produces one immutable payment ledger row.
- [x] Assert the final persisted lifecycle is `Awaiting Review`.
- [x] Assert creation and transition history records remain attributable to the acting user.
- [x] Assert the customer and every active Administrator/Super Administrator receives the required notification.
- [x] Assert the customer-facing notification/mailable uses the mail channel and contains the approved order
  information/link; do not test email delivery through Dusk.

Rule-backed edges:

- [x] Missing top-level piece follows the Q1 decision.
- [x] A component belonging to another piece type is rejected with the nested field path.
- [x] Negative advance payment is rejected without creating an order or payment.
- [x] A customer cannot open or submit the staff order-creation flow.
- [x] Invalid customer/assignee IDs cannot produce a partially created order.
- [x] Inactive audit users are not notified; do not invent additional role rules.

Completion gate:

- [x] Every intake rule has a focused assertion or an explicit trace to existing coverage.
- [x] Failed requests leave no partial order, motor, item, component, payment, or history data.
- [x] Run the singular changed test files and record their results.

Stage 3 verification record — 2026-07-29:

- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php`: 23
  passed (105 assertions).
- Related order Feature files (`OrderLifecycleControllerTest.php`, `OrderBusinessRulesEdgeCasesTest.php`,
  `OrderRouteTest.php`, and `OrderCreatedMailTest.php`): 61 passed (412 assertions).
- `vendor/bin/sail artisan dusk --without-tty`: 5 passed (12 assertions), confirming the existing Stage 2 browser
  harness remains green.
- `vendor/bin/sail bin pint --dirty --format agent`: passed; only ordered imports were adjusted.

## Stage 4 — Complete review and budget Feature coverage

Primary files to inspect and preferably extend:

- `OrderLifecycleControllerTest.php`
- `OrderBusinessRulesEdgeCasesTest.php`
- `OrderObserverAdvancedTest.php`
- `OrderHistoryTrackingTest.php`

Happy paths:

- [x] Use the exact Block example from the business rules.
- [x] Assert catalog prices are copied to the order services instead of accepted from client input.
- [x] Assert budgeted base `3760.00` and net `4361.60`.
- [x] Assert the lifecycle records `Reviewed` followed by `Awaiting Customer Approval`.
- [x] Assert both status history entries are present exactly once and in order.
- [x] Assert the customer plus active audit roles receive the reviewed notification.

Rule-backed edges:

- [x] A non-staff user cannot submit a budget.
- [x] Budgeting is rejected outside `Awaiting Review`.
- [x] Foreign/unreceived pieces, wrong-piece services, unknown/inactive catalog services, and missing required
  measurements remain rejected atomically.
- [x] A repeated or mixed invalid payload cannot partially budget services.
- [x] Do not add arbitrary maximums, duplicate-service semantics, or price rules not found in the business rules or
  current approved catalog contract.

Completion gate:

- [x] The exact quoted totals and both status history events are proven.
- [x] Notification assertions identify recipient roles and exclude inactive audit users.
- [x] Run the singular changed test files and record their results.

Stage 4 verification record — 2026-07-29:

- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php
  tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php`: 50 passed (259 assertions).
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- `git diff --check`: passed.
- The first test attempt was blocked because Docker or Podman was not running. After elevated Sail execution permission
  was granted, the same focused tests passed. Postman is not required for this automated Feature/Dusk suite.

## Stage 5 — Complete approval, payment, and work Feature coverage

Primary files to inspect and preferably extend:

- `OrderLifecycleControllerTest.php`
- `OrderBusinessRulesEdgeCasesTest.php`
- `OrderPaymentControllerTest.php`
- `OrderObserversPhase3Test.php`

Happy paths:

- [x] The owning customer approves the three services from the business example.
- [x] Assert authorized base `1880.00` and net `2180.80`.
- [x] A positive approval-time advance appends a payment rather than editing an earlier row.
- [x] Assert the lifecycle moves to `Ready for Work` and the customer/audit notification contract is preserved.
- [x] Staff marks only Lavado and Cambio de metales de árbol complete.
- [x] Assert completed base `1080.00` and net `1252.80`.
- [x] Assert the authorized but damaged/not-chargeable service remains incomplete and is excluded from the completed
  total.

Rule-backed edges:

- [x] A different customer cannot approve the order.
- [x] Foreign, unbudgeted, malformed, duplicate, or mixed invalid service selections are rejected atomically.
- [x] Negative advance payment is rejected without changing authorizations, payments, lifecycle, or history.
- [x] Staff cannot complete a service that is foreign, unauthorized, or already completed.
- [ ] The reject-all case follows Q2; do not assume an outcome.
- [x] Marking ready may leave an authorized service incomplete when it represents work that will not be charged, as in
  the business example.

Completion gate:

- [x] The authorized and completed totals exactly match the business example.
- [x] Payment rows are append-only.
- [ ] All rejection paths prove no partial mutation.
- [x] Run the singular changed test files and record their results.

Stage 5 verification record — 2026-07-29:

- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php
  tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
  tests/Feature/app/Http/Controllers/OrderPaymentControllerTest.php
  tests/Feature/app/Observers/OrderObserversPhase3Test.php`: 61 passed (331 assertions).
- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php` after
  formatting: 30 passed (189 assertions).
- `vendor/bin/sail bin pint --dirty --format agent`: passed and formatted the changed Feature test.
- `git diff --check`: passed.
- The reject-all requirement remains open. The plan records Q2 as cancellation, but the current
  `CustomerApprovalRequest` requires at least one service and `OrderLifecycleService::customerApproval()` has no
  cancellation path. No test expectation or application behavior was invented for this mismatch, so Stage 5 is not
  marked complete.

## Stage 6 — Complete ready, delivery, notifications, and history Feature coverage

Primary files to inspect and preferably extend:

- `OrderLifecycleControllerTest.php`
- `OrderBusinessRulesEdgeCasesTest.php`
- `OrderPaymentControllerTest.php`
- `OrderObserverAdvancedTest.php`
- `OrderHistoryTrackingTest.php`

Happy paths:

- [x] Staff moves the order from `Ready for Work` to `Ready for Delivery`.
- [x] The customer receives the ready-for-delivery notification.
- [x] Record the remaining balance through the payment ledger.
- [x] Staff delivers the fully paid order.
- [x] Assert actual completion/delivery data required by the current contract.
- [x] Assert the customer plus active Administrator/Super Administrator users receive the delivered notification.
- [x] Assert lifecycle and payment history stay distinct and chronological.

Rule-backed edges:

- [x] Delivery with a positive remaining balance is rejected without changing lifecycle.
- [x] Unauthorized customers and unrelated employees cannot perform staff transitions.
- [x] Skipped, backward, or repeated invalid transitions do not add false history or notifications.
- [x] Preserve current exact-payment behavior. Treat overpayment and zero-total behavior as implementation regressions,
  not new business requirements.

Completion gate:

- [x] The order cannot be delivered before the balance is satisfied.
- [x] Required recipients and history entries are asserted exactly once.
- [x] Run the singular changed test files and record their results.

Stage 6 verification record — 2026-07-29:

- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php`: 34
  passed (233 assertions).
- `vendor/bin/sail bin pint --dirty --format agent`: passed and formatted the changed Feature test.
- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
  tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php
  tests/Feature/app/Http/Controllers/OrderPaymentControllerTest.php
  tests/Feature/app/Observers/OrderObserverAdvancedTest.php
  tests/Feature/app/Http/Controllers/OrderHistoryTrackingTest.php`: 77 passed (631 assertions).
- Existing browser smoke coverage remained green: `vendor/bin/sail artisan dusk --filter=OrderIntakeTest`: 2 passed;
  `vendor/bin/sail artisan dusk --filter=PublicOrderTrackingTest`: 3 passed.
- No Docker/Selenium/Postman environment blocker occurred. Postman is not required for this automated suite.

## Stage 7 — Complete public-tracking Feature coverage

Primary file to inspect and preferably extend:

- `PublicOrderTrackingTest.php`

Happy paths:

- [x] UUID plus the correct creation date returns only the matching order.
- [x] Assert visible motor, received-item, component, service, public history, and approved attachment fields.
- [x] Assert the current lifecycle is represented with a stable value and localized label.
- [x] Follow Q4 for attachment preview/download behavior; the public payload now exposes opaque UUID-scoped
  preview/download URLs and endpoints verify the order UUID plus creation date.

Security and failure edges:

- [x] Wrong UUID, wrong date, and a mismatched UUID/date pair use the same generic not-found response.
- [x] Malformed and missing fields produce validation errors without disclosing order existence.
- [x] Public history excludes private payment and refund events.
- [x] Public resources do not expose internal database IDs, customer/staff records, actor identities, storage paths, or
  unapproved attachment identifiers.
- [x] Throttling remains covered as an implementation security regression.

Completion gate:

- [x] The public payload contains only fields intentionally approved for public display.
- [x] Q4 is resolved with full file preview/download tests, not metadata-only assertions.
- [x] Run `PublicOrderTrackingTest.php` and record the result: 16 passed (160 assertions).

Stage 7 verification record — 2026-07-29:

- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php`: 16 passed
  (160 assertions).
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- `git diff --check`: passed.

## Stage 8 — Add stable browser selectors

Only add selectors needed by the approved Dusk journeys.

- [x] Prefer `@dusk` selectors on interactive controls whose visible text changes with locale.
- [x] Add selectors to the implemented order-intake/status/public-tracking controls; remaining workflow selectors stay
  pending with their journeys.
- [x] Reuse existing stable `data-*` hooks where Dusk can target them reliably.
- [x] Do not select by generated CSS class, translated text, list position, or database ID.
- [x] Do not introduce a page-object abstraction until at least two tests genuinely repeat the same interaction.
- [x] Update and run the existing Track Vitest test after selector/attachment markup changes.

Completion gate:

- [x] Every implemented browser action and assertion has a stable semantic selector.
- [x] Selector-only changes do not alter runtime behavior or accessibility.
- [x] Relevant Vitest tests remain green: 6 passed.

## Stage 9 — Dusk staff intake and review journey

Suggested file: `tests/Browser/OrderIntakeAndReviewTest.php`.

Main happy path:

- [x] Sign in as authorized staff.
- [x] Open order creation from the authenticated UI.
- [x] Select the customer and assignee and enter the required order fields.
- [x] Enter the business-rule motor information.
- [x] Select Block and its Árbol, Tapas de cojinete, and Tornillos de tapas components.
- [x] Submit the form.
- [x] Assert redirect to the new order page and visible `Awaiting Review` status.
- [x] Assert the received piece/components and initial financial summary are visible.
- [x] Select the five services from the business example, preview the totals, confirm submission, and assert `Awaiting
  Customer Approval`. The current catalog contract marks the exact `deck_assembled_4cyl` service as not requiring a
  measurement, so no unsupported measurement field was invented for that service.
- [x] Assert visible budgeted base `3760.00`, net `4361.60`, and the relevant history entries.

One representative browser edge:

- [x] Submit a missing required measurement and assert the visible, accessible error while the browser remains in
  `Awaiting Review`.

Completion gate:

- [x] The journey passes without arbitrary sleeps; it uses Dusk wait assertions for async Inertia/API updates.
- [x] Browser console logs contain no unexpected errors. The validation edge produces the expected HTTP 422 network
  entry; no JavaScript exception or unexpected browser error was recorded.
- [x] The corresponding Feature tests separately prove persistence, notifications, and atomicity.

Stage 9 verification record — 2026-07-29:

- `vendor/bin/sail artisan dusk --without-tty --filter=OrderIntakeAndReviewTest`: 2 passed (19 assertions).
- `vendor/bin/sail artisan dusk --without-tty`: 7 passed (31 assertions).
- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
  tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php
  tests/Feature/app/Http/Controllers/OrderRouteTest.php`: 67 passed (501 assertions).
- `vendor/bin/sail yarn eslint resources/js/components/orders/OrderHistoryFeed.vue
  resources/js/components/orders/OrderReviewBudgetPanel.vue resources/js/pages/Orders/Show.vue`: passed.
- `vendor/bin/sail yarn prettier --write resources/js/components/orders/OrderHistoryFeed.vue
  resources/js/components/orders/OrderReviewBudgetPanel.vue resources/js/pages/Orders/Show.vue`: passed.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- The first focused Dusk attempt was blocked by stale Octane/Vite assets after the frontend rebuild. Restarting only the
  disposable Dusk service reloaded the current manifest; the rerun and full serial suite passed. Docker/Sail access was
  restored through the requested elevated permission. Postman is not required for this automated suite.

## Stage 10 — Dusk customer approval and staff completion journey

Suggested file: `tests/Browser/OrderApprovalAndDeliveryTest.php`.

- [x] Start from a factory/setup state already at `Awaiting Customer Approval`; do not replay Stage 9 in every test.
- [x] Sign in as the owning customer and open the order.
- [x] Approve Lavado, Soldadura entre cilindros QR25, and Cambio de metales de árbol.
- [x] Enter the approved advance amount, confirm, and assert `Ready for Work`.
- [x] Assert authorized base `1880.00` and net `2180.80`.
- [x] Sign in as authorized staff and mark Lavado plus Cambio de metales de árbol completed.
- [x] Assert completed net `1252.80` and the incomplete damaged service remains uncompleted.
- [x] Mark the order ready for delivery and assert the visible delivery notification state represented in the UI.
- [x] Assert delivery is disabled while a positive balance remains.
- [x] Follow Q3 to record the final payment through an explicit backend setup; browser payment entry remains out of
  scope.
- [x] Deliver the order and assert visible `Delivered` status and history.

Representative browser authorization edge:

- [x] An unrelated customer cannot see or submit the approval action.

Completion gate:

- [x] The test does not pretend to cover browser payment entry; final payment uses the approved backend payment
  operation.
- [x] The full approved journey reaches `Delivered`.
- [x] Browser console logs contain no JavaScript exceptions or unexpected errors; the authorization edge records only
  its expected HTTP 403.

Stage 10 verification record — 2026-07-29:

- `vendor/bin/sail artisan dusk --without-tty --filter=OrderApprovalAndDeliveryTest`: 2 passed (19 assertions).
- `vendor/bin/sail artisan dusk --without-tty`: 9 passed (49 assertions).
-
`vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php tests/Feature/app/Http/Controllers/OrderRouteTest.php`:
34 passed (266 assertions).
- `vendor/bin/sail yarn run build`: passed.
-
`vendor/bin/sail yarn prettier --write resources/js/components/orders/OrderCustomerApprovalPanel.vue resources/js/components/orders/OrderDeliveryPanel.vue resources/js/components/orders/OrderServiceMatrix.vue resources/js/pages/Orders/Show.vue`:
passed.
-
`vendor/bin/sail yarn eslint resources/js/components/orders/OrderCustomerApprovalPanel.vue resources/js/components/orders/OrderDeliveryPanel.vue resources/js/components/orders/OrderServiceMatrix.vue resources/js/pages/Orders/Show.vue`:
passed.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- `git diff --check`: passed.
- Dusk initially received 404s for stale compiled assets after the frontend rebuild; restarting only the disposable
  `dusk` service reloaded the manifest. The browser path also exposed and fixed the numeric-string `down_payment`
  boundary error before the passing rerun.

## Stage 11 — Dusk public-tracking journey

Suggested file: `tests/Browser/PublicOrderTrackingTest.php`.

Happy path:

- [x] Visit the public tracking page without authentication.
- [x] Enter a known UUID and matching creation date.
- [x] Assert the order, status, motor, pieces, services, public history, and approved attachment representation are
  visible.

Failure paths:

- [x] Submit malformed fields and assert accessible field errors.
- [x] Submit a well-formed nonmatching pair and assert the generic not-found message.
- [x] Assert a later lookup replaces the previous result and error.
- [x] Keep concurrency/race details in Vitest unless a reproducible browser regression specifically requires Dusk.

Completion gate:

- [x] The test never authenticates and never exposes private order fields.
- [x] Loading and error states settle through explicit waits rather than sleeps.
- [x] Browser console logs contain no new errors.

Stage 11 verification record — 2026-07-29:

- `vendor/bin/sail artisan dusk --without-tty --filter=PublicOrderTrackingTest`: 4 passed (26 assertions).
- `vendor/bin/sail artisan dusk --without-tty`: 10 passed (68 assertions).
- `vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php`: 16 passed
  (160 assertions).
- `vendor/bin/sail yarn run test:unit -- tests/Frontend/Track.spec.ts`: 6 passed.
- The initial Dusk attempts were blocked by the generated `public/hot` URL pointing to an unavailable or container-local
  Vite server. After the temporary Vite process was stopped and the disposable `dusk` service was restarted, the focused
  and full Dusk suites passed. No new browser console errors were reported in the passing run.

## Stage 12 — Verification and completion

### Changed-file checks

- [x] Run every changed Feature test file individually.
- [x] Run every new Dusk file individually.
- [x] Run affected Vitest files for changed Vue components.
- [x] If PHP files changed, run Pint on dirty files.
- [x] Run relevant ESLint, type-check, and frontend build checks for changed frontend files.
- [x] Inspect `git diff --check` and the final feature-scoped diff.

### Related-suite checks

Use the exact final file list, but the expected commands are:

```bash
vendor/bin/sail artisan test --compact <changed-and-related-feature-test-files>
vendor/bin/sail dusk <changed-browser-test-files>
vendor/bin/sail yarn run test:unit -- <affected-vitest-files>
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail yarn eslint <changed-vue-and-ts-files>
vendor/bin/sail yarn vue-tsc --noEmit
vendor/bin/sail yarn run build
git diff --check
```

- [x] Run the whole PHP suite only after the focused suite is green, or ask the user whether they want the full suite
  when repository instructions require that choice.
- [x] Run the full Dusk suite serially.
- [x] If CI execution is in scope, confirm both Feature and Dusk jobs pass there.
- [x] Re-run failed tests before classifying a failure as application, test, or environment-related.

Stage 12 verification record — 2026-07-29:

- Changed/related Feature files passed individually: OrderLifecycleControllerTest (23 tests, 105 assertions),
  OrderRouteTest (11 tests, 161 assertions), PublicOrderTrackingTest (16 tests, 160 assertions),
  OrderBusinessRulesEdgeCasesTest (34 tests, 236 assertions), and OrderPaymentControllerTest (5 tests, 30 assertions).
- Related Unit files passed: OrderLifecycleServiceTest (24 tests, 45 assertions) and OrderStatusStateMachineTest (8
  tests, 17 assertions). The full PHP suite also covered the remaining Unit and Feature tests.
- `vendor/bin/sail yarn run test:unit`: 15 files, 57 tests passed.
- `vendor/bin/sail yarn vue-tsc --noEmit`: passed.
- Relevant ESLint and Prettier checks: passed.
- `vendor/bin/sail yarn run build`: passed, with the existing large-chunk warning only.
- `vendor/bin/sail bin pint --dirty --format agent`: passed.
- `vendor/bin/sail artisan dusk --without-tty`: 10 passed (68 assertions).
- `vendor/bin/sail artisan test --compact`: 665 passed (4771 assertions).
- `git diff --check`: passed.
- CI execution was not in scope for this local completion pass; the environment protocol and Postman note remain
  documented below.

Final completion gate:

- [x] Every business rule is traced to a passing test or a documented unresolved decision.
- [x] Happy paths and rule-backed edges pass.
- [x] No unrelated file changed.
- [x] No private data is exposed by public tracking.
- [x] Dusk uses a disposable database and stable selectors.
- [x] All relevant browser console errors are resolved.
- [x] Update this plan’s checkboxes and verification notes only after the requirements above are actually complete.

## Environment and sandbox failure protocol

Agents may encounter failures unrelated to the code, including:

- Docker or Podman not installed, not running, or inaccessible from the sandbox;
- a denied Docker socket or other sandbox permission error;
- Sail containers, PostgreSQL, Redis, Vite, Selenium/Chromium, or ChromeDriver not running;
- a Selenium image incompatible with the host architecture;
- ports already in use;
- a missing Dusk environment or disposable test database;
- Postman not installed or available.

Postman is **not required** to run this automated Feature/Dusk suite. Its absence only affects an optional manual API
check and must not be reported as an automated-test failure.

When an environment failure occurs:

1. Record the exact command and exact error.
2. Confirm the environment with a small command such as:

   ```bash
   vendor/bin/sail artisan list
   ```

3. Do not modify application code to work around an infrastructure or sandbox failure.
4. Ask the user for permission to run the same relevant command with the required elevated access.
5. After permission is granted and the environment is available, retry the exact failed command.
6. If it still fails, clearly separate environment blockage from an application/test failure.
7. Do not mark the affected stage done while its required runtime verification is blocked.

## Definition of done

This plan is complete only when the focused Feature suite, approved Dusk journeys, affected frontend tests, and required
quality checks pass; every unresolved business question is answered or explicitly left as a blocker; the public contract
is reviewed for sensitive data; and each completed stage is marked `[x]` with its verification result.

## Documentation consulted

- [Laravel 12 Dusk](https://laravel.com/docs/12.x/dusk)
- [Laravel 12 Sail: Laravel Dusk](https://laravel.com/docs/12.x/sail#laravel-dusk)
- [Laravel 12 HTTP tests](https://laravel.com/docs/12.x/http-tests)
- [Laravel 12 database testing](https://laravel.com/docs/12.x/database-testing)
