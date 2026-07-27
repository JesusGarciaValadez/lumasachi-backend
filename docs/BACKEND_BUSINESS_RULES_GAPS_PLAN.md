# Backend Business Rules Gap Remediation Plan

## Purpose

Use this document as a handoff plan for completing the backend gaps identified by comparing `docs/Business_Rules.md`
with the registered routes, controllers, requests, resources, observers, services, and PHPUnit tests.

This plan covers backend steps 1–6 only. The public Inertia/Vue tracking form is intentionally deferred to step 7 until
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

## Step 7 — Inertia/Vue business-rule workflow views

Do not begin this step until steps 1–6 are complete and the final backend verification passes.

Step 7 adds the public tracking form, authenticated workflow views, and reusable presentation components needed to
operate the full order lifecycle defined in `docs/Business_Rules.md`. The public form will accept the order UUID plus
creation date, connect to the public tracking endpoint, and display the public-safe order, history, and attachments
response.

### Step 7 scope and sequencing

Keep these constraints throughout Step 7:

- Continue using Inertia v2, Vue 3, TypeScript, Tailwind CSS v4, Ziggy named routes, the existing `AppLayout` for
  authenticated pages, and a public-safe layout for tracking.
- Preserve the current design line based on `Card`, `Button`, `Input`, `Label`, `Checkbox`, `Dialog`, `Skeleton`,
  breadcrumbs, Lucide icons, semantic color tokens, responsive grids, and dark mode.
- Prefer extending `resources/js/pages/Orders/Show.vue` with role- and status-specific panels instead of creating a
  separate full page for every lifecycle transition.
- Keep lifecycle authority in the existing Form Requests, policies, and `OrderLifecycleService`; the UI may hide or
  disable invalid actions, but it must never become the only enforcement layer.
- Use the existing JSON API endpoints from the Vue pages rather than duplicating lifecycle behavior in web routes.
- Do not add frontend dependencies without approval.
- Do not copy Tailwind Plus code unless the project has an applicable Tailwind Plus license. Treat the referenced blocks
  as layout and interaction patterns, then adapt them to the existing Vue components and design tokens.

### 7.1 — Shared UI contract, design line, and test foundation

#### 7.1.1 — Define the page and component architecture

1. Keep `resources/js/pages/Orders/Show.vue` as the authenticated order-detail shell.
2. Add `resources/js/pages/Orders/Create.vue` for order intake because creation is a distinct multi-section form.
3. Plan focused components under `resources/js/components/orders/` for:
    - order page heading and status progress;
    - motor and received-items summary;
    - service matrix and financial summary;
    - review/budget action panel;
    - customer-approval action panel;
    - work-completion action panel;
    - delivery action panel;
    - attachment list;
    - order-history feed;
    - validation, loading, empty, and stale-state feedback.
4. Reuse public read-only components in authenticated views when they have the same data and behavior.
5. Keep every Vue page and component to a single root element.

#### 7.1.2 — Preserve the current application design line

1. Use `AppLayout` and the current breadcrumb pattern for authenticated pages.
2. Reuse the existing UI primitives before adapting a Tailwind Plus pattern.
3. Use semantic classes such as `bg-background`, `text-foreground`, `text-muted-foreground`, `border-border`,
   `bg-primary`, and `text-primary-foreground`.
4. Preserve the current rounded cards, compact typography, responsive one/two-column grids, and dark-mode behavior.
5. Use `gap-*` utilities for sibling spacing and Tailwind CSS v4 syntax only.
6. Use Lucide icons already installed by the application; do not add another icon library.
7. Keep mobile layouts functional without horizontal page scrolling. Service tables may use an internal scroll container
   or a stacked mobile representation.

#### 7.1.3 — Tailwind Plus component references

Use the following Tailwind Plus blocks as design references and adapt them to existing application primitives:

| Tailwind Plus pattern                                                                                                                                     | Planned application use                                                                          |
|-----------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------|
| [Page headings — With meta and actions](https://tailwindcss.com/plus/ui-blocks/application-ui/headings/page-headings)                                     | Order UUID, customer-safe metadata, status badge, and the one lifecycle action currently allowed |
| [Detail screens](https://tailwindcss.com/plus/ui-blocks/application-ui/page-examples/detail-screens)                                                      | Overall composition for the authenticated order detail                                           |
| [Form layouts — Two-column with cards](https://tailwindcss.com/plus/ui-blocks/application-ui/forms/form-layouts)                                          | Order intake sections for customer, assignment, motor data, items, and advance payment           |
| [Select menus — Simple native / with status indicator](https://tailwindcss.com/plus/ui-blocks/application-ui/forms/select-menus)                          | Customer, employee, priority, item type, and status-aware choices                                |
| [Checkboxes — List with description / checkbox on right](https://tailwindcss.com/plus/ui-blocks/application-ui/forms/checkboxes)                          | Received components and selectable budget, approval, and completion services                     |
| [Tables — With checkboxes, grouped rows, summary rows, and stacked columns on mobile](https://tailwindcss.com/plus/ui-blocks/application-ui/lists/tables) | The Medida / PPTO / Aut. / T.R. service matrix grouped by item type                              |
| [Description lists — Left-aligned in card / two-column](https://tailwindcss.com/plus/ui-blocks/application-ui/data-display/description-lists)             | Motor specifications, assignment, dates, and received components                                 |
| [Stats — Simple in cards / shared borders](https://tailwindcss.com/plus/ui-blocks/application-ui/data-display/stats)                                      | Budgeted, authorized, completed, advance-payment, and remaining-balance totals                   |
| [Order summaries — Simple with full order details](https://tailwindcss.com/plus/ui-blocks/ecommerce/components/order-summaries)                           | Customer-facing and delivery financial breakdown                                                 |
| [Feeds — Simple with icons / multiple item types](https://tailwindcss.com/plus/ui-blocks/application-ui/lists/feeds)                                      | Chronological order history and lifecycle activity                                               |
| [Progress bars](https://tailwindcss.com/plus/ui-blocks/application-ui/navigation/progress-bars)                                                           | Read-only lifecycle progress from Awaiting Review through Delivered                              |
| [Badges](https://tailwindcss.com/plus/ui-blocks/application-ui/elements/badges)                                                                           | Order status and budgeted, authorized, completed, and payment states                             |
| [Alerts](https://tailwindcss.com/plus/ui-blocks/application-ui/feedback/alerts)                                                                           | Validation summaries, payment blockers, stale status, and request failures                       |
| [Empty states](https://tailwindcss.com/plus/ui-blocks/application-ui/feedback/empty-states)                                                               | No services, components, attachments, or history                                                 |
| [Modal dialogs — Simple with gray footer / simple alert](https://tailwindcss.com/plus/ui-blocks/application-ui/overlays/modal-dialogs)                    | Confirmation before approval, work completion, ready-for-delivery, and delivery mutations        |

Tailwind Plus currently offers Vue and HTML component formats and targets the latest Tailwind CSS release. During
implementation, translate any selected pattern to this repository's installed Tailwind CSS version and existing
Reka-based components instead of adding Headless UI or Catalyst as a parallel component system.

#### 7.1.4 — Define typed API and capability contracts

1. Add shared TypeScript interfaces for order, motor info, item, component, service, financial totals, history, and
   attachment payloads.
2. Normalize the resource shape once; do not repeat `{ data: ... }` unwrapping or `any` types in each view.
3. Add a focused order API client/composable for show, create, budget, approval, completion, ready-for-delivery, and
   delivery requests.
4. Preserve server validation keys, including nested keys such as `items.0.components.0`, so errors render beside the
   correct control.
5. Define a server-derived role/status capability map for presentation:
    - create order;
    - submit review and budget;
    - approve selected services;
    - mark authorized services completed;
    - mark ready for delivery;
    - deliver a fully paid order.
6. Refresh the order after every successful mutation and render controls from the persisted status returned by the
   server.
7. Treat `409`, `422`, `403`, `404`, `429`, and unexpected failures as distinct UI states.

#### 7.1.5 — Test foundation

1. Add PHPUnit feature coverage for every new web route with `assertInertia`, including component name, required props,
   authentication, authorization, and absence of sensitive props where applicable.
2. Continue testing lifecycle mutations through their existing API feature tests; do not duplicate business logic
   assertions in route-render tests.
3. Vue test dependencies are approved for this project. Add the required runner and utilities when implementing the Vue
   unit-test cases listed below, using Vitest and Vue Test Utils.
4. Until those Vue unit tests are implemented, keep PHPUnit feature coverage plus TypeScript, build, lint, and format
   verification mandatory; do not claim unimplemented Vue unit tests are green.

#### 7.1 completion marks

- [x] Authenticated order-detail routing and the order-create page foundation are in place.
- [x] Shared order contracts and the order API composable cover the Step 7.1 data, capability, mutation, and error
  requirements.
- [x] PHPUnit route coverage includes Inertia props, authentication, authorization, and sensitive-prop checks.
- [x] TypeScript, production build, frontend lint, and global format verification pass.
- [x] Vue unit tests were not part of the completed Step 7.1 scope; their dependencies are now approved for future
  implementation.
- [x] No additional Step 7.1 edge case remains unresolved.

### 7.2 — Public order-tracking view

#### 7.2.1 — Route and lookup contract

1. Add a named public web route for the tracking page before any dynamic `/orders/{order:uuid}` route.
2. Render a public Inertia page under `resources/js/pages/Orders/` without the authenticated `AppLayout`.
3. Accept exactly the order UUID and creation date required by the existing public tracking endpoint.
4. Submit to the existing public endpoint without adding a second lookup implementation to a web controller.
5. Keep the entered values after validation, not-found, rate-limit, or network failures so the customer can correct or
   retry the lookup.

#### 7.2.2 — Public-safe result

1. Adapt the Tailwind Plus detail-screen, description-list, progress, order-summary, attachment-list, and feed patterns
   to display the public tracking response.
2. Show the current localized status, motor information, received items and components, services with their budgeted,
   authorized, and completed states, financial totals, public-safe attachments, and history.
3. Keep the result read-only and omit all approval, work-completion, ready-for-delivery, delivery, and administrative
   controls.
4. Do not expose customer contact data, employee-private data, database IDs, storage paths, authenticated-only URLs, or
   any other field absent from the public tracking resource.
5. Reuse shared read-only presentation components only when their props cannot expose authenticated-only fields.

#### 7.2.3 — Lookup states and edge cases

1. Provide a loading state for the active lookup and disable duplicate submissions.
2. Render field-level `422` validation errors and one generic not-found response for an unknown UUID, incorrect creation
   date, or mismatched UUID/date pair.
3. Render distinct `429` and network/unexpected-error states without revealing whether a UUID exists.
4. Provide empty states for services, components, attachments, and history when the public resource returns empty
   collections.
5. Allow another lookup after a successful result and clear the previous order before displaying a failed subsequent
   lookup.
6. Cancel or ignore superseded requests so a slower earlier response cannot replace the latest lookup result.

#### 7.2.4 — Tests

Feature tests:

1. Guests can open the tracking page and receive the expected Inertia component without authenticated or sensitive
   props.
2. A valid UUID and matching creation date return the public-safe order contract.
3. An unknown UUID, incorrect date, and mismatched pair return the same generic not-found response.
4. Missing or malformed UUID/date values return field-level validation errors.
5. Statuses and priorities return stable values plus localized labels.
6. Populated and empty service, component, attachment, and history collections use stable response shapes.
7. Sensitive customer, employee, internal identifier, storage-path, and authenticated-action data are absent.
8. Existing public tracking rate-limit coverage remains green.

Frontend unit tests, if the test dependency is approved:

1. The form maps UUID and creation date to the endpoint contract.
2. Validation, not-found, rate-limit, and network states render independently.
3. A failed second lookup removes the previous result without losing the submitted lookup values.
4. A superseded response is ignored.
5. The result exposes no lifecycle mutation controls.

#### 7.2 completion marks

- [x] The public tracking route and page use the UUID plus creation-date lookup contract without authenticated props or
  layout.
- [x] The public result renders only the public-safe order, progress, items, components, services, totals, attachments,
  and history.
- [x] Loading, validation, generic not-found, rate-limit, network, unexpected-error, empty-collection, and
  superseded-request states are covered.
- [x] Public tracking remains read-only and omits customer, employee, database identifier, storage-path, and
  authenticated-action data.
- [x] Related PHPUnit and approved frontend tests pass.
- [x] TypeScript, build, formatting, ESLint, Pint, PHPStan, and diff verification pass.
- [x] No additional Step 7.2 business-rule edge case remains unresolved.

### 7.3 — Staff order-intake view

#### 7.3.1 — Route and access

1. Add a named authenticated web route for `Orders/Create`.
2. Permit only roles authorized by `OrderPolicy::create`.
3. Add the create action to the existing order index/page heading for authorized users only.

#### 7.3.2 — Form layout and data

1. Use the Tailwind Plus two-column-with-cards pattern inside `AppLayout`.
2. Add sections for:
    - customer, title, description, priority, assigned employee, and estimated completion;
    - brand, liters, year, model, and cylinder count;
    - advance payment using a decimal-safe money input;
    - one or more received item types;
    - components constrained to each selected item type.
3. Load customers, employees, item types, and component options from existing authorized endpoints/catalog data.
4. Use the select-menu pattern for customer, employee, priority, and item type.
5. Use described checkbox lists for components.
6. Allow omitted or empty component lists where the business rules permit them.
7. Prevent duplicate item-type rows in the client while preserving server validation as the authority.

#### 7.3.3 — Submission behavior

1. Submit the exact `StoreOrderWithItemsRequest` payload to the existing create endpoint.
2. Disable duplicate submissions and preserve entered data on validation failure.
3. Map nested validation errors to the corresponding item/component row.
4. On success, navigate to the created order and display its persisted `Awaiting Review` state.
5. Do not expose or simulate notification delivery in the UI; show only the successful server result.

#### 7.3.4 — Tests

Feature tests:

1. Authorized staff can open the creation page.
2. Customers and guests cannot open it.
3. A valid nested payload creates the order, motor info, items, and components and returns `Awaiting Review`.
4. Invalid and cross-item components show nested validation errors and create nothing.
5. Missing required items and invalid money values remain covered.

Frontend unit tests, if the test dependency is approved:

1. Adding/removing item rows preserves independent component selections.
2. Changing an item type clears components invalid for the new type.
3. Nested server errors attach to the correct row and component.
4. A processing form cannot submit twice.

#### 7.3 completion marks

- [x] The authenticated, policy-protected `Orders/Create` route and authorized order-index action are in place.
- [x] The intake form covers order details, motor information, decimal-safe advance payment, received item types, and
  item-type-specific components using the existing catalog and user endpoints.
- [x] Duplicate item types are prevented in the client, invalid components are cleared when an item type changes, and
  server validation remains authoritative.
- [x] The exact create payload is submitted, duplicate submissions are guarded, entered values remain after validation
  failure, and nested component errors are shown on their item row.
- [x] Valid creation persists motor information, items, and components and returns the persisted `Awaiting Review`
  status.
- [x] Guest/customer access, missing items, invalid money, and cross-item component validation are covered by focused
  PHPUnit tests.
- [x] TypeScript, production build, changed-file Prettier, ESLint, Pint, PHPStan, and scoped PHPUnit verification pass.
- [x] Vue unit tests were not part of the completed Step 7.3 scope; their dependencies are now approved for future
  implementation.
- [x] No additional Step 7.3 business-rule edge case remains unresolved.

### 7.4 — Staff review and quotation panel

#### 7.4.1 — Visibility and data

1. Render the review panel only for users allowed to update an order in `Awaiting Review`.
2. Group received items and catalog services by item type.
3. Keep unreceived item types out of the actionable service matrix.
4. Show service name, measurement, PPTO selection, base price, net price, and notes.
5. Require measurement only when the catalog service requires it.

#### 7.4.2 — Service matrix and totals

1. Adapt the Tailwind Plus grouped table with checkboxes and summary rows.
2. Use a stacked mobile presentation for service name, measurement, and price while retaining accessible table headers
   on larger screens.
3. Calculate preview totals from selected catalog services for feedback only.
4. Replace preview totals with server-returned values after submission.
5. Display the budgeted base and net totals in stats cards and an order-summary card.

#### 7.4.3 — Submission and transition

1. Confirm budget submission in a modal.
2. Submit only services belonging to the route order and selected received item.
3. Render all validation failures without partially mutating the local service state.
4. After success, refresh the order and show the persisted `Awaiting Customer Approval` state.
5. Show the two recorded history transitions in the activity feed; do not synthesize a client-only Reviewed event.

#### 7.4.4 — Tests

Feature tests:

1. Only authorized staff can access and submit the review action.
2. The panel contract contains received items and applicable catalog services.
3. Foreign items, invalid service keys, and missing required measurements are rejected.
4. Successful submission stores the budget and ends in `Awaiting Customer Approval`.
5. Existing notification and two-history-row tests remain green.

Frontend unit tests, if approved:

1. Grouping and selection operate independently per received item.
2. Measurement fields appear only for services that require them.
3. Preview totals include only selected budget services.
4. Validation and stale-status errors leave selections intact.

#### 7.4 completion marks

- [x] The review panel is rendered from the server-provided `submit_budget` capability and only while the order is in
  `Awaiting Review`.
- [x] Received items are grouped independently with only their active, item-type-specific catalog services; unreceived
  items are excluded.
- [x] The responsive review matrix shows PPTO selection, service name, conditional measurement, base price, net price,
  and notes with accessible desktop headers.
- [x] Preview base and net totals include only selected services, while the persisted order exposes server-calculated
  budgeted base and net totals in its financial summary.
- [x] Budget submission is confirmed in a modal, sends only selected received services, preserves local selections after
  validation or stale-status errors, and refreshes to the persisted `Awaiting Customer Approval` state on success.
- [x] Foreign, unreceived, invalid, mismatched, inactive, malformed, and missing-measurement budget payloads are
  rejected without persisting partial services.
- [x] Existing budget notifications and history transitions remain green, and the focused PHPUnit, Vue unit, TypeScript,
  build, ESLint, Prettier, Pint, and PHPStan checks pass.
- [x] No additional Step 7.4 business-rule edge case remains unresolved.

### 7.5 — Customer approval panel

#### 7.5.1 — Visibility and read-only quotation

1. Render the approval panel only to the order's customer while status is `Awaiting Customer Approval`.
2. Show all budgeted services grouped by item type, including measurement, base price, and net price.
3. Make budgeted state read-only.
4. Display budget totals before any customer selection.

#### 7.5.2 — Approval and advance payment

1. Use described checkbox rows to select any subset of budgeted services.
2. Keep unselected services visibly unapproved rather than removing them.
3. Accept an optional non-negative advance payment with two-decimal presentation.
4. Update authorized preview totals as selections change.
5. Confirm the selected services, totals, and advance payment in a modal before submission.

#### 7.5.3 — Submission and transition

1. Submit service IDs and advance payment to the existing customer-approval endpoint.
2. Disable controls while processing and after a successful transition.
3. On success, refresh the order and show `Ready for Work`.
4. If status changed in another session, show a stale-state alert and reload rather than retrying automatically.
5. Keep the public tracking page read-only; public possession of UUID and date must never grant approval capability.

#### 7.5.4 — Tests

Feature tests:

1. Only the owning customer can submit approval.
2. Any valid subset of same-order budgeted services is accepted according to current business rules.
3. Foreign and non-budgeted services are rejected without partial authorization.
4. Advance payment validation and authorized totals remain correct.
5. Success transitions the order to `Ready for Work`.

Frontend unit tests, if approved:

1. Authorized preview totals track selected services only.
2. The confirmation summary matches selected IDs and payment.
3. Server validation and stale-state responses preserve a recoverable form.
4. Read-only users never receive actionable controls.

#### 7.5 completion marks

- [x] The approval panel is rendered only from the server-provided customer approval capability while the order is in
  `Awaiting Customer Approval`.
- [x] All persisted budgeted services are shown by item with measurement, base price, net price, read-only budgeted
  state, and persisted authorization state; non-budgeted services are excluded.
- [x] Customers can select any displayed subset, see budgeted and selected authorized base/net previews, and enter an
  optional non-negative advance payment with two-decimal submission formatting.
- [x] Confirmation includes the selected count, authorized totals, and advance payment; controls are disabled while
  processing and the successful server response is applied before the follow-up refresh.
- [x] Foreign, non-budgeted, non-integer, duplicate, negative-payment, wrong-status, and non-owner approval attempts
  remain rejected without partial authorization.
- [x] Successful approval returns `Ready for Work`, preserves unselected services as unauthorized, and keeps authorized
  totals and advance payment correct.
- [x] PHPUnit, Vue unit tests, TypeScript, production build, ESLint, Prettier, Pint, PHPStan, and diff checks pass.
- [x] No additional Step 7.5 business-rule edge case remains unresolved.

### 7.6 — Work-execution panel

#### 7.6.1 — Visibility and service constraints

1. Render for authorized staff only when status is `Ready for Work` or `In Progress`.
2. Show all budgeted services for context, but make only authorized services selectable for completion.
3. Clearly badge budgeted, authorized, and completed states.
4. Keep unauthorized services visible and disabled so omitted work is understandable.

#### 7.6.2 — Completion workflow

1. Use the same grouped service matrix as review and approval.
2. Allow selecting one or more authorized, incomplete services.
3. Confirm completion before submitting.
4. Submit IDs to the existing work-completion endpoint.
5. Refresh server-calculated completed totals and remaining balance after success.
6. Never treat authorization as completion, and never allow a mixed authorized/unauthorized selection.

#### 7.6.3 — Ready-for-delivery workflow

1. Render the ready-for-delivery action only in a status accepted by the existing Form Request.
2. Show a summary of completed and uncompleted authorized services before confirmation.
3. Use server validation as the final decision when the business permits an order to become ready for delivery.
4. On success, show `Ready for Delivery` and the customer-notification outcome represented by the persisted state only.

#### 7.6.4 — Tests

Feature tests:

1. Authorized staff can complete same-order authorized services.
2. Unauthorized, foreign, duplicate, and mixed service IDs are rejected atomically.
3. Completed totals are recalculated from completed services only.
4. Ready-for-delivery accepts only the statuses defined by the backend request.
5. Existing ready-for-delivery notification tests remain green.

Frontend unit tests, if approved:

1. Unauthorized and already-completed rows are disabled.
2. Completed preview totals include completed services only.
3. A mixed invalid selection cannot be submitted.
4. Successful mutations refresh badges, totals, and available actions.

#### 7.6 completion marks

- [x] The work-execution panel is rendered only from the server-provided staff capabilities while the order is in
  `Ready for Work` or `In Progress`.
- [x] Budgeted services are shown by item with budgeted, authorized, and completed badges; unauthorized services stay
  visible and disabled.
- [x] Only authorized incomplete services are selectable, and the completed preview total is calculated from completed
  services only.
- [x] Completion submissions accept only same-order authorized incomplete integer IDs, reject foreign, unauthorized,
  duplicate, non-integer, already-completed, mixed, and wrong-status attempts atomically, and recalculate completed and
  remaining totals.
- [x] Successful completion applies the server response before refresh, clears the selection, and refreshes badges,
  totals, and available actions.
- [x] The ready-for-delivery action uses the existing accepted statuses, summarizes completed and uncompleted authorized
  services before confirmation, and applies the successful server response before refresh.
- [x] Existing ready-for-delivery notification tests remain green.
- [x] PHPUnit, Vue unit tests, TypeScript, production build, ESLint, Prettier, Pint, PHPStan, and diff checks pass.
- [x] No additional Step 7.6 business-rule edge case remains unresolved.

### 7.7 — Delivery and payment panel

#### 7.7.1 — Financial summary

1. Render for authorized staff when the order is `Ready for Delivery`.
2. Adapt the Tailwind Plus full order-summary and stats-card patterns.
3. Show completed-service total, advance payment, remaining balance, and payment state using two decimal places.
4. Distinguish partial payment, exact payment, overpayment, and zero-total orders.
5. Show a blocking alert while remaining balance is positive.

#### 7.7.2 — Delivery action

1. Disable the delivery action in the UI when a positive balance is returned by the server.
2. Keep server-side `DeliverOrderRequest` and `OrderLifecycleService` checks as the authoritative guard.
3. Confirm final delivery in a modal with the order UUID and financial summary.
4. Submit to the existing delivery endpoint.
5. On success, refresh and show the terminal `Delivered` state with no further lifecycle mutation controls.

#### 7.7.3 — Tests

Feature tests:

1. Partial payment rejects delivery and preserves `Ready for Delivery`.
2. Exact payment, overpayment, and zero-total orders can be delivered.
3. Unauthorized users cannot deliver the order.
4. Failed delivery sends no delivery notification.
5. Successful delivery retains customer and audit notifications.

Frontend unit tests, if approved:

1. Remaining-balance formatting is correct for partial, exact, overpaid, and zero-total data.
2. Positive balance disables the action and exposes the blocking explanation.
3. Confirmation receives the same financial values shown on the page.
4. A successful response removes lifecycle actions and renders `Delivered`.

#### 7.7 completion marks

- [x] Authorized staff see the delivery/payment panel for `Ready for Delivery`; delivery capability remains server-
  provided and requires no pending payment.
- [x] Completed-service total, advance payment, remaining balance, and payment state are shown with two decimal places,
  distinguishing partial, exact, overpaid, and zero-total orders.
- [x] A positive remaining balance shows a blocking explanation and disables delivery in the UI; the server request and
  lifecycle service remain authoritative.
- [x] Delivery confirmation includes the order UUID and the same completed, advance-payment, and remaining-balance
  values shown on the page.
- [x] Successful delivery applies the server response before refresh, removes lifecycle actions, and shows `Delivered`.
- [x] Partial payment preserves `Ready for Delivery` and sends no delivery notification; exact payment, overpayment, and
  zero-total orders can be delivered; unauthorized users cannot deliver.
- [x] Successful delivery retains customer and active audit notifications.
- [x] PHPUnit, Vue unit tests, TypeScript, production build, ESLint, Prettier, Pint, PHPStan, and diff checks pass.
- [x] No additional Step 7.7 business-rule edge case remains unresolved.

### 7.8 — Shared order details, attachments, and history

#### 7.8.1 — Order detail composition

1. Replace the current partial detail view with the shared order heading, lifecycle progress, description lists, service
   matrix, financial summary, attachments, and history feed.
2. Keep customer, assignment, dates, priority, and notes in compact description-list cards.
3. Show motor data and received components without exposing edit controls to unauthorized roles.
4. Use localized status and priority labels while retaining stable enum values for logic.

#### 7.8.2 — Attachments

1. Show filename, type, size, uploader-safe display data, and available preview/download actions.
2. Preserve existing attachment authorization for authenticated views.
3. Use loading skeletons and a dedicated empty state.
4. Treat preview/download authorization failure separately from a missing attachment.
5. Do not assume authenticated attachment URLs are suitable for public tracking; that contract must remain public-safe.

#### 7.8.3 — History feed

1. Adapt the Tailwind Plus feed with icons or multiple item types.
2. Render status, priority, assignment, item, service, payment, and attachment events chronologically.
3. Use the server-provided description and timestamps; do not infer missing history client-side.
4. Preserve both Reviewed transition entries as separate events.
5. Add loading, empty, and pagination states if the endpoint is paginated.

#### 7.8.4 — Tests

Feature tests:

1. Authorized users receive the complete order-detail contract.
2. Customer and employee visibility still follows `OrderPolicy`.
3. History and attachment endpoints remain order-scoped.
4. Empty history and attachments return stable empty collections.
5. Sensitive fields are absent from any public Inertia or public API contract.

Frontend unit tests, if approved:

1. Status progress maps every supported status without skipping the automatic Reviewed transition.
2. History preserves server order and renders distinct event types.
3. Empty and loading states render independently for details, attachments, and history.
4. Action panels are selected from role, policy capability, and persisted status.

#### 7.8 completion marks

- [x] Authenticated order detail now composes the heading, localized status/priority, complete description-list fields,
  lifecycle progress, motor and received-component data, financial summary, and readonly service matrix without adding
  unauthorized edit controls.
- [x] Progress uses every supported `OrderStatus` value, including the automatic `Reviewed` transition, while stable
  enum values remain the logic source and labels remain localized.
- [x] Authenticated attachments show filename, type, size, and uploader-safe name data with preview/download actions,
  loading and empty states, and distinct authorization, missing, and unsupported-preview messages; public tracking
  remains read-only and excludes authenticated attachment fields and URLs.
- [x] The authenticated history feed renders server descriptions and timestamps in server order, uses event-type icons,
  preserves duplicate Reviewed-related events, displays related attachment filenames, and supports loading, empty,
  error, and paginated states.
- [x] Focused PHPUnit coverage confirms the complete authorized contract, notes/completion dates, policy visibility,
  stable empty collections, history order isolation, attachment authorization/order scoping, and the public-safe
  tracking contract.
- [x] Vue unit coverage confirms all status values, distinct ordered history events, independent history states,
  independent attachment loading/empty states, attachment authorization-versus-missing errors, and persisted action
  panel behavior from the existing detail workflow tests.
- [x] Focused PHPUnit (57 tests, 631 assertions), Vue unit tests (35 tests), TypeScript, production build, ESLint,
  Prettier, Pint, PHPStan, and diff checks pass. The full Vitest suite passes with one worker; the initial parallel
  worker attempt was retried after worker-start timeouts.
- [x] No additional Step 7.8 business-rule edge case remains unresolved.

### 7.9 — Navigation, stale-state handling, accessibility, and responsive behavior

#### 7.9.1 — Navigation and role visibility

1. Add order-create navigation only for authorized roles.
2. Keep lifecycle actions inside the order detail instead of adding sidebar entries per transition.
3. Use named Ziggy routes and Inertia `Link` for internal page navigation.
4. Ensure customers see only their own orders and the actions allowed by the backend policy.

#### 7.9.2 — Concurrency and request state

1. Disable duplicate submissions.
2. Refresh after every mutation and reject stale local state.
3. Show a reload action for status conflicts or resources changed in another session.
4. Cancel or ignore superseded read requests so an older response cannot replace a newer order state.
5. Preserve user input after recoverable validation or network failures.

#### 7.9.3 — Accessibility and responsive verification

1. Associate every input with a label and every validation message with its field.
2. Keep service matrices keyboard-operable with visible focus states.
3. Announce success, error, loading, and validation-summary changes using appropriate live regions.
4. Give confirmation dialogs an accessible title, description, initial focus, cancel action, and focus return.
5. Verify contrast and dark mode using existing semantic tokens.
6. Verify mobile, tablet, and desktop layouts, including long UUIDs, service names, currency values, and translated
   labels.

#### 7.9.4 — Tests

Feature tests:

1. Every web route enforces authentication, policy, and order ownership.
2. Every lifecycle API rejects stale or invalid status according to its Form Request.
3. Route names used by the pages exist and resolve with UUID route binding.

Frontend unit tests, if approved:

1. Duplicate clicks produce one request.
2. Older read responses cannot overwrite newer state.
3. Keyboard interaction works for item/service selection and dialogs.
4. Alerts and validation summaries expose accessible text and focus behavior.

#### Step 7.9 completion marks

- [x] Order-create navigation uses the server-shared authorization capability; customers do not receive the create link,
  lifecycle actions remain inside order detail, and internal navigation uses named Ziggy routes with Inertia `Link`.
- [x] Existing backend route and policy coverage continues to enforce authenticated access, order ownership, and
  lifecycle authority; the focused route and lifecycle tests remain green.
- [x] Duplicate submissions and attachment actions are disabled while active, successful mutations refresh persisted
  order state, stale order, attachment, history, and public tracking responses cannot overwrite newer state, and
  recoverable errors preserve the relevant retry or form state.
- [x] Inputs and validation messages are associated, service selection controls have visible focus states, loading and
  error states expose live-region or alert semantics, and the existing confirmation dialog structure provides title,
  description, cancel, and focus-management primitives.
- [x] Existing semantic color tokens, dark-mode classes, responsive layouts, and overflow handling remain in place for
  long identifiers, service names, currency values, and translated labels.
- [x] Focused PHPUnit coverage passes with 55 tests and 612 assertions; the full Vitest suite passes with 40 tests
  across 11 files. TypeScript, production build, ESLint, Prettier, Pint, PHPStan, and diff checks pass.
- [x] No additional Step 7.9 business-rule edge case remains unresolved.

### 7.10 — Step 7 final verification and completion criteria

#### 7.10.1 — Focused verification after each subsection

1. Run the narrowest PHPUnit feature tests for the route and lifecycle endpoint being connected.
2. Run approved Vue unit tests for the changed component or composable.
3. Run TypeScript checking, frontend build, lint, and format checks through Sail.
4. Run Pint after any PHP change and PHPStan after any backend contract change.

If Prettier or ESLint reports the pre-existing warning in `resources/views/vendor/mail/html/themes/default.css`, it is
permitted to modify that file to resolve the warning before evaluating the format/lint verification mark.

#### 7.10.2 — Docker/Sail access and complete verification command list

Run all PHP, Composer, Artisan, and Node commands through Sail. Docker Desktop must be running and the current terminal
must be allowed to access the Docker socket. In a restricted Codex terminal, Docker Desktop may be running while the
sandbox still reports `Docker or Podman is not running` or `permission denied` for the Docker socket. Use this execution
procedure:

1. Run the exact command from the backend project directory with its required `vendor/bin/sail` prefix.
2. If the command returns the Docker/Podman availability or socket-permission message before the application command
   starts, treat it as a terminal-access failure, not an application test failure.
3. Ensure Docker Desktop or Podman is running, grant the terminal elevated Docker/socket access, and retry the exact
   same Sail command. Do not replace Sail with host PHP, Composer, Node, or Yarn commands.
4. Use the result from the successful Docker-enabled retry when evaluating the verification mark.

Verify application access first:

```bash
vendor/bin/sail artisan list
```

Run the complete Step 7 verification block:

```bash
vendor/bin/sail artisan test --parallel --processes=8
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderRouteTest.php tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php tests/Feature/app/Http/Controllers/OrderBusinessRulesEdgeCasesTest.php tests/Feature/app/Policies/OrderPolicyTest.php tests/Unit/app/Services/OrderLifecycleServiceTest.php
vendor/bin/sail composer run test:types
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail yarn run test:unit
vendor/bin/sail yarn vue-tsc --noEmit
vendor/bin/sail yarn run build
vendor/bin/sail yarn run format:check
vendor/bin/sail yarn prettier --check resources/ tests/Frontend vitest.config.ts package.json
vendor/bin/sail yarn eslint resources/js/types/orders.ts resources/js/composables/useOrderApi.ts resources/js/pages/Orders/Create.vue resources/js/pages/Orders/Index.vue resources/js/pages/Orders/Show.vue resources/js/pages/Orders/Track.vue resources/js/components/orders/OrderFinancialSummary.vue resources/js/components/orders/OrderServiceMatrix.vue resources/js/components/orders/OrderStatusProgress.vue resources/js/components/AppSidebar.vue tests/Frontend/orders.types.spec.ts tests/Frontend/OrderServiceMatrix.spec.ts tests/Frontend/Track.spec.ts vitest.config.ts
git diff --check
```

If Pint changes PHP files, rerun the affected PHPUnit tests and `vendor/bin/sail composer run test:types` before marking
the step complete. If any command fails, resolve the failure and rerun the complete block.

#### 7.10.3 — Final verification

```bash
vendor/bin/sail artisan test --parallel --processes=8
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer run test:types
vendor/bin/sail yarn run test:unit
vendor/bin/sail yarn vue-tsc --noEmit
vendor/bin/sail yarn run build
vendor/bin/sail yarn run format:check
```

Run the complete command block in 7.10.2 when the step includes frontend files, frontend tests, or additional
step-specific checks.

#### 7.10.4 — Step 7 completion checklist

- [x] Public tracking accepts UUID plus creation date and renders only the public-safe order, history, and attachment
  contract.
- [x] Order intake captures motor information, received items, valid components, and advance payment.
- [x] Review builds the PPTO service set and shows budgeted totals.
- [x] Customer approval supports any permitted subset and shows authorized totals.
- [x] Work completion permits authorized services only and shows completed totals.
- [x] Ready-for-delivery and delivery actions respect status and payment rules.
- [x] Order details display motor data, components, services, totals, attachments, and complete history.
- [x] All actions are role-, ownership-, and status-aware while retaining backend enforcement.
- [x] Loading, empty, validation, forbidden, not-found, rate-limit, stale-state, and unexpected-error states are
  covered.
- [x] Responsive, keyboard, focus, screen-reader, translated-label, and dark-mode behavior are verified.
- [x] Related PHPUnit feature tests pass.
- [x] Approved Vue unit tests pass with the Vitest test setup.
- [x] TypeScript, frontend build, lint/format, Pint, and PHPStan checks pass as applicable.
- [x] No additional Step 7 business-rule edge case remains unresolved.
