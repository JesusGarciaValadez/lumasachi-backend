# Backend Business Rules Gap Remediation Plan

## Purpose

Use this document as a handoff plan for completing the backend gaps identified by comparing `docs/Business_Rules.md`
with the registered routes, controllers, requests, resources, observers, services, and PHPUnit tests.

This plan covers backend steps 1–6 only. The public Inertia/Vue tracking form is intentionally deferred to step 8 until
these backend behaviors are complete.

## Scope and constraints

- Work only in `lumasachi-backend`.
- Do not modify `docs/Business_Rules.md`.
- Do not modify any file in `lumasachi-react-native`.
- Preserve the existing Laravel 12, PHPUnit, Form Request, Resource, observer, and service conventions.
- Work test-first from the specifications already added in:
    - `tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php`
    - `tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php`
- For each step: implement the smallest coherent production change, run the focused tests, fix failures until green,
  then run PHPUnit, Pint and PHPStan before moving on.
- Do not run the intentionally failing new specifications before implementing the corresponding step.

## Existing test specifications

The new specifications define these expected behaviors:

| Step | Specification                                                                         | Test location                                                                                                                                                                                                                   |
|------|---------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 1    | Public tracking serializes order history and attachments                              | `PublicOrderTrackingTest::it_returns_the_order_history_and_attachments`                                                                                                                                                         |
| 2    | Budget, approval, and completion IDs belong to the route order                        | `OrderBusinessRulesEdgeCasesTest::budget_rejects_an_item_that_belongs_to_another_order`; `customer_approval_rejects_a_service_that_belongs_to_another_order`; `work_completion_rejects_a_service_that_belongs_to_another_order` |
| 3    | Work can only be marked completed for authorized services                             | `OrderBusinessRulesEdgeCasesTest::work_completion_rejects_a_service_that_is_not_authorized`                                                                                                                                     |
| 4    | Components are valid for their selected item type                                     | `OrderBusinessRulesEdgeCasesTest::order_creation_rejects_a_component_from_a_different_item_type`                                                                                                                                |
| 5    | Delivery requires no remaining balance                                                | `OrderBusinessRulesEdgeCasesTest::delivery_requires_the_remaining_balance_to_be_paid`                                                                                                                                           |
| 6    | The automatic Reviewed → Awaiting Customer Approval transition is recorded in history | `OrderBusinessRulesEdgeCasesTest::reviewed_to_awaiting_customer_approval_records_both_status_changes`                                                                                                                           |

## Step 1 — Serialize public tracking history and attachments

### Business requirement

When a user supplies an order UUID and creation date, the response must include that order’s information, historical
changes, and attachments.

### Likely implementation areas

- `app/Http/Controllers/PublicOrderController.php`
- `app/Http/Resources/OrderResource.php`
- `app/Http/Resources/OrderHistoryResource.php`
- `app/Http/Resources/AttachmentResource.php`

### Implementation checklist

1. Eager-load `orderHistories` and `attachments` in the public lookup query.
2. Load any nested relationships required by the resources, such as history creators and attachment uploaders.
3. Add conditional `history` and `attachments` fields to `OrderResource`, using `whenLoaded` so unrelated endpoints do
   not incur extra queries.
4. Keep the public response limited to the requested order.
5. Preserve the existing UUID/date validation and unauthenticated access behavior.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 1 status

- [x] Public tracking eager-loads history, history creators, attachments, and uploaders.
- [x] `OrderResource` conditionally serializes `history` and `attachments`.
- [x] Populated and empty tracking collections are covered by feature tests.
- [x] UUID/date lookup, validation, not-found, and unauthenticated access remain covered.
- [x] PHPUnit tracking tests passed in the supplied test run.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 1 edge case was identified from the business rules.

## Step 2 — Constrain lifecycle IDs to the route order

### Business requirement

Budget items, approved services, and completed services must belong to the order identified by the route UUID. A valid
database ID from another order must not be accepted.

### Likely implementation areas

- `app/Http/Requests/SubmitBudgetRequest.php`
- `app/Http/Requests/CustomerApprovalRequest.php`
- `app/Http/Requests/MarkWorkCompletedRequest.php`
- `app/Services/OrderLifecycleService.php`

### Implementation checklist

1. Add order-scoped validation for `services.*.order_item_id`.
2. Add order-scoped validation for `authorized_service_ids.*` through the service’s parent order item.
3. Add order-scoped validation for `completed_service_ids.*` through the service’s parent order item.
4. Keep service-layer ownership checks as defense in depth; do not rely only on request validation.
5. Ensure rejected requests do not create services, authorize services, complete services, or change order status.
6. Ensure same-order valid IDs continue to pass existing lifecycle tests.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php --filter='belongs_to_another_order' --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 2 status

- [x] Budget item IDs are validated against the route order.
- [x] Approved service IDs are validated against the route order.
- [x] Completed service IDs are validated against the route order.
- [x] Lifecycle service methods reject foreign IDs before mutating data.
- [x] Related feature and service tests passed.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 2 edge case remains unresolved.

## Step 3 — Require authorization before work completion

### Business requirement

Only services approved by the customer may be marked as performed. An unauthorized service must remain unperformed, and
a mixed request must not partially update valid entries.

### Likely implementation areas

- `app/Http/Requests/MarkWorkCompletedRequest.php`
- `app/Services/OrderLifecycleService.php`

### Implementation checklist

1. Validate each submitted service as belonging to the route order and having `is_authorized = true`.
2. Repeat the authorization constraint in the lifecycle service query.
3. Reject the complete request if any submitted ID is invalid, foreign, or unauthorized.
4. Keep the existing permitted statuses (`Ready for Work` and `In Progress`) unchanged.
5. Preserve the existing recalculation behavior based on completed services.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php --filter='work_completion' --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 3 status

- [x] Work-completion request validation requires route-order services to be authorized.
- [x] Lifecycle service queries require every completed service to be authorized.
- [x] Foreign, unauthorized, and mixed requests are rejected before mutation.
- [x] Ready for Work and In Progress remain the permitted statuses.
- [x] Related feature, service, and lifecycle regression tests passed.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 3 edge case remains unresolved.

## Step 4 — Constrain components to their item type

### Business requirement

Received components must come from the documented component list for the selected item type: Cylinder Head, Engine
Block, Crankshaft, Connecting Rods, or Others.

### Likely implementation areas

- `app/Http/Requests/StoreOrderWithItemsRequest.php`
- `app/Enums/OrderItemType.php`

### Implementation checklist

1. Resolve each submitted `items.*.item_type` to `OrderItemType`.
2. Validate each `items.*.components.*` value against that enum case’s `getComponents()` list.
3. Attach an error to the exact nested component attribute.
4. Keep an omitted or empty component list valid when the business rule permits no components for that item.
5. Preserve the existing valid component creation behavior.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php --filter='component' --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 4 status

- [x] Each submitted component is validated against its selected item type.
- [x] Invalid components report errors on the exact nested component attribute.
- [x] Omitted and empty component lists remain valid.
- [x] Existing valid component creation behavior remains green.
- [x] Related component, order creation, lifecycle, and enum tests passed.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 4 edge case remains unresolved.

## Step 5 — Require payment before delivery

### Business requirement

An order may be delivered only when the remaining amount is zero. Partial payment must leave the order in
`Ready for Delivery`; exact payment and overpayment must be accepted.

### Likely implementation areas

- `app/Http/Requests/DeliverOrderRequest.php`
- `app/Services/OrderLifecycleService.php`
- `app/Models/OrderMotorInfo.php`

### Implementation checklist

1. Retain the current status requirement.
2. Compare `down_payment` and `total_cost` using monetary precision rather than unsafe binary float comparisons.
3. Return a stable validation error for a positive remaining balance.
4. Repeat the payment check in `OrderLifecycleService::deliverOrder()` so the rule cannot be bypassed outside HTTP
   validation.
5. Leave the order status unchanged on rejection.
6. Preserve zero-total orders as deliverable because their remaining balance is zero.
7. Preserve delivery notifications only for successful delivery.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php --filter='delivery_requires' --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Observers/OrderPaymentNotificationsTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 5 status

- [x] Delivery validation rejects orders with a positive remaining balance.
- [x] Lifecycle service delivery enforces the payment rule independently.
- [x] Monetary comparisons use two-decimal precision.
- [x] Partial payment leaves the order ready for delivery.
- [x] Exact payment, overpayment, and zero-total orders remain deliverable.
- [x] Related delivery, lifecycle, model, service, observer, and notification tests passed.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 5 edge case remains unresolved.

## Step 6 — Record the automatic approval transition

### Business requirement

After review, the order transitions from `Reviewed` to `Awaiting Customer Approval`, and both status changes must remain
visible in the order history.

### Likely implementation areas

- `app/Observers/OrderObserver.php`
- `app/Models/OrderHistory.php`
- Existing order history and observer tests

### Implementation checklist

1. Replace the quiet-only transition with an auditable transition mechanism.
2. Record exactly these two status changes:
    - `Awaiting Review → Reviewed`
    - `Reviewed → Awaiting Customer Approval`
3. Prevent duplicate history rows caused by nested observer updates.
4. Preserve the reviewed customer notification and administrator audit notification.
5. Preserve the final persisted status as `Awaiting Customer Approval`.
6. Confirm unrelated status changes still create one history row each.

### Verification

```bash
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php --filter='reviewed_to_awaiting' --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Observers/OrderObserverAdvancedTest.php --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderHistoryTrackingTest.php --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

### Step 6 status

- [x] The automatic Reviewed → Awaiting Customer Approval transition is auditable.
- [x] Exactly two status-history rows are recorded for the reviewed transition.
- [x] Nested observer updates do not create duplicate history rows.
- [x] Reviewed notifications and the final persisted status remain unchanged.
- [x] Unrelated status-history behavior remains green.
- [x] Related transition, observer, and history tests passed.
- [x] Pint passed.
- [x] PHPStan passed with no errors.
- [x] No additional Step 6 edge case remains unresolved.

## Final verification after steps 1–6

Run the complete suite only after all six implementations are complete and their focused tests are green:

```bash
vendor/bin/sail artisan test --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer test:types
```

If any command fails, fix the relevant implementation or test, rerun the narrowest failing command, then repeat the
final verification.

## Deferred step 8 — Public Inertia/Vue tracking form

Do not begin this step until steps 1–6 are complete and the final backend verification passes. The later work will add
the public form for UUID plus creation date, connect it to the public tracking endpoint, and display the now-complete
order, history, and attachments response.
