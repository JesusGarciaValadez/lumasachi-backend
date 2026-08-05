# Order Price Presentation Refinement Plan

## Objective

Refine the user-visible monetary information in order workflow steps 2 and 3 and, once the scope question below is
confirmed, the other order screens that reuse the same pricing components.

The allowed monetary labels are:

- Net price
- Net total
- Budgeted total
- Authorized total
- Completed total

The following monetary labels and values must not be shown:

- Base price
- Base total
- Advance payment
- Remaining balance

This is a presentation requirement. The current evidence does not authorize changing stored prices, payment records,
delivery rules, API payloads, or financial calculations.

## Priority criterion

**Remove user-visible disallowed amounts first, then simplify internal presentation contracts, then prove the
workflow.**

## Business-rule boundary

`docs/Business_Rules.md` establishes the following domain behavior:

- A budget is calculated after staff review and is followed by customer authorization (`Business_Rules.md:158-190`).
- An advance payment may be entered during customer authorization (`Business_Rules.md:192-193`).
- Delivery occurs after the customer pays the outstanding amount (`Business_Rules.md:211-215`).

The requested visibility rule is narrower than those domain rules and does not say that payments or balance enforcement
should be removed. Therefore, implementation must preserve the backend pricing/payment model unless a separate business
decision explicitly changes it.

## Current-code findings

### Workflow naming

- `resources/js/pages/Orders/Create.vue` is the intake form and currently has no price fields or numbered steps.
- The code and lifecycle tests identify step 2 as budget submission and step 3 as customer approval
  (`tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php:502-517`).
- The relevant UI is rendered from `resources/js/pages/Orders/Show.vue` through
  `OrderReviewBudgetPanel.vue` and `OrderCustomerApprovalPanel.vue`.

### Group A — Step 2: review and budget

`resources/js/components/orders/OrderReviewBudgetPanel.vue` currently:

- shows both base price and net price for every service;
- calculates and displays both base total and net total;
- emits both totals to the confirmation-dialog state;
- causes `Orders/Show.vue` to repeat both totals in the confirmation dialog.

The server remains authoritative for submitted prices: the step submits service identifiers and measurements, not
client-supplied prices. Hiding base values must not change that request contract.

### Group B — Step 3: customer approval

`resources/js/components/orders/OrderCustomerApprovalPanel.vue` currently:

- shows both base price and net price for each budgeted service;
- displays budgeted base/net totals and authorized base/net totals;
- exposes the advance-payment input;
- emits the four preview totals and the advance payment to `Orders/Show.vue`;
- causes the confirmation dialog to repeat authorized base total, authorized net total, and advance payment.

The advance-payment input is the only current browser UI found for recording payment during approval. Removing it
without an approved replacement changes an operational capability, not only a label.

### Group C — Shared order price surfaces

The phrase “all places where prices is shown” reaches beyond the two workflow panels in the current component graph:

| Surface                                                                 | Current disallowed display                                       | Allowed display already present                   |
|-------------------------------------------------------------------------|------------------------------------------------------------------|---------------------------------------------------|
| `OrderFinancialSummary.vue` on authenticated detail and public tracking | Base total, advance payment, remaining balance                   | Net total, budgeted, authorized, completed totals |
| `OrderServiceMatrix.vue` on completion/read-only views                  | Base price                                                       | Net price, completed total                        |
| `OrderDeliveryPanel.vue`                                                | Numeric remaining balance                                        | Non-monetary payment-required warning             |
| `Orders/Show.vue` confirmation dialogs                                  | Base total, advance payment, remaining balance                   | Net/authorized/completed totals                   |
| `Orders/Track.vue`                                                      | Advance payment and remaining balance through the shared summary | Net service prices and stage totals               |
| `Orders/EngineOptions.vue` catalog table                                | Base price                                                       | Net price                                         |

Payment status badges and a non-monetary “payment required” warning are not prices. They can remain unless the product
decision intends to hide payment state as well as monetary amounts.

### Data that should remain unless separately requested

- `base_price` and `net_price` on catalog/order-service models and resources;
- `budgeted_base`, `budgeted_net`, `advance_payment`, and `remaining_balance` in the backend financial calculation;
- payment-ledger behavior and the remaining-balance delivery guard;
- request validation and lifecycle transitions.

Removing those contracts would broaden this task into a backend/payment migration and is not supported by the request.

## Decisions required before implementation

### D1 — Advance-payment entry (blocking step 3)

Should the step-3 advance-payment input itself be removed?

- If **yes**, customer approval should submit only `authorized_service_ids`; decide where staff records payments before
  delivery, because no separate browser payment-entry UI was found.
- If **no**, the input remains visible and conflicts with the literal instruction not to show “Advance payment.” A more
  precise exception would need to be documented.

No implementation agent should invent a replacement payment workflow under this plan.

Current implementation status: the user explicitly requested removal. The advance-payment input, validation display,
local state, emitted value, and frontend `down_payment` payload field were removed. The backend optional field and
payment ledger remain available to preserve documented server/API behavior; no replacement payment-entry workflow was
invented.

### D2 — Meaning of “all places” (blocks shared-surface scope)

Confirm whether the rule applies to the entire order UI, including public tracking, delivery, read-only service tables,
and the engine-options catalog. The recommended interpretation is **yes**, because those screens reuse or expose the
same monetary concepts. If the intended scope is only workflow steps 2 and 3, Group C must remain unchanged.

The user subsequently expanded the scope to the entire order UI for every role. Group C is therefore in scope, including
authenticated detail, public tracking, delivery, read-only/completion service tables, and the engine-options catalog.

### D3 — API visibility (non-blocking for UI-only work)

Confirm whether “not show” means only “do not render” or also “do not send in API responses.” This plan assumes
rendering only. Removing fields from authenticated or public resources requires a separate contract audit and backend
tests.

This implementation accepts the UI-only interpretation. Backend resources, API payloads, stored prices, and financial
calculations remain unchanged.

## Dependency-ordered implementation plan

### Phase 0 — Fix the presentation contract

- [x] Map the business-rule pricing/payment requirements to the current UI.
- [x] Identify the actual step-2 and step-3 components and every directly reused monetary display.
- [x] Record the conflicts and unknowns instead of choosing new payment behavior.
- [x] Resolve D1: remove the advance-payment UI/payload field without inventing a replacement payment workflow.
- [x] Resolve D2: shared order surfaces for every role are in scope.
- [x] Resolve D3: the requirement is UI rendering only; API response redaction is out of scope.

**Completion gate:** D1 and D2 have explicit answers; D3 is answered or the UI-only assumption is accepted.

Current status: the gate is closed; D1 and D2 are explicitly resolved and D3 remains UI-only.

### Phase 1 — Establish red frontend presentation tests

Depends on Phase 0.

- [x] Update `tests/Frontend/OrderReviewBudgetPanel.spec.ts` to require net price/net total and reject rendered base
  price/base total.
- [x] Update `tests/Frontend/OrderCustomerApprovalPanel.spec.ts` to require budgeted/authorized totals calculated from
  net prices and reject all base values.
- [x] Encode the D1 decision: assert that the advance-payment control and emitted value are absent while preserving the
  backend payment contract.
- [x] Update the `Orders/Show.vue` confirmation coverage so budget confirmation shows only net total, approval
  confirmation shows only authorized total, and no disallowed monetary label/value appears.
- [x] Add shared-surface tests for financial summary, delivery, public tracking, and read-only service presentation.
- [x] Run only these focused tests and record the expected failures as the TDD red gate; distinguish assertion failures
  from Docker/Sail setup failures.

**Completion gate:** every selected surface has a failing test for its new presentation contract, and the failures are
caused by the current disallowed rendering.

The original red gate produced six expected assertion failures and four passing tests before implementation. The
selected price-presentation and D1 tests now pass.

### Phase 2 — Refine step 2: review and budget

Depends on Phase 1.

- [x] Remove the base-price column and its responsive mobile label from `OrderReviewBudgetPanel.vue`.
- [x] Remove base-total calculation, label props, emitted confirmation data, and duplicate footer/summary output.
- [x] Keep net price per service and one net total for the current selection.
- [x] Update `Orders/Show.vue` label wiring and budget confirmation to accept only `netTotal` and display “Net total.”
- [x] Preserve the existing service selection, measurement, notes, validation, busy state, and budget request payload.
- [x] Run the focused review-panel and confirmation tests until green.

**Completion gate:** step 2 shows only net price/net total, submits the same server-owned budget request, and its
focused tests pass.

### Phase 3 — Refine step 3: customer approval

Depends on Phase 1 and D1.

- [x] Remove the base-price column and base-total calculations/props/emitted values from
  `OrderCustomerApprovalPanel.vue`.
- [x] Show each service's net price.
- [x] Replace the four preview totals with exactly “Budgeted total” and “Authorized total,” both calculated from net
  prices.
- [x] Apply D1 without inventing payment behavior: delete the advance-payment control, error presentation, local state,
  emitted value, and `down_payment` field from the frontend approval payload.
- [x] Update `Orders/Show.vue` label wiring and approval confirmation to show only “Authorized total.”
- [x] Preserve budgeted-service filtering, customer selection, validation, busy state, and authorization submission.
- [x] Run the focused approval-panel and confirmation tests until green.

**Completion gate:** step 3 shows only net price, budgeted total, and authorized total; payment handling matches the
explicit D1 decision; focused tests pass.

The step-3 presentation and D1 completion gates are complete; backend payment recording remains unchanged.

### Phase 4 — Align shared order surfaces

Depends on D2 and Phases 2-3.

**Status: implemented.** Group C is in scope for every order role and view.

- [x] Simplify `OrderFinancialSummary.vue` to render only allowed totals and a non-monetary payment-status badge.
- [x] Remove base price from `OrderServiceMatrix.vue`; retain net price and completed total.
- [x] Stop rendering the numeric remaining balance in `OrderDeliveryPanel.vue`, but keep balance data internally for
  disabling delivery and keep the non-monetary payment-required warning.
- [x] Reduce delivery confirmation in `Orders/Show.vue` to completed total plus non-price order context.
- [x] Verify `Orders/Track.vue` shows only net service prices and the allowed stage totals.
- [x] Remove base price from `Orders/EngineOptions.vue`.
- [x] Remove presentation-only label props, selectors, and translation usages that have no remaining consumer. Backend
  fields and shared TypeScript data fields remain unchanged.

**Completion gate:** an application-wide search of render templates finds no disallowed price labels/values in the
approved UI scope, while payment/delivery rules still consume their underlying data.

### Phase 5 — Verification and handoff

Depends on all in-scope implementation phases.

- [x] Run focused Vitest files through Sail:
  `vendor/bin/sail yarn run test:unit -- tests/Frontend/OrderReviewBudgetPanel.spec.ts tests/Frontend/OrderCustomerApprovalPanel.spec.ts tests/Frontend/OrderShowBudgetApproval.spec.ts`.
- [x] Run `vendor/bin/sail yarn vue-tsc --noEmit`.
- [x] Run ESLint for only the changed Vue/TypeScript/test files.
- [x] Run Prettier check for only the changed Vue/TypeScript/test files.
- [x] Run `vendor/bin/sail yarn run build`.
- [ ] Run the existing focused Dusk journeys that cover budget/customer approval and public tracking if their assertions
  are changed. Do not claim browser verification if Dusk is not run.
- [ ] Manually inspect step 2 and step 3 at mobile and desktop widths in both Spanish and English, checking table
  alignment and confirmation text.
- [x] Re-run a source search for rendered base price/base total/advance payment/remaining balance in the confirmed
  scope.
- [x] Record Docker, Sail, Selenium, or browser setup failures separately from product/test failures and rerun the exact
  blocked command after the environment recovers.
- [x] Run the focused backend lifecycle feature tests through Sail.
- [x] Run `vendor/bin/sail bin pint --dirty --format agent`.
- [ ] Run `vendor/bin/sail composer run test:types`; the command reported 59 errors in existing app paths outside the
  changed production files.

**Completion gate:** focused automated checks pass, required browser/manual checks are recorded accurately, and no
disallowed monetary value is visible in the confirmed scope.

## Expected file scope

Core step-2/step-3 files:

- `resources/js/components/orders/OrderReviewBudgetPanel.vue`
- `resources/js/components/orders/OrderCustomerApprovalPanel.vue`
- `resources/js/pages/Orders/Show.vue`
- `tests/Frontend/OrderReviewBudgetPanel.spec.ts`
- `tests/Frontend/OrderCustomerApprovalPanel.spec.ts`
- focused `Orders/Show.vue` confirmation coverage

Conditional shared-surface files if D2 is application-wide:

- `resources/js/components/orders/OrderFinancialSummary.vue`
- `resources/js/components/orders/OrderServiceMatrix.vue`
- `resources/js/components/orders/OrderDeliveryPanel.vue`
- `resources/js/pages/Orders/Track.vue`
- `resources/js/pages/Orders/EngineOptions.vue`
- corresponding focused frontend/browser tests and presentation-only i18n wiring

Backend models, services, resources, migrations, and database schema are outside the current plan unless D3 expands the
requirement.

## Implementation verification record

- Group A (step 2), Group B (step 3), and the shared Group C presentation slices were delegated to separate sub-agents
  after dependency analysis identified disjoint component/test work that could run in parallel; `Show.vue` wiring,
  cross-surface integration, and final verification were serialized in the main thread.
- TDD red gate: the three focused frontend files produced six expected presentation assertion failures and four passes
  before the production changes.
- Focused frontend green gate: 8 files and 43 tests passed serially.
- Full frontend unit verification: 21 files and 97 tests passed with
  `vendor/bin/sail yarn run test:unit --no-file-parallelism --maxWorkers=1`. The default parallel run had 51 passing
  tests but seven worker-start timeouts under container resource pressure.
- Type and style verification passed through Sail: `vue-tsc`, targeted ESLint, targeted Prettier, the production build,
  `git diff --check`, and Pint. The build retained the existing large-chunk warning.
- Focused backend lifecycle verification passed: 3 feature tests and 22 assertions.
- PHPStan was run with `vendor/bin/sail composer run test:types` and reported 59 errors in existing `app/` paths; no
  changed production PHP file is among those reported paths. The changed Dusk tests are not included by the configured
  PHPStan paths.
- Dusk setup initially failed because the Selenium service was unavailable/name resolution failed. Starting the
  configured `selenium` service restored WebDriver connectivity. The latest focused retry with built assets ran the
  updated order journey: 1 test failed and 1 passed; the main journey timed out before assertions because
  `@order-approval-panel` was not rendered, matching the broader blank-page/app-rendering failures. This is not recorded
  as product verification.
- Manual mobile/desktop and Spanish/English inspection was not performed in this environment.
- No backend models, services, resources, migrations, API contracts, payment records, or financial calculations were
  changed. The full backend test suite was not run.
