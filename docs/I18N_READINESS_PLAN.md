# Application-wide i18n Readiness Plan

## Purpose

Use this document as the handoff plan for making the Lumasachi Laravel/Inertia/Vue application consistently
internationalized in Spanish and English.

The immediate symptom is mixed-language output. The supplied screenshots show Spanish interface labels alongside raw or
English domain values such as `connecting_rods`, `engine_block`, `bolts`, `bearings`, `Resize 4 connecting rods`, and
`Engine block wash`.

This is an implementation plan only. Creating this file did not change application code or any existing file.

## Business-rule source

All order terminology and lifecycle behavior must remain consistent with `docs/Business_Rules.md`.

Relevant domain language includes:

- Motor item types: Cabeza, Block, Cigüeñal, Bielas, and Otros.
- Received components belonging to each item type.
- Catalog services and measurements.
- Order statuses from receipt through review, approval, work, payment, and delivery.
- Customer and administrator notifications for lifecycle events.
- Public tracking by order UUID and creation date, including history and attachments.

Translation work must not change lifecycle rules, calculations, authorization, public-data exposure, or notification
recipient rules.

## Supported locales and assumptions

- Initial supported locales: `es` and `en`.
- Product default: `es`.
- Currency remains MXN; locale changes presentation, not the stored monetary value or business currency.
- English remains a complete supported locale, not merely an emergency fallback.
- Spanish and English catalogs must have identical key structures.
- Unsupported or malformed locale input must resolve to a supported locale and must not create unbounded cache keys.
- If product requirements later select a different default or add another locale, update this section before
  implementation.

## Definition of “i18n ready”

The feature is complete only when:

1. Every user-visible application string is obtained from a translation catalog or from explicitly user-authored data.
2. Laravel and Vue use the same resolved locale for a request and subsequent API calls.
3. A user can select Spanish or English from authenticated user settings, and the preference survives navigation,
   reload, and sign-in.
4. Public pages honor an anonymous locale preference and a supported browser language.
5. Dates, times, numbers, pluralized counts, and MXN amounts use the active locale.
6. Stable domain identifiers remain stable in storage and API contracts while their labels are translated at the
   presentation boundary.
7. Validation errors, API errors, authentication messages, mail, and notifications use the intended recipient/request
   locale.
8. Existing order histories remain unchanged in storage and can be rendered in either locale.
9. Spanish and English pass automated translation-key parity and missing-key checks.
10. Critical authenticated and public workflows are verified in both locales without mixed-language output.

## Phase completion gate

Do not mark any phase complete until the phase's focused tests and the entire application test suite have both been run
successfully at least once after the final code change.

Required gate:

1. Run the phase-specific backend and frontend tests first and resolve all failures.
2. Run the complete backend suite with the repository's standard parallel command:
   `./vendor/bin/sail test --parallel --processes=8`.
3. Run `vendor/bin/sail yarn run test:unit` when the phase changes frontend code, or when the phase's acceptance
   criteria include frontend behavior.
4. If the parallel backend suite reports database-isolation setup failures, restore/recreate the testing databases and
   rerun the same full command with `--recreate-databases` before diagnosing an application regression.
5. Record the successful full-suite result in the chat handoff, then update that phase's checklist. A focused-only pass
   is never sufficient to mark a phase done.

## Current-state audit

### Existing foundation to retain

- `vue-i18n` is already installed.
- `resources/js/i18n/index.ts` already contains partial `es` and `en` message trees.
- `resources/lang/{es,en}` already contains partial `common.php`, `orders.php`, `notifications.php`, and
  `service_catalog.php` catalogs.
- `ServiceCatalog` stores a stable `service_name_key`, and the catalog cache is already segmented by locale.
- Order statuses and public priority labels already have partial Laravel translation support.
- The root Blade template already derives the HTML `lang` attribute from Laravel's locale.
- Vitest and Vue Test Utils are already approved and configured for this project.

### Confirmed gaps and causes

| Area                  | Current evidence                                                                                                                                     | Required direction                                                                                     |
|-----------------------|------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| Locale authority      | Vue starts with `createI18nInstance()` and therefore always selects `es`; Laravel and `.env.example` default to `en`.                                | Resolve one locale server-side and share it with Vue.                                                  |
| API locale            | `CatalogController` independently accepts query/header locale, while `useOrderApi` sends no application locale contract.                             | Apply a common locale middleware and request contract to web and API routes.                           |
| Locale validation     | `CatalogRequest` accepts any string, and the value is used in cache keys.                                                                            | Normalize and allow-list supported locales.                                                            |
| Locale persistence    | There is no locale selector or dedicated user locale field.                                                                                          | Add an authenticated preference plus anonymous session/cookie behavior.                                |
| Static Vue copy       | Only the dashboard, order pages, sidebar, and header partially use `vue-i18n`.                                                                       | Cover welcome, auth, settings, account, navigation, catalog, order components, and accessibility text. |
| Missing Vue keys      | Call sites reference keys such as `orders.no_services` that are absent from the current catalog.                                                     | Add parity/missing-key tests and complete both locales.                                                |
| Domain labels         | Order detail/tracking output renders raw item and component keys. `OrderItemType::label()` is English-only, and no `motor.php` language files exist. | Add stable translation keys and localized labels for item types and components.                        |
| Service names         | Service accessors localize using Laravel's current locale, which is currently often `en`.                                                            | Resolve locale before resources/accessors execute and retain service keys alongside labels.            |
| Dates and numbers     | Several components hard-code `es-ES` and `es-MX`; formatting logic is duplicated.                                                                    | Centralize formatting and bind it to the active locale.                                                |
| Status and priority   | Raw enum values and localized labels are inconsistently returned/rendered; `OrderPriority::getLabel()` is English-only.                              | Keep raw values for logic and consistently expose/render localized labels.                             |
| History               | `OrderHistory::description` constructs English sentences such as “changed from”; stored values are canonical and should remain so.                   | Translate descriptions at serialization/render time without rewriting history rows.                    |
| Validation/API errors | Many Form Requests, controllers, routes, attachments, auth actions, and `useOrderApi` fallbacks contain English literals.                            | Move human messages to catalogs and use stable error codes where UI branching is needed.               |
| Mail                  | `OrderCreatedMail` subject and Markdown body are English literals; lifecycle mail interpolates raw status values.                                    | Localize all mail content and recipient-specific domain labels.                                        |
| Recipient locale      | `User` does not implement `HasLocalePreference`.                                                                                                     | Persist a supported user locale and let queued notifications/mail resolve it.                          |
| Accessibility         | App-owned primitives contain literals such as Close, More, Sidebar, Toggle Sidebar, and Navigation Menu.                                             | Translate screen-reader, title, placeholder, and ARIA text too.                                        |
| Starter/settings UI   | Welcome, auth, profile, password, appearance, delete-account, and settings navigation are predominantly English.                                     | Include these surfaces in the translation inventory.                                                   |

## Architecture contract

### 1. One locale resolver

Implement one request-scoped locale middleware used by both web and API requests.

Recommended precedence:

1. Explicit, validated locale change made by the current user.
2. Authenticated user's persisted `locale`.
3. Anonymous session/cookie locale.
4. Supported primary tag from `Accept-Language`.
5. `config('app.locale')`.

The resolver must:

- Normalize tags such as `es-MX`, `es_MX`, and `ES` to supported application locale `es`.
- Normalize English regional tags to `en`.
- Reject or fall back from unsupported values.
- call `App::setLocale()` before controllers, resources, validation messages, and Inertia props are resolved;
- avoid static/request-specific state because Octane workers are long-lived;
- expose the current locale and supported locale metadata as namespaced shared Inertia props;
- keep `document.documentElement.lang` synchronized after a client-side locale change.

### 2. Preference persistence

Use a dedicated, allow-listed `locale` user attribute rather than overloading the existing free-form string
`preferences` column.

- Add a nullable/defaulted locale migration following current user-table conventions.
- Validate locale changes against the configured supported locale list.
- Persist the locale for authenticated users.
- Store an anonymous selection in the session and an encrypted/non-sensitive cookie as appropriate.
- Define whether login copies an anonymous preference to the user or the existing user preference wins; recommended:
  an existing user preference wins.
- Do not accept arbitrary locale strings from query parameters.

### 2.1 User settings language preference

The authenticated settings area must provide the durable, user-facing control for this preference. The shared header or
public selector may remain as a convenience, but settings is the canonical place where a signed-in user can review and
change the saved language.

Required implementation steps:

1. Add a `Language`/`Idioma` field to the authenticated profile/settings surface, populated only from the shared
   supported-locale allow-list.
2. Display the user's current persisted locale, with Spanish and English labels translated by the active locale.
3. Submit changes through the same validated locale action used by other selectors; do not create a second persistence
   path or accept arbitrary locale values.
4. Preserve the settings page context, show saving/validation/error states, and update the active Vue locale, Laravel
   locale, HTML `lang`, and subsequent API requests after success.
5. Keep the existing preference precedence explicit: an existing authenticated user preference wins over an anonymous
   session/cookie preference when the user signs in.
6. Cover the profile/settings route with authenticated authorization and ensure a guest cannot persist another user's
   locale.

Likely implementation areas:

- `resources/js/pages/settings/Profile.vue`
- `resources/js/layouts/settings/Layout.vue`
- the shared locale selector component and locale action already introduced by Phase 1
- `tests/Feature/Settings/ProfileUpdateTest.php` and a focused settings component test

Required tests:

- Settings renders the selector with the persisted locale selected.
- Authenticated settings changes persist to the user and are reflected after reload and a new sign-in.
- Unsupported or malformed settings values are rejected without changing the stored preference.
- The settings selector changes both locales without losing unrelated profile state or showing mixed-language copy.
- Anonymous preference plus sign-in precedence is verified end to end.

### 3. Catalog ownership

Use catalogs by responsibility:

- Vue catalogs: static SPA copy, page titles, navigation, buttons, help text, client-side error fallbacks, accessibility
  text, and formatting labels.
- Laravel catalogs: validation, auth/password messages, controller/API messages, mail, notifications, history sentence
  templates, enum labels, motor item/component labels, and service catalog labels.
- Persisted data: stable codes/keys and user-authored content only.

Do not store translated status, priority, item, component, service, or history sentences as the business value.

### 4. API representation

For translatable domain values, preserve stable machine fields and add or consistently populate presentation fields.
Examples:

- `status` plus `status_label`
- `priority` plus `priority_label`
- `item_type` plus `item_type_label`
- `component_name`/stable component key plus `component_label`
- `service_key` plus `service_name`
- history event/field code and canonical old/new values plus a localized description

Do not remove existing stable fields without a separately approved API-versioning decision. Public resources must remain
public-safe and must not gain internal IDs, private fields, or mutation controls.

### 5. User-authored content boundary

The following values are not automatically translated:

- order title and description;
- notes and service notes;
- motor brand/model and measurements;
- customer/employee names;
- attachment filenames;
- free-form history comments.

They must be displayed exactly as entered. The surrounding labels and generated sentences are translated.

### 6. Formatting contract

Create one locale-aware frontend formatting composable/helper for:

- date and date-time;
- decimal and integer values;
- MXN currency;
- counts/pluralization where needed.

Do not keep hard-coded `es-ES` or `es-MX` values in page/components. Use the active locale, with an explicit locale map
only if a browser API requires regional tags (`es-MX`, `en-US`). Invalid/missing date values may continue to use the
language-neutral em dash.

## Phased implementation plan

Later chats/agents must complete phases in order unless a phase explicitly states that work can be parallelized. After
each phase, run its focused verification and update only that phase's status after the checks pass.

### Phase 0 — Lock the locale contract and baseline

#### Scope

- Re-read this plan and `docs/Business_Rules.md`.
- Confirm `es` as the product default, `en` as supported, and MXN as the currency.
- Record the current route/page/API/mail inventory in the implementing chat.
- Confirm the working tree before editing and preserve unrelated user changes.
- Run the existing focused frontend and localization-related backend tests to establish a baseline.

#### Expected files

No production changes are required in this phase.

#### Verification

```bash
vendor/bin/sail artisan list
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/CatalogControllerTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/CatalogSeederIntegrationTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php
vendor/bin/sail yarn run test:unit
```

#### Status

- [x] Locale/default/currency decisions confirmed.
- [x] Baseline results recorded.
- [x] No unrelated file is included in the phase diff.

### Phase 1 — Establish the server/client locale lifecycle

#### Scope

1. Add configuration for supported locales and normalized browser tags.
2. Set the application default to Spanish in committed configuration/examples while retaining English support.
3. Add a request locale middleware to the web and API stacks in `bootstrap/app.php`.
4. Add the dedicated user locale persistence and the locale-change endpoint/action.
5. Share namespaced `i18n.locale` and `i18n.supported_locales` props through `HandleInertiaRequests`.
6. Initialize `vue-i18n` from the shared initial-page locale instead of a hard-coded default.
7. Add a visible locale selector usable from authenticated layouts and the public tracking page.
8. Make navigation labels reactive; avoid translation calls captured once in non-reactive arrays.
9. Ensure locale switching updates Laravel, Vue, the HTML `lang`, and subsequent API responses without a full sign-out.
10. Ensure Octane requests cannot inherit locale state from a prior request.

#### Likely implementation areas

- `config/app.php`
- `.env.example`
- a new locale configuration file if that matches project convention
- `bootstrap/app.php`
- a new locale middleware and locale Form Request/controller/action
- route files
- a new migration plus `app/Models/User.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `resources/js/app.ts`
- `resources/js/i18n/**`
- `resources/js/types/index.d.ts`
- authenticated/public layout or selector components

#### Tests

- Middleware precedence and normalization for `es`, `es-MX`, `en`, `en-US`, unsupported, and missing locale.
- Session/cookie persistence for guests.
- User persistence for authenticated users.
- User preference precedence after login.
- Shared Inertia locale props.
- Vue initialization and locale selector behavior.
- Reactive navigation labels after a runtime switch.
- Sequential requests under different locales to guard against Octane locale leakage.

#### Status

- [x] Locale middleware and allow-list implemented.
- [x] Authenticated and anonymous preferences persist.
- [x] Laravel, Inertia, Vue, and HTML `lang` agree.
- [x] Focused backend and frontend tests pass.

### Phase 2 — Complete static Vue and accessibility catalogs

#### Scope

Split the monolithic frontend message object into maintainable per-locale modules if helpful, while preserving one typed
message schema. Complete both `es` and `en` for all application-owned UI.

Inventory at minimum:

- `Welcome.vue`
- dashboard and order index links such as `Ver`
- all `pages/auth/*.vue`
- all `pages/settings/*.vue`
- `layouts/settings/Layout.vue`
- `DeleteUser.vue`, `UserMenuContent.vue`, and `AppearanceTabs.vue`
- `NavMain.vue`, `AppHeader.vue`, `AppSidebar.vue`, breadcrumbs, page titles, and navigation-menu text
- `Orders/EngineOptions.vue`
- all order pages and `components/orders/*.vue`
- client-side loading, empty, success, warning, confirmation, network, forbidden, not-found, conflict, and unexpected
  states
- placeholders, tooltips, button titles, screen-reader labels, `aria-label`, and app-owned primitive text such as Close,
  More, Sidebar, and Toggle Sidebar

Requirements:

- Use parameters for interpolated values; do not concatenate grammar-dependent sentence fragments.
- Use pluralization for counts such as selected/completed services.
- Add currently referenced but missing keys such as `orders.no_services`.
- Do not translate brand names, UUIDs, route names, technical keys used only in code, or user-authored data.
- Keep Spanish and English key trees identical.
- Fix confirmed wording defects while migrating, including the English
  `Estimated deliverycompletion` string.

#### Tests

- Recursive key-tree parity between `es` and `en`.
- No undefined key in current Vue translation call sites.
- Representative rendering in both locales for welcome, login, settings, dashboard, order creation, order detail,
  catalog, and public tracking.
- Accessibility assertions for translated screen-reader/ARIA copy.
- Pluralization tests for zero, one, and multiple services.

#### Status

- [x] All app-owned static copy uses Vue translation keys.
- [x] Accessibility copy is translated.
- [x] Spanish and English catalogs have parity.
- [x] Representative dual-locale component tests pass.

### Phase 3 — Localize motor items, components, services, statuses, and priorities

#### Scope

1. Add complete `resources/lang/{es,en}/motor.php` catalogs for every `OrderItemType` and every component returned by
   `OrderItemType::getComponents()`.
2. Make `OrderItemType` and `OrderPriority` label methods translation-backed; keep enum values unchanged.
3. Resolve the request locale before catalog labels and `ServiceCatalog::service_name` are evaluated.
4. Restrict `CatalogRequest::locale` to supported normalized locales, or remove endpoint-specific locale input once the
   common middleware owns it.
5. Preserve locale in catalog cache keys using only normalized supported values.
6. Update authenticated and public order resources to provide stable item/component keys and localized labels.
7. Update Vue pages/components to render localized label fields or translation keys, never raw snake-case fallback in
   visible output.
8. Ensure catalog service names use `service_name_key` in the active locale.
9. Define an explicit, visible-safe fallback for legacy/missing catalog translations and cover it with a test. Do not
   silently show a snake-case key as the normal UI path.
10. Preserve all IDs, enum values, service keys, pricing, and item/component ownership rules.

#### Business-rule acceptance examples

Spanish:

- `connecting_rods` → `Bielas`
- `crankshaft` → `Cigüeñal`
- `engine_block` → `Block`
- `bolts` → `Tornillos`
- `wash_block` → `Lavado de block`
- `resize_rods_4` → `Rectificado de 4 bielas`

English:

- The same stable keys render the existing English catalog labels.

#### Tests

- Every enum case has Spanish and English labels.
- Every component returned by every item type has Spanish and English labels.
- Every active seeded service key has Spanish and English catalog entries.
- Catalog full and filtered responses localize correctly for both locales.
- Catalog cache isolation uses normalized locales only.
- Authenticated and public order resources contain stable values plus correct localized labels.
- The screenshot scenarios render no raw motor/component/service keys.

#### Status

- [x] Motor item/component catalogs complete.
- [x] Service/status/priority labels are locale-aware.
- [x] Resources preserve stable values and add localized presentation.
- [x] Catalog and resource tests pass in both locales.

### Phase 4 — Localize formatting, validation, API errors, auth, and history

#### Scope

1. Replace duplicated hard-coded date/number formatters with the shared locale formatting helper.
2. Format MXN amounts using the active locale while preserving two-decimal monetary precision and existing totals.
3. Publish/complete Laravel auth, password-reset, and validation catalogs for both locales.
4. Move custom Form Request messages to translation keys, including nested order/item/service validation attributes.
5. Move user-facing controller and route response messages to translation keys:
    - order lifecycle mutations;
    - public tracking not-found;
    - attachments;
    - profile/password/account actions;
    - authentication/registration/verification;
    - catalog invalid input;
    - user-facing order history errors.
6. Replace `useOrderApi` English fallback literals with stable error-kind handling and localized client fallbacks.
7. Prefer stable API error codes for client branching while retaining a localized human `message`.
8. Translate generated history descriptions using field/event templates and localized status/priority/boolean/date
   values.
9. Keep all existing `order_histories` rows and canonical `old_value`/`new_value` data unchanged.
10. Ensure public history remains intentionally redacted/generic where current privacy rules require it.

Operational health-check messages may remain stable operator-facing API text if they have no end-user consumer. Record
that decision explicitly rather than accidentally omitting them.

#### Tests

- Locale-aware date, number, and MXN formatting for Spanish and English.
- Standard and custom validation messages in both locales, including nested fields.
- API error code stability plus localized messages.
- Auth/password flows in both locales.
- History descriptions for set, removed, changed, status, priority, date, boolean, and empty values.
- A regression assertion that localization does not update or duplicate history rows.
- Public tracking validation/not-found/rate-limit/network UI states in both locales.

#### Status

- [x] Formatting follows the active locale.
- [x] Validation/auth/API messages are translated.
- [x] History is presentation-localized without data migration.
- [x] Focused backend/frontend tests pass.

Operational health-check messages remain stable operator-facing API text because they have no end-user consumer.

### Phase 5 — Localize mail and notifications per recipient

#### Scope

1. Implement Laravel's `HasLocalePreference` contract on `User` using the allow-listed persisted locale.
2. Complete Spanish and English notification keys for every lifecycle/audit event.
3. Pass localized status and priority labels into mail; never interpolate raw enum values into a human sentence.
4. Move `OrderCreatedMail` subject, Markdown headings, labels, unassigned fallback, action, thanks, and footer text into
   language files.
5. Verify each queued notification/mailable renders in the recipient's locale, not the locale of the staff user who
   triggered the order change.
6. Preserve current customer/admin recipient rules and queue behavior.
7. Ensure administrator notifications can render separately for administrators with different locale preferences.
8. Do not translate user-authored order titles, descriptions, or names inside mail.

#### Tests

- Content tests for Spanish and English `OrderCreatedMail`.
- Content tests for every lifecycle customer notification.
- Audit-notification tests in both locales.
- Two recipients with different locale preferences receive independently localized content.
- Queued notification/mail assertions follow the project's queue conventions.
- Existing recipient, after-commit, and lifecycle behavior remains green.

#### Status

- [x] User locale preference drives mail/notification locale.
- [x] Order-created and lifecycle/audit content is fully translated.
- [x] Recipient-specific dual-locale tests pass.

### Phase 6 — Add durable i18n regression guards

#### Scope

Add automated guards without introducing a new dependency unless separately approved.

1. Frontend key parity test: `es` and `en` have identical recursive key paths.
2. Frontend missing-key test: known application call sites resolve in both locales.
3. Backend catalog parity test: all PHP translation groups required by the app exist in both locales.
4. Domain coverage test: every status, priority, item type, component, service key, notification event, and custom
   validation key resolves without returning its key.
5. Vue template audit using the already installed Vue compiler where practical:
    - detect bare visible text and static presentational attributes in application-owned Vue files;
    - maintain a narrow documented allow-list for proper nouns and technical/user-data examples;
    - do not use a broad regex that treats CSS classes, route names, or test selectors as translations.
6. Browser-console/missing-translation assertion for critical pages in development/test.
7. Document test conventions inside existing test code/configuration only if needed; do not create unrelated
   documentation.

#### Status

- [x] Frontend and backend parity guards pass.
- [x] Domain translation coverage guard passes.
- [x] Bare-string audit has a reviewed narrow allow-list.
- [x] Missing/fallback translation warnings are absent from critical flows.

The Vue template audit allow-list is intentionally exact and limited to the `Laracasts` proper noun, the
`email@example.com`
technical example, and punctuation-only placeholders such as `—`.

### Phase 7 — Final application audit and release verification

#### Automated verification

Run focused tests first, then the complete relevant suite.

```bash
vendor/bin/sail artisan test --compact tests/Feature/Auth
vendor/bin/sail artisan test --compact tests/Feature/Settings
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/CatalogControllerTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/CatalogSeederIntegrationTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/PublicOrderTrackingTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Http/Controllers/OrderLifecycleControllerTest.php
vendor/bin/sail artisan test --compact tests/Feature/app/Mail
vendor/bin/sail artisan test --compact tests/Feature/app/Observers
vendor/bin/sail yarn run test:unit
vendor/bin/sail yarn vue-tsc --noEmit
vendor/bin/sail yarn run build
vendor/bin/sail yarn run format:check
vendor/bin/sail yarn eslint resources/js tests/Frontend vitest.config.ts
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail composer run test:types
vendor/bin/sail artisan test --compact
git diff --check
```

If parallel PHPUnit reports database-isolation failures, retry the same suite with `--recreate-databases` before
diagnosing an application regression.

> **Handoff note for subsequent chats:** `Docker or Podman is not running` and Docker-socket permission errors happen
> before Artisan, Composer, PHP, or Node starts. Treat them as terminal-access failures, not application failures.
> First restore the runtime (`docker desktop start` when Docker Desktop is installed, then `vendor/bin/sail up -d`),
> confirm the services with `vendor/bin/sail ps`, and verify command access with `vendor/bin/sail artisan list`. Retry
> the exact failed Sail command after access is restored. Do not switch to host PHP/Composer/Node/Yarn or claim tests
> passed while the container runtime is unavailable; if the socket remains denied, request the approved escalated
> command access and continue with the same Sail command.

#### Manual bilingual browser matrix

Verify every row in both Spanish and English at desktop and mobile widths.

| Surface              | Required checks                                                                                                              |
|----------------------|------------------------------------------------------------------------------------------------------------------------------|
| Welcome              | Head title, headings, links, and CTA copy.                                                                                   |
| Authentication       | Login, registration, forgot/reset password, confirmation, verification, validation, success states.                          |
| Dashboard/navigation | Sidebar/header, account menu, page title, dates, empty/loading states, runtime locale switch.                                |
| Settings             | Profile, password, appearance, delete-account warning/dialog, save states.                                                   |
| Order index/create   | Labels, catalog item/components, nested validation, buttons, empty/loading/error states.                                     |
| Order detail         | Breadcrumbs, status/priority, dates, item/components, services, financials, history, attachments, dialogs, lifecycle errors. |
| Engine options       | Page copy, selected locale, components, services, numbers, measurement indicator, failures.                                  |
| Public tracking      | Form, validation, 404, 429, network error, order data, item/components, services, history, attachments.                      |
| Mail                 | HTML and text output for customer and administrator recipients in both locales.                                              |
| Accessibility        | Screen-reader text, ARIA labels, tooltips, dialog titles/descriptions, focus announcements.                                  |

For each locale switch:

- the visible UI changes immediately or after the intentionally documented Inertia reload;
- API-fetched catalog/order/history content uses the same locale;
- dates and MXN formatting change locale conventions;
- user-authored content remains unchanged;
- no raw snake-case domain key appears;
- no English copy appears in Spanish except approved proper nouns/technical terms;
- no Spanish copy appears in English except approved proper nouns/user-authored content;
- a reload and a new session follow the persistence rules.

#### Final status

- [ ] All focused tests pass.
- [ ] Full PHPUnit and frontend suites pass.
- [ ] Type checking, build, formatting, linting, Pint, PHPStan, and diff check pass.
- [ ] Manual bilingual browser matrix passes.
- [ ] No business-rule, privacy, authorization, lifecycle, total, history, or notification-recipient regression found.
- [ ] Plan statuses were updated only after verification evidence was recorded.

## Edge cases that must not be skipped

- Browser locale is `es-MX`, `en-US`, uppercase, underscore-separated, weighted, unsupported, or absent.
- Authenticated preference conflicts with anonymous cookie/session/browser language.
- Locale changes while catalog/history/order requests are in flight; stale responses must not overwrite the new locale's
  content.
- Cached catalog response from one locale must never be served for another.
- Missing translation key must be detected in tests rather than silently displaying a key or mixed-language fallback.
- Order has no components, services, history, attachments, assignee, notes, or completion date.
- A legacy service key/catalog row or historical enum value lacks a translation.
- One queued event notifies recipients with different locale preferences.
- Queue execution occurs after the triggering HTTP request has ended.
- Octane serves consecutive requests in different locales.
- Public tracking remains unauthenticated and public-safe under every locale.
- User-authored Spanish or English content must not be altered when the interface locale changes.
- Long English/Spanish labels must wrap without hiding actions or table data.
- Pluralization covers zero, one, and many.
- Dates are invalid/null and monetary values are zero, partial, paid, or overpaid.
- Validation uses nested array attributes such as items, components, and services.

## Scope boundaries

In scope:

- Laravel/Inertia/Vue web application in `lumasachi-backend`;
- user-visible authenticated/public UI;
- server-generated validation/API messages consumed by the UI;
- domain labels, formatting, history presentation, mail, and notifications;
- tests and configuration needed to keep Spanish and English complete.

Out of scope unless separately requested:

- translating user-authored database content;
- changing business rules or order lifecycle behavior;
- changing catalog prices or monetary calculations;
- changing authorization or public-resource privacy;
- translating source-code identifiers, route names, log keys, or database schema names;
- `lumasachi-react-native`;
- adding a third locale;
- adding a new dependency without approval;
- localizing operator-only health payloads if no end-user consumes them.

## Handoff protocol for subsequent chats/agents

1. State the phase being implemented.
2. Read `docs/Business_Rules.md`, this plan, relevant sibling files, and relevant tests.
3. Use Laravel Boost `search-docs` before code changes.
4. Inspect `git status --short`; preserve unrelated changes.
5. Implement only the selected phase and its required tests.
6. Run the phase's focused verification through Sail.
7. Review edge cases and confirm stable API/business values were not translated in storage.
8. Run Pint for PHP changes and the relevant frontend formatting/lint/type checks for Vue/TypeScript changes.
9. Update only the completed phase checkboxes after verification succeeds.
10. Report exact commands/results, remaining unchecked items, and any inference or blocker.

Do not describe a phase as complete when only one locale, static text, or a focused happy path has been verified.
