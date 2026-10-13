# Multi-company tenancy implementation plan

## Status, scope, and priority

Planning date: 2026-10-09; implementation decisions updated 2026-10-10. The user authorized implementation on
2026-10-09. Steps 0–1 and 3–4 are complete; Step 2 remains in progress; Steps 5–6 are complete; Steps 7–8 are in
progress; Step 9 is in progress and Step 10 is in progress with release gates pending. Tenant authentication (including
stateful API CSRF protection), central onboarding, the tenant-host order-create journey, and initial queue-worker
isolation are implemented and verified. Cache/storage hardening, the complete two-company
browser lifecycle, migration audit/rehearsal, and deployment gates remain open. This does not authorize deployment or
destructive changes to existing databases or storage.

Scope: `/Users/jesus.garciav/Sites/lumasachi-backend`. React Native changes are outside this plan. Inventory external
API consumers before changing their authentication or host contract.

**Priority criterion: prove company isolation before enabling company operations.**

Mark a step complete only when its requirements and completion gate pass. Record commands, results, failures, and
unrun checks in the verification ledger. A checked planning step does not mean the corresponding feature exists.

### Implementation sequencing and delegation

The hard dependency chain is Step 1 → Step 2 → Step 3 → Step 4 → Step 5. After Step 5, Step 6 (onboarding) and
Step 7 (queues, cache, files, and worker isolation) may run in parallel with disjoint write scopes. Step 8 depends on
both; Step 9 depends on Steps 3–8; Step 10 follows Step 9. Do not start the tenant-host order-form change before these
boundaries exist: the tenant host, not a form field, must determine company context.

Delegated read-only audits completed during implementation startup:

- API-consumer inventory: complete; findings and mobile contract risks are recorded below.
- Sail/PostgreSQL/Dusk harness inventory: complete; it found no guarded per-tenant test-database lifecycle and no
  multi-company hostname setup. Step 2 must add a test-only database guard before creating or dropping databases.

Completed delegated code slices: package-provider/tenant-route scaffolding cleanup and the fresh tenant migration
baseline. Both were kept to non-overlapping files; the new tenant migration path has not been applied to any shared
database.

Code-writing subagents will be assigned to dependency-ready groups with non-overlapping file ownership; the primary
agent retains integration, review, and final verification.

## Accepted decisions and explicit interpretations

| Decision                      | Contract                                                                                                                                            |
|-------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| Isolation                     | One PostgreSQL database per company; a separate central database for platform administration.                                                       |
| Staff and customer membership | Each account belongs to one company; no shared login or company membership switching for company users.                                             |
| Email identity                | The same email may have independent accounts in multiple company databases, with separate credentials and recovery.                                 |
| Domains                       | Company subdomains now; allow a domain registry to support verified custom domains later.                                                           |
| Authentication                | Company sessions stay on their own subdomain; central Super Administrators use a separate administration area.                                      |
| Central access                | Super Administrators can list companies and deliberately enter an authorized company context.                                                       |
| Audit recipients              | Company Administrators receive only their company's audit notifications. Preserve central Super Administrator audit delivery.                       |
| Central mutation attribution  | Tenant history records a non-login, tenant-local support actor and the central administrator's immutable UUID; no cross-database FK.                |
| Public tracking               | Only the order's company subdomain; no central lookup or cross-company fallback.                                                                    |
| Initial defaults              | Each company gets an independent copy of the same catalog, prices, taxes, and configuration defaults; these may diverge later.                      |
| Order-form financial display  | Keep the current order-form policy: pre-tax and tax-inclusive totals remain hidden.                                                                 |
| Existing records              | The user says current data is fake. Audit ownership before any migration/reset; block ambiguous records instead of assigning them by guesswork.     |
| Onboarding                    | Central Super Administrator creates the company, chooses its subdomain, and invites its first Administrator.                                        |
| Tenant user administration    | Only Central Super Administrators manage tenant users, using an active, company-scoped support session; tenant users cannot administer other users. |
| Dependencies                  | A maintained tenancy dependency is acceptable if it meets the requirements.                                                                         |

**Interpretation of "same data" (confirmed):** identical reference/configuration values are stored as independent
copies in company databases. Customers, staff accounts, orders, histories, payments, refunds, and attachments remain
private. Initial company name/contact/domain necessarily differ. No company-specific catalog editor or override
engine is required for v1. Use one versioned default dataset and an explicit update process to keep reference values
equal during v1; a seed performed only at onboarding would not keep existing companies equal after later default
updates.

Separate databases are a technical isolation choice, not a certification or claim of legal compliance. No regulatory
framework, jurisdiction, residency target, hosting provider, retention policy, or recovery objective has been specified.
Those are deployment inputs; do not invent them.

## Current application evidence

The Sail PHP CLI is 8.5.8 (the supplied project guidance says 8.4). The app requires PHP >=8.5.0. The old ignored
project-root FrankenPHP v1.9.0/PHP 8.4.10 binary caused the Dusk worker's Composer platform-check fatal. With the
user's explicit approval, it was replaced by the official FrankenPHP v1.12.7 Linux aarch64/glibc binary embedding
PHP 8.5.11; the original is preserved at
`/private/tmp/lumasachi-frankenphp.wBEth4/frankenphp-v1.9.0-php8.4.10`. Dusk remains on FrankenPHP. The package
query confirmed Laravel 13.35.0, Inertia Laravel 2.0.28, Octane 2.21.0, Sanctum 4.3.3, Pest 4.7.8, and Dusk
8.7.1. Recheck versions before later stages. The current database schema is PostgreSQL. At the initial planning pass,
there was no tenancy dependency in `composer.json`; Stancl Tenancy is now installed. `.ai/rules` was absent then and
remains absent in this checkout.

| Existing area       | Evidence and consequence                                                                                                                                                              |
|---------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Companies and users | `app/Models/Company.php`, `app/Models/User.php`: nullable company membership, one user role, and company metadata already exist.                                                      |
| Order ownership     | `app/Models/Order.php`, current database schema: orders have participant/actor IDs but no persisted company owner.                                                                    |
| Intake scoping      | `app/Services/OrderParticipantQuery.php`, `StoreOrderWithItemsRequest`, `OrderLifecycleService`: participants are scoped, but selected company context is removed before persistence. |
| Order access        | `app/Policies/OrderPolicy.php`, `app/Services/OrderAdministrationQuery.php`, `OrderController`: Admin/Super Admin queries and permissions assume globally visible orders.             |
| User administration | `UserPolicy`, `UserAdministrationQuery`, `UserService`: company filtering and global Super Administrator protections must be split between central and company contexts.              |
| Actor foreign keys  | Order histories, orders, payments, services, attachments, and refunds reference the local `users` table. Central user IDs cannot be inserted into these foreign keys.                 |
| Catalog             | `app/Models/ServiceCatalog.php`, `database/seeders/ServiceCatalogSeeder.php`: catalog values are currently global; order services already store price snapshots.                      |
| Notifications       | `app/Traits/NotifiesAdmins.php`, `UserAdministrationIncidentReporter`: recipients currently come from global user queries.                                                            |
| Authentication      | `config/auth.php`, `config/session.php`, `config/sanctum.php`, `routes/auth.php`, `routes/api.php`: one user provider and no company host boundary.                                   |
| Request forgery     | `bootstrap/app.php` exempts `api/*` and `sanctum/*`; verify tenant cookie-authenticated mutations are protected when introducing the new session contract.                            |
| Infrastructure      | Cache traits, `config/filesystems.php`, `config/queue.php`, `config/octane.php`: tenant namespaces and worker context restoration require explicit verification.                      |
| Browser harness     | `tests/DuskTestCase.php`: existing Dusk/Selenium harness should be extended for company hosts rather than replaced.                                                                   |

Some earlier plan sections describe pre-implementation behavior. Inspect live code before reusing them. This plan
supersedes the lookup-only company boundary in `ORDER_CREATE_PARTICIPANT_SCOPING_PLAN.md` for tenancy work; it does
not revoke its role, active-participant, or validation protections.

## Current implementation inputs, findings, and open questions

Verified through Sail/Boost on 2026-10-09:

- Sail runs PHP 8.5.8, although the supplied `AGENTS.md` says PHP 8.4. Use the actual container runtime unless that
  runtime is deliberately changed.
- Direct packages include Laravel 13.35.0, Inertia Laravel 2.0.28, Octane 2.21.0, Sanctum 4.3.3, Pest 4.7.8,
  Dusk 8.7.1, and Larastan 3.13.0. Stancl Tenancy v3.10.1 is now installed; full application adoption remains in
  progress.
- The database is PostgreSQL. `companies` is central-style metadata; `users.company_id` is nullable and has a foreign
  key to it. `orders` has no company owner. Tenant migrations must remove cross-database foreign-key assumptions
  rather than copying the current combined schema unchanged.
- Stancl Tenancy 3.10.1 resolved successfully with Laravel 13.35.0 through Sail; the package scaffold and real
  PostgreSQL tenant database manager are installed. Fresh tenant migrations have only run in guarded disposable tests.
- The checked-in test configuration uses a PostgreSQL `testing` database. A guarded helper creates and cleans up only
  generated tenant test databases. The initial browser fixture failure was resolved: the Dusk harness now provisions
  two tenant databases and host aliases, and the latest FrankenPHP Dusk run passes (see the verification ledger).

External API consumer audit (read-only; React Native remains outside the authorized backend scope): the mobile app
uses one build-configured API host and does not send company identity at login. Because equal emails may identify
different company accounts, mobile must choose a company host before login or remain a documented integration
follow-up. The audit also found existing mobile/backend mismatches in profile lookup, attachment upload paths/field
names, and download response shape. Do not silently expand this backend task into mobile changes; settle its scope with
the user and record any required client follow-up.

The Step 3 order-flow audit originally found three pre-form blockers: routes lacked tenant initialization, order intake
accepted a submitted `company_id`, and audit recipients did not distinguish tenant Administrators from central Super
Administrators. Step 4/8 now resolve host context and remove company selection from the form; central queue storage and
support attribution are implemented. The complete tenant/central recipient assertion matrix and remaining queue cases
are still Step 7 verification work, so these historical findings are not a claim that the entire notification boundary
is finished.

The following inputs remain unresolved before dependent implementation:

- Central Super Administrator hostname remains a deployment choice. Configuration now separates exact central hosts
  (`TENANCY_CENTRAL_HOSTS`) from tenant parent domains (`TENANCY_CENTRAL_DOMAINS`), supporting either
  `app.example.com` with `{company}.example.com` or a root central host without inventing a production domain.
- Trusted reverse-proxy IP/CIDR ranges are unknown. Forwarded host/protocol headers are not trusted by default; add
  only the hosting provider's verified proxy addresses during deployment, never a speculative wildcard.
- Resolved 2026-10-10: the user confirmed tenant-local non-login support actor plus central administrator UUID for
  Super Administrator audit attribution, and confirmed that order-form totals remain hidden. Feature/Dusk coverage
  now proves those behaviors; the decision is recorded in Steps 5 and 8.
- Resolved 2026-10-10: only Central Super Administrators manage tenant users. Company Administrators and other
  tenant users must not list, create, update, or delete other tenant users. Central administration must use an active,
  company-scoped support grant and must never grant tenant users central identity or cross-company access. Ordinary
  self-service profile settings remain separate from user administration.
- React Native: the authorized scope is this backend repository. Track mobile tenant-host selection as an integration
  follow-up; do not change the client without expanded scope.

Deployment DNS/TLS, database-role provisioning, backup/recovery, and legal requirements remain deployment inputs and
must not be guessed during local implementation.

## Options and selected architecture

| Option                              | Benefit                                                                   | Cost / reason for selection                                                                         |
|-------------------------------------|---------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| Shared database/schema              | Lowest provisioning overhead; easy global reporting.                      | Relies heavily on row scoping. Does not best match the requested database separation.               |
| PostgreSQL schema per company       | Namespace separation in one database.                                     | Restore, permissions, and search-path management remain coupled. Not selected.                      |
| Database per company                | Independent data, migrations, backup/restore, and database access grants. | More provisioning and rollout work. Selected to match the user's isolation priority.                |
| Separate app deployment per company | Independent compute and release schedules.                                | Unnecessary for the current request; revisit only for a concrete hosting or compliance requirement. |

Use one application deployment with a central connection and a dynamically selected company connection. Each company
database should have a restricted runtime database role; a separate provisioning role manages database creation and
migrations. Do not assume default package provisioning provides PostgreSQL role isolation. Prove that company A's
credentials cannot access company B's database. Sharing a server does not provide separate compute or residency.

Selected and installed: `stancl/tenancy` v3.10.1, whose dependency constraints and successful Composer resolution are
compatible with the installed Laravel 13.35.0 application. Use v3 documentation, not development v4 examples. Its
PostgreSQL database manager does not meet the accepted per-tenant PostgreSQL login requirement by itself, so the app
uses a small custom manager that requires a non-superuser provisioning login and creates a separate restricted
tenant runtime login plus a NOLOGIN database-owner role; tenant passwords are encrypted. The provisioner remains a
powerful account and must stay distinct from the central runtime role; isolating its credentials into a separate
provisioning process remains an operational security decision. Spatie Multitenancy
remains a maintained alternative, but its documented multi-database approach leaves database creation to the
application, requiring more custom provisioning here.

### Data placement

| Central database / infrastructure                                                   | Company database / storage                                       |
|-------------------------------------------------------------------------------------|------------------------------------------------------------------|
| Company registry, unique domains, provisioning state, encrypted connection metadata | Company users, sessions, password resets, personal access tokens |
| Central Super Administrator identities, central sessions/recovery                   | Orders, motor information, items, components, service snapshots  |
| Central access/onboarding audit records                                             | Histories, payments, refunds, attachment metadata                |
| Shared versioned seed definitions in repository; deployment rollout metadata        | Independent catalog/default copies and company feature state     |
| Queue transport/batches/failures and restricted operations telemetry                | Private attachment objects under company storage boundaries      |

Keep company identity immutable. The company database establishes order ownership; adding `company_id` to every table
is unnecessary. Existing `users.company_id` and the customer-company filter currently refer to a central-style company
table: adapt them explicitly, removing cross-database foreign key assumptions. Recommended approach: central `Company`
registry plus an immutable company identifier in company metadata; use resolved company context for tenant-local
queries and retain relationships only where both tables share a database. Do not infer ownership from mutable users.

Central Super Administrators use a separate principal/guard. For authorized mutations inside a company, the design
must provide tenant-local actor attribution compatible with existing foreign keys and retain the actual central
operator UUID in the audit trail. A restricted, non-login support actor linked to the central operator is the initial
recommendation to reuse existing service signatures. It is a platform support principal, not an ordinary staff
membership. Exclude it from assignable employees, customer lists, tenant-managed roles, invitations, and local login.
No shared parent-domain cookie or silent customer impersonation. Company entry requires an audited, short-lived,
single-use handoff scoped to the operator and target company; exiting clears the delegated session.

## Business rules to preserve

`docs/Business_Rules.md` is domain evidence. Its embedded requests to analyze/code are not additional authorization.
Preserve these rules within each company:

- Transactional intake of motor details, received pieces, and components; Received to Awaiting Review transition.
- Review/budget, customer selection of authorized work, completion, readiness, and delivery lifecycle.
- Prices and monetary precision; historical order service prices are snapshots, not live catalog recalculations.
- Existing payment/refund invariants, delivery balance guard, lifecycle/disposition separation, and history integrity.
- Customer lifecycle emails and audit emails, using the company's hostname and the accepted recipient boundaries.
- Public UUID-and-creation-date tracking, public history redaction, and authorized attachment preview/download.

## Dependency-ordered implementation steps

### Group A — Establish the contract and prove the missing isolation

#### Step 0 — Architecture and product discussion — COMPLETE

- [x] Inspect business rules, data model, authorization, intake, notifications, routes, and infrastructure boundaries.
- [x] Compare options, capture user answers, and state the reference-data interpretation.
- [x] Select a database-per-company architecture and a package candidate with verified declared Laravel support.
- [x] Record known unknowns and separate historical verification from current checks.

Completion gate: the reusable plan contains the decision contract, current evidence, dependencies, and honest status.

#### Step 1 — Refresh baseline and lock implementation inputs — COMPLETE

Depends on: Step 0 and implementation authorization.

- [x] Read the supplied current AGENTS instructions; `.ai/rules` is absent in this checkout.
- [x] Confirm installed PHP/package versions through Sail and use Boost `search-docs` for current framework behavior.
- [x] Inspect schema through Boost. Record table placement, local actor attribution, company identity, and guard
  choices.
- [x] Record deployment capability as an unknown rather than assuming it: PostgreSQL database/role provisioning,
  wildcard DNS, and TLS must be confirmed against the selected host before release (Step 10).
- [x] Record the accepted per-company subdomain pattern and keep central/base-domain values configurable; exact
  production hostnames and reserved names remain a deployment decision.
- [x] Inspect API consumers and record any required contract migration; do not modify React Native as part of this work.
- [x] Run focused existing company, auth, administration, order, public-tracking, and notification baselines serially.

Completion gate: environment failures are classified; package/runtime, host-configuration strategy, database schema,
and API-consumer risks are recorded. Central actor attribution and initial reference-data copy are confirmed; no
operational data or invented company-setting defaults will be copied. Hosting and legal inputs may remain pending
until their deployment gates, and no code relies on invented production hostnames.

#### Step 2 — Write the red isolation specifications — IN PROGRESS

Depends on: Step 1.

- [x] Use Pest for new PHP specifications; retain existing coverage. Two existing Dusk tests received narrow updates
  for the verified route redirect and current financial-visibility policy; neither implements tenant behavior.
- [x] Add tenant-host Vitest/Dusk journeys and provision two ready tenant databases/hosts in the Dusk harness. Dusk
  covers tenant order intake on a company host, central entry/exit, and wrong-company public tracking; see Step 8
  for the remaining full two-company lifecycle gate.
- [x] Add a test-only PostgreSQL lifecycle helper guarded by `APP_ENV=testing`, the `pgsql` driver, an explicit
  writer-host allowlist, an exact writer connection to database `testing`, and a superuser cleanup connection;
  create/drop only generated databases and roles registered by that helper.
- [x] Prove the test helper can create two disposable databases, isolate distinct records, and clean them up; reject
  a valid-looking unowned name and a writer host outside the allowlist.
- [x] At the harness level, provision two disposable databases with overlapping synthetic user/order IDs and the
  same email, but distinct credential markers and order data. This is not yet an application-level tenancy test.
- [x] Refuse to register pre-existing database/runtime-role collisions as disposable resources; allow only the exact
  PostgreSQL bootstrap-grantor membership (OID 10) with ADMIN enabled and SET/INHERIT disabled.
- [x] Use real PostgreSQL connections to prove tenant credentials cannot connect to another tenant database; feature
  tests now also prove tenant-host order and attachment boundaries. Reused-worker gaps are listed below.
- [x] Verify tenant runtime credentials cannot connect to the central database after the central ACL migration; on
  tenant deletion, disable the login, terminate existing tenant sessions, then drop the owned database.
- [x] Cover wrong host, unknown/inactive host, forged company input, foreign UUID, and unauthorized central access.
- [x] Cover independent same-email accounts, login, verification, resets, token issuance, logout, CSRF, and session
  replay across tenant hosts.
- [x] Verify tenant-host order listing/show/history and authenticated/public attachment download/preview boundaries.
  Alpha data is available on Alpha; Beta receives no Alpha order, history, attachment, or tracking fallback.
- [ ] Complete the remaining cross-company matrix for all order descendants/refunds, every notification event's
  recipients, and catalog-default behavior; focused endpoint tests do not cover every lifecycle path.
- [x] Specify onboarding retry/failure behavior, central company entry, central actor attribution, and default updates.
- [ ] Complete A -> B -> unknown-host request and job transitions in a reused worker, including central jobs and
  failures between all transitions. Request cleanup and queue A/B/failure coverage pass; unknown/central job cases
  remain open.
- [x] Use real PostgreSQL connections for isolation proof; disposable-database tests prove database credentials and
  host-routed requests use the intended company databases.

Completion gate: failures demonstrate missing intended behavior, not broken test provisioning. Foundational
provisioning and host/auth specifications pass; finish the remaining cross-company graph and worker cases.

### Group B — Build database and host boundaries

#### Step 3 — Adopt the package and split central/company persistence — COMPLETE

Depends on: Step 2.

- [x] Resolve a stable compatible Stancl release through Sail; exact version is 3.10.1 and Composer resolution passed.
- [x] Separate the migration paths without rewriting legacy migrations: move the combined history intact to
  `database/migrations/legacy/`, keep fresh company schemas under `tenant/`, and limit non-testing default discovery
  to `central/`. Testing intentionally uses the legacy path for the existing pre-cutover suite. Focused discovery,
  central-schema, tenant-schema, and tenant-isolation tests pass; `tenancy:migrate-central` pins both connection
  and path. Composer no longer runs the legacy migration history automatically on project creation.
- [x] Keep each company's order graph, user references, monetary columns, and UUIDs internally consistent. The new
  tenant schema includes the local order graph and passed its fresh disposable-database migration test; the
  PostgreSQL manager/test-helper review and central Company/Tenant/domain mapping passed. A two-database PostgreSQL
  test inserted a valid local user/order/item/service graph in one tenant and verified the other tenant remained
  without those operational rows.
- [x] Create central Company/domain records and separate central authentication storage. The central registry stores
  only UUID, name, and active status; tenant profile/branding fields live in the tenant `company_settings` table.
  The Company UUID is not mass assignable and model updates reject UUID changes; central Company no longer exposes
  tenant-user relationships. Central Super Administrator storage is separate. Tenant seeding is configured to
  use `TenantDefaultsSeeder`, not the operational `DatabaseSeeder`; sample Company seeding is restricted to local
  and test environments.
- [x] Prove a central Company, Tenant, and subdomain mapping round-trip through one immutable UUID; verify domain
  normalization in a guarded disposable central database.
- [x] Separate database provisioning permissions from restricted runtime permissions and protect credentials. The
  manager requires a non-superuser CREATEDB/CREATEROLE provisioner and creates per-company restricted runtime and
  NOLOGIN database-owner roles; passwords are encrypted. It repairs ACLs after an interrupted create only when the
  existing database owner and runtime-role privileges match the expected tenant. PostgreSQL's automatic
  bootstrap-granted ADMIN membership for tenant roles is accepted only from PostgreSQL's bootstrap superuser (OID 10)
  with SET/INHERIT disabled; the explicit temporary SET grant is grantor-scoped and is revoked on
  normal completion/retry. Keep the provisioner out of
  ordinary runtime credentials; production role provisioning remains a release gate.
- [x] Make independent reference seeds idempotent; retain order price snapshots during controlled default updates.
  `TenantDefaultsSeeder` synchronizes the tenant-local service catalog, including its current base prices and tax
  percentages; the same code/config feature defaults apply to each tenant. It does not seed operational records.
  The repository has no universal `company_settings` values to copy, so onboarding must set company-specific
  identity/branding rather than invent defaults. A guarded two-database PostgreSQL test verifies equal catalog
  starting values, repeatable synchronization, and unchanged order-service price snapshots.

Completion gate: two fresh company databases have identical reference values, independent operational records, valid
foreign keys, and tested connection boundaries. No cross-database transaction atomicity is assumed.

#### Step 4 — Resolve company hosts before authentication and binding — COMPLETE

Depends on: Step 3.

Host resolution is the prerequisite for tenant connection/model guards, so these protections are grouped with this
step rather than applied before a request has a trustworthy company context.

- [x] Register separate exact-central-host and tenant-only routes; initialize tenant context before sessions,
  authentication, and route binding. `/orders/create` is tenant-host-only.
- [x] Use Stancl's normalized, unique subdomain registry against configured, non-overlapping tenant parent domains;
  startup rejects nested parent suffixes. Custom-domain onboarding remains out of scope.
- [x] Reject unknown, inactive, unprovisioned, or missing-database companies before constructing a tenant connection;
  never search another database to resolve a resource.
- [x] Pin central company/readiness/provisioning queries to the central connection; tenant models use the package's
  `tenant` connection only after host initialization. PostgreSQL request tests prove A/B routing and reset after
  exceptions.
- [x] Guard every tenant-owned model against use without initialized context; no fallback to central data or a prior
  company's connection. Test-only legacy compatibility is opt-in under `APP_ENV=testing` only.
- [x] Allow only configured central hosts and one company label under configured tenant parent domains. No forwarded
  proxy headers are trusted until the hosting provider's proxy addresses are known; this deployment input is in
  Step 10. Tenant identity never comes from request-body company fields.
- [x] Keep public tracking and Stancl's tenant-asset route behind the same readiness/context-cleanup middleware, so
  they have no central-host fallback.
- [x] Preserve same-origin tenant API paths; central health routes are exact-host-only.

Tenant-generated mail, signed URLs, notification links, and queued links are tracked in Step 7 because their correct
host depends on worker and notification context. Dusk host/DNS fixtures are tracked in Step 8. These are not claims
that those later features are complete.

Completion gate: focused PostgreSQL tests and the full PHP suite prove central/company route boundaries, tenant
readiness, and request-context cleanup; invalid hosts fail closed. Passed on 2026-10-10. Actual reused-Octane-worker
transitions remain Step 7 work.

#### Step 5 — Isolate accounts, authorization, and central company entry — COMPLETE

Depends on: Step 4.

Resolved product input (2026-10-10): central Super Administrator tenant mutations use a non-login tenant-local support
actor plus the central administrator UUID in tenant audit history. No cross-database foreign key is permitted.

- [x] Configure separate central and company guards/providers, host-only secure cookies, and scoped session stores.
- [x] Resolve login/reset/verification/Sanctum token queries solely within the target company database.
- [x] Reject company A's cookie/token/reset link on B even when numeric user IDs and emails coincide.
- [x] Verify CSRF protection on cookie-authenticated state changes and tenant-aware login/lookup rate limits.
- [x] Restrict tenant user administration to a valid Central Super Administrator support session; tenant-local users
  cannot list/create/update/delete other users, and central roles remain outside tenant databases. Confirmed by the
  user on 2026-10-10: only Central Super Administrators may manage tenant users.
- [x] Remove legacy `users.company_id` and cross-database user/company relationships; derive company scope from the
  initialized tenant instead of a submitted or persisted company foreign key.
- [x] Preserve existing Employee/Customer permissions and company-local active/verification/password-change rules.
- [x] Implement audited central company entry/exit, preserving the original central identity on every support mutation.
- [x] Review global "last Super Administrator" protections: central identities use their own guard/store; no central
  administrator CRUD surface is in this implementation, so its future lifecycle controls remain outside this step.

Completion gate: every role, session, recovery, and token case in the isolation suite passes; support attribution is
accurate and no ordinary company identity acquires multi-company membership.

### Group C — Onboarding, background work, and application experience

#### Step 6 — Create companies and invite their first Administrator — COMPLETE

Depends on: Steps 3 and 5.

Confirmed onboarding scope: copy matching reference data into each new company database; do not copy fake operational
users, customers, orders, or attachments.

- [x] Central Super Administrator chooses unique subdomain/company metadata and the first Administrator's email.
- [x] Provision database/role, migrate, apply shared defaults, create Administrator, then send the tenant-bound
  invitation.
- [x] Track pending/ready/failed states; expose tenant routes only after readiness. Retry without duplicate
  users/databases.
- [x] Reuse existing verified-email and forced-password-change flows where suitable; never email a permanent password.
- [x] Keep cleanup of failed provisioning scoped and recoverable; do not automatically destroy populated databases.

Completion gate: onboarding succeeds end to end, failure/retry is tested, and only the invited company's account
changes.

#### Step 7 — Isolate queues, caches, files, and reused workers — IN PROGRESS

Depends on: Steps 4 and 5. May proceed alongside Step 6 with disjoint changes.

- [x] Keep queue transport and its jobs/failed-jobs tables on an explicit central infrastructure connection; include
  immutable tenant identity and initialize context before model deserialization. Queue configuration and central
  jobs/failures/batches migrations are explicit; a real worker test switches A/B and clears context after failure.
- [ ] Test queued mail/notifications, retries, failed jobs, missing/inactive tenants, central jobs, scheduled work, and
  CLI tenant loops. A/B/failure behavior passes; the remaining cases are open.
- [x] Prove identical cache keys/locks and private storage paths stay separate between two tenant contexts. The
  focused Redis-backed worker test now verifies A/B cache visibility, cache locks, private files, and context
  cleanup after a failed job.
- [x] Verify local private-disk separation and authorized/public attachment download/preview across two tenant hosts.
  The Alpha attachment succeeds on Alpha and returns not found on Beta for both authenticated and public routes.
- [ ] Verify feature-state invalidation and provider-level storage credentials/policies; local root separation alone
  does not prove cloud-object authorization.
- [ ] Generate tenant mail, signed URLs, notification links, and queued links from the recorded company domain; never
  fall back to the central host.
- [ ] Restrict runtime storage credentials/policies as supported by hosting; a path prefix alone is not an access grant.
- [x] Route tenant order-audit mail to that company's Administrator and the central Super Administrator; host tests
  prove another company's Administrator is excluded.
- [ ] Verify the recipient matrix for every lifecycle and central incident notification.
- [ ] Verify actual Octane driver and restore connections, auth, URL defaults, storage, mail/config, locale, and feature
  caches after each request/job, including exceptions. Do not rely solely on refreshing the test application.
- [ ] Restrict operational dashboards/logs to central authorized users; tenant identifiers aid incident tracing.
  Health checks that count queued/failed jobs must use the same explicit infrastructure connection, not the tenant
  default connection.

Completion gate: real worker A/B/error/central transitions, file isolation, cache isolation, and recipient assertions
pass.

#### Step 8 — Adapt the existing Inertia interface and API contracts — IN PROGRESS

Depends on: Steps 5, 6, and 7.

- [x] Add central company list/create/entry controls; tenant pages display resolved company identity.
- [x] Remove intake's submitted `company_id` selector and derive company context solely from the resolved tenant host;
  list only participants from that tenant database.
- [x] Adapt company filters/user administration to tenant-local data and keep the existing workflow components.
- [x] Preserve the confirmed existing financial-visibility behavior: do not expose pre-tax or tax-inclusive totals on
  the order form; verify the existing front-end contract remains intact.
- [x] Keep defaults identical initially; do not add customization UI, shared-customer sync, billing, or custom domains.
- [x] Cover invitations, company entry/exit, same-email independent logins, and wrong-host tracking in feature/Dusk
  tests. Same-email/session and invitation flows are feature-tested; browser tests cover entry/exit and tracking.
- [x] Exercise order creation/listing on both company hosts in Dusk, including same-email independent staff accounts,
  tenant-local customer choices, and proof that one company's order is absent from the other company's list.
- [ ] Exercise the complete review/approval/completion/payment/refund/delivery lifecycle on both hosts and the full
  central company-creation/invitation journey in a browser.
- [x] Configure test DNS/host resolution inside Sail/Selenium using `tenant.dusk` and `other.dusk` aliases.

Completion gate: the full order lifecycle and central onboarding journey pass through their actual company hosts.

### Group D — Audit, rehearse, and release

#### Step 9 — Audit fake legacy data and rehearse the transition — IN PROGRESS

Depends on: Steps 3 through 8.

- [x] Produce a read-only ownership audit: company mappings, null/mixed participants, missing references, duplicate
  identities/UUIDs, actor history, catalog contents, and attachment ownership. Never print credentials or personal data.
- [x] Block ambiguous mappings. Since existing data is fake, choose clean tenant fixtures rather than a speculative
  backfill; no operational records have been copied or reset.
- [x] Record the guard for any later old-data reset: it requires separate explicit authorization and a recoverable
  snapshot first. No reset has been requested or performed.
- [x] Rehearse fresh central + two-company database provisioning, migrations, and defaults in the isolated Dusk
  fixture; no production data is used.
- [ ] Rehearse upgrading an existing company database.
- [ ] Rehearse a failed rollout and recovery without deleting successful company data or reverting recorded payments.
- [ ] Verify isolated backup and restore into a disposable target, including attachments and reference integrity.

Completion gate: there is a reviewed audit, a successful rehearsal, and a concrete recovery path. Fake data is not
blanket permission to erase a database or storage directory.

**Sanitized read-only legacy audit snapshot (2026-10-10):** Laravel Boost queried the configured default PostgreSQL
database `laravel`; only aggregate counts were read and no customer names, emails, credentials, or file paths were
returned.

| Area                | Aggregate result                                                                                                     |
|---------------------|----------------------------------------------------------------------------------------------------------------------|
| Companies           | 7 total; 6 active.                                                                                                   |
| User ownership      | 237 users; 231 have no `company_id`; 6 have valid company references; 0 dangling company references.                 |
| Identity uniqueness | 0 duplicate company/user/order/history UUID groups; 0 duplicate email groups globally or within a company.           |
| Orders              | 59 total; 0 missing user references; all 59 have at least one participant/actor with no company mapping.             |
| Order participants  | All 59 orders have at least one unmapped participant; 0 can currently be assigned to a tenant from this heuristic.   |
| Histories           | 0 missing non-null actors.                                                                                           |
| Attachments         | 19 metadata rows: 18 point to existing orders and 1 to an existing order history; no unknown owner types or orphans. |
| Catalog             | 74 rows; 0 duplicate service keys.                                                                                   |

This snapshot is not a production-source certification and does not verify physical attachment objects, their access
policies, or backup recoverability. **Do not backfill these users/orders by inference:** all 59 orders remain
ambiguous under the recorded user-to-company mapping. Since the user identified current data as fake, the safe v1 path
is clean tenant provisioning and no operational-data copy; any reset of the existing database still requires explicit
authorization and a recoverable snapshot.

#### Step 10 — Final verification and deployment handoff — IN PROGRESS (release gates pending)

Depends on: Step 9.

- [x] Run focused tests first, then the full PHP suite, frontend tests, type/static checks, production build, and Dusk.
- [x] Run required Pint after PHP changes; inspect final diff and report unrelated baseline failures accurately.
- [x] Prove database runtime permissions, two-company auth/data isolation, and queue-worker A/B/error context cleanup.
- [ ] Record hosting, DNS/TLS, tenant role provisioning, backup/retention/recovery, and access controls required to
  release.
- [ ] Set exact central host (s), tenant parent domain (s), and verified trusted-proxy IP/CIDR ranges; confirm wildcard
  DNS and TLS for exactly one company subdomain label.
- [ ] Resolve specific regulatory/residency obligations if required; obtain the appropriate review before claiming
  compliance.
- [ ] Deploy only with explicit deployment authorization; migrate central/company stores deliberately and restart
  workers.
- [ ] Record release evidence and mark steps complete only after all gates pass.

Completion gate: all required verification and operational prerequisites pass, or unresolved failures explicitly block
release.

## Verification ledger and environment issues

| Date       | Check                                                                           | Result / limitation                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
|------------|---------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| 2026-08-21 | Focused company, company-user, user-policy, order-policy, and participant tests | Historical: 40 passed, 229 assertions. Not a current full-suite baseline.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 2026-08-21 | Sail environment                                                                | Docker unavailable; Docker Desktop started, Sail required approved access outside the sandbox, then Artisan ran successfully.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 2026-10-09 | Installed dependencies and PHP through Sail                                     | Sandbox initially reported `Docker or Podman is not running`; approved Sail execution succeeded. PHP 8.5.8; Laravel 13.35.0, Inertia Laravel 2.0.28, Octane 2.21.0, Sanctum 4.3.3, Pest 4.7.8, Dusk 8.7.1, Larastan 3.13.0. Stancl Tenancy v3.10.1 resolved and installed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 2026-10-09 | Boost schema and documentation                                                  | PostgreSQL schema inspected; Laravel 13 multiple-connection, scoped-container, Octane, and Sanctum guidance retrieved.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-09 | Current focused company/user/policy/order tests                                 | Exit 0; 40 tests, 229 assertions, 48.07s. All were classified as deprecated before the PHP 8.5 config fix below.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| 2026-10-09 | Current auth/admin/order/tracking/notification tests                            | Exit 0; 116 tests, 813 assertions, 18.33s. Existing tests were classified as deprecated.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-09 | Current order-create frontend baseline                                          | Exit 0; Vitest 4.1.9, 13 tests passed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-09 | Initial PHPStan baseline (superseded by final verification)                     | At this point, full `vendor/bin/sail composer run test:types` exited 1 with 59 findings in existing application files. Targeted analysis of changed paths passed. The final 2026-10-10 run below exits 0 with no findings.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 2026-10-09 | Initial Dusk failure and approved FrankenPHP repair                             | Dusk initially failed with `net::ERR_CONNECTION_REFUSED`; direct Octane startup showed Composer requires PHP >=8.5.0 but the ignored binary embedded PHP 8.4.10. User directed keeping FrankenPHP and explicitly approved replacing the binary with official v1.12.7 (aarch64/glibc, PHP 8.5.11). Original binary preserved at `/private/tmp/lumasachi-frankenphp.wBEth4/frankenphp-v1.9.0-php8.4.10`.                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-09 | Dusk Vite/runtime setup                                                         | After upgrading, Dusk still rendered blank pages because ignored `public/hot` pointed to `localhost:5173`, where no Vite server was serving assets. Preserved the stale marker at `/private/tmp/lumasachi-frankenphp.wBEth4/public-hot-stale.original`, built static assets through Sail, and recreated both FrankenPHP app services so long-lived workers loaded the current manifest. Build succeeds with an existing >500 kB chunk warning.                                                                                                                                                                                                                                                                                                                                                           |
| 2026-10-09 | Final full Dusk browser suite                                                   | `vendor/bin/sail artisan dusk`: 24 passed, 163 assertions. The existing ready-for-delivery action redirects to `/orders`; its Dusk journey now verifies that redirect and reopens the order. Order-review Dusk verifies the currently tested policy of not exposing pre-tax totals.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| 2026-10-09 | Full PHP and frontend unit suites                                               | `vendor/bin/sail artisan test --compact`: 784 passed, 5,544 assertions. `vendor/bin/sail yarn test:unit`: 21 files, 107 tests passed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-09 | PHP 8.5 database-config compatibility                                           | Replaced deprecated MySQL SSL CA constant in both MySQL connection options with `Pdo\Mysql::ATTR_SSL_CA`. `CompanyTest`: 9 passed, 17 assertions, no deprecation marker.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-09 | Tenancy test-harness audit                                                      | No guarded per-tenant PostgreSQL database creation/cleanup or multi-host Dusk setup existed. The new test-only name, ownership, writer-host, and database guards now bound disposable database operations; multi-host Dusk setup remains future work.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-09 | Disposable PostgreSQL test harness                                              | `DisposablePostgresDatabasesIsolationTest`: 3 passed, 12 assertions. Creates two uniquely named databases, verifies overlapping user/order IDs and same email with distinct credential/order markers, confirms cleanup, and rejects unowned names and an unapproved writer host. This verifies test infrastructure only, not application tenancy behavior.                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 2026-10-09 | Test-harness review findings                                                    | Independent review found an unowned-name connection path and a possible read-PDO safety check; both are fixed. The guard now verifies the configured allowlisted writer host and checks `current_database()` on the writer connection used for CREATE/DROP.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| 2026-10-09 | Initial new-test verification findings                                          | Focused PHPStan first found unchecked `PDO::query()` results; explicit result checks fixed both findings. A PHP 8.5 warning from redundant global imports was also removed. The final focused test rerun passed without warnings.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| 2026-10-09 | Order-flow Dusk test alignment                                                  | The approval/delivery test now follows the existing redirect after the Ready for Delivery transition. The review total assertion follows the existing frontend security specification: pre-tax/base amounts remain hidden; tax-inclusive totals stay visible. The mismatch between that test policy and the business-rules example is recorded as an open user decision.                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-09 | Current changed-file PHPStan and Pint                                           | Targeted PHPStan on `config/database.php`, the disposable-database helper, and its feature spec reports no errors. Targeted Pint passed for all PHP files touched in this pass.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| 2026-10-09 | Tenant PostgreSQL manager, schema, tests, and static analysis                   | Fresh tenant migrations ran only in a helper-generated disposable DB using its restricted owner role; required local order/auth tables existed and no central company table or `users.company_id` existed. Focused tenancy tests: 9 passed, 46 assertions. Targeted PHPStan: no errors. Pint: passed. No shared database was migrated.                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-09 | Tenant schema and PostgreSQL privilege review                                   | Two independent read-only reviews are in progress. PHPStorm inspection tool is unavailable in this session; PHPStan, Pint, real PostgreSQL integration tests, and manual schema/model comparison are being used instead.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-09 | Central migration boundary review                                               | Historical finding: at that point, default Laravel migration discovery still selected legacy migrations. Resolved on 2026-10-10 by `MigrationPathServiceProvider`, `tenancy:migrate-central`, and the Composer migration-script change below.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 2026-10-09 | Restricted PostgreSQL provisioner and manager hardening                         | Manager now rejects superuser/underprivileged provisioners and non-PostgreSQL URLs, reconciles uncertain database creation during rollback, and disables tenant login before database deletion. Active-session deletion is tested. Independent re-review is pending.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| 2026-10-09 | Focused tenancy PostgreSQL verification                                         | Exit 0: `vendor/bin/sail artisan test --compact tests/Feature/PostgreSQLTenantDatabaseManagerIsolationTest.php tests/Feature/Tenancy tests/Unit/PostgreSQLTenantDatabaseManagerTest.php`; 14 passed, 88 assertions. Includes central ACL denial, fresh central/tenant schemas, non-superuser provisioning, and deletion/retry behavior.                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-09 | Targeted PHPStan after manager hardening                                        | Exit 0 with no errors for changed tenancy manager, central migration, disposable PostgreSQL helper, and focused tests. An initial sandbox attempt reported Docker/Podman unavailable; the same Sail command succeeded with approved elevated access.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| 2026-10-09 | Restricted-provisioner test cleanup                                             | The first harness cleanup removed four exact temporary roles after catalog checks showed no owned databases and only `CONNECT` grants on `testing`. A later failed cleanup exposed duplicate per-grantor role memberships; see the 2026-10-10 retryable-cleanup entry.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-09 | PostgreSQL identifier quoting correction                                        | Initial tenancy test run found `PostgresGrammar::wrapValue()` is unavailable. Explicit identifier quoting replaced it; subsequent focused tests passed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-09 | `git diff --check`                                                              | Implementation paths were clean; generated `_ide_helper.php` has three trailing-whitespace lines in its already-dirty generated-file diff. It was left untouched to preserve pre-existing user changes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-09 | Docker/Sail environment and diagnostics                                         | The default sandbox initially reported `Docker or Podman is not running`; approved elevated Sail access worked. Laravel Boost `browser-logs` returned a transport-closed error, so container logs, served asset URLs, and screenshots were used instead. Postman was not needed or run.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-09 | Markdown formatting and diff                                                    | Sail Prettier passed. The pre-existing dirty `.phpstorm.meta.php`, `_ide_helper.php`, and `composer.lock` were preserved. The implementation changes are limited to database-test configuration/helper/specs and two existing Dusk files plus this plan.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-10 | Tenant reference-data defaults                                                  | `TenantReferenceDataSeederTest`: 1 passed, 11 assertions through Sail. Two guarded disposable tenant databases received equal catalog values; rerunning the seeder restored default values without changing the order-service price snapshot or seeding operational records. Targeted PHPStan on the helper/seeder slice passes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| 2026-10-10 | Docker/Sail access for current checks                                           | The default sandbox again reported `Docker or Podman is not running`; approved elevated Sail access succeeded for PHP syntax, the focused reference-data test, and targeted PHPStan. Do not substitute host PHP.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| 2026-10-10 | Retryable test-role cleanup                                                     | An earlier cleanup removed four exact temporary roles. Later failed attempts left some generated provisioner/owner-role artifacts; a read-only catalog check found no tenant databases at that time. Do not claim all stale roles are gone. The helper now revokes only recorded grants through their grantors and retains cleanup state when teardown fails; new-run cleanup is covered by the current focused suite.                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-10 | Legacy migration preservation                                                   | Compared every moved original migration against its tracked source; all 36 original files match byte-for-byte. Two separate Stancl scaffold migrations are not part of that original history and remain test-path-only; production central discovery excludes the legacy path.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 2026-10-10 | Restricted role membership behavior                                             | A disposable PostgreSQL test showed that role creation gives a non-superuser CREATEROLE role a bootstrap-superuser-granted ADMIN membership with SET/INHERIT false. PostgreSQL documents that this automatic membership cannot be removed by the CREATEROLE role. The manager/test now target and assert cleanup of the explicit temporary grant by its actual grantor, while allowing only the safe bootstrap membership to remain. See [role attributes](https://www.postgresql.org/docs/17/role-attributes.html), [GRANT](https://www.postgresql.org/docs/17/sql-grant.html), and [REVOKE](https://www.postgresql.org/docs/17/sql-revoke.html).                                                                                                                                                       |
| 2026-10-10 | Interrupted tenant database creation recovery                                   | Independent review found a worker-stop window after `CREATE DATABASE` but before access-control setup. Retry now verifies the database owner and restricted runtime role, repairs the intended database/schema grants, and still reports an existing database; mismatched state is not modified. A real PostgreSQL interruption simulation verifies own-tenant access works and another tenant's credentials cannot connect.                                                                                                                                                                                                                                                                                                                                                                             |
| 2026-10-10 | Focused tenancy foundation suite                                                | Exit 0: 21 tests, 131 assertions across migration discovery, central/tenant schemas, tenant credential isolation, equal reference seeds, manager lifecycle/ACL recovery, and unit behavior. All database lifecycle tests used only the guarded disposable PostgreSQL test helper.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| 2026-10-10 | Changed-path PHPStan and Pint                                                   | Targeted PHPStan on tenancy code and tests exits 0 with no errors. Pint passed on the changed manager and test files. An earlier broad `--dirty` Pint invocation reformatted moved legacy files; those formatting-only changes were reverted and original file contents were rechecked byte-for-byte. Generated `_ide_helper.php` and `.phpstorm.meta.php` were excluded to preserve unrelated root changes.                                                                                                                                                                                                                                                                                                                                                                                             |
| 2026-10-10 | PostgreSQL tenancy regression suite                                             | Exit 0: 24 tests, 166 assertions across the database manager, tenant schema/defaults, central administrator model, and disposable-resource cleanup. Includes exact bootstrap grantor validation and rejection of cleanup claims for pre-existing resources.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| 2026-10-10 | Intermediate PHPStan rerun (superseded by final verification)                   | An intermediate `vendor/bin/sail composer larastan` run exited 1 with 59 findings during implementation. Those findings were later resolved; the final `composer test:types` run below exits 0. No suppressions were added to hide them.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-10 | Independent PostgreSQL manager review                                           | Focused independent review of `PostgreSQLTenantDatabaseManager` and the disposable database helper found no actionable issues in bootstrap membership validation, ownership tracking, or cleanup.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| 2026-10-10 | Central company/tenant mapping                                                  | Exit 0: 1 isolated PostgreSQL feature test, 4 assertions; focused PHPStan and Pint both pass. The central Company and Tenant resolve through the same UUID; the registered domain is normalized to lowercase.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 2026-10-10 | Central company registry and tenant profile slice                               | Exit 0: 17 focused tests, 59 assertions across Company, CompanySetting, factory/seeder configuration, central mapping, and tenant schema. The central CRUD test used a guarded disposable PostgreSQL database. Initial tests exposed the expected legacy-schema required-profile columns; central schema coverage was moved to the disposable central database and the legacy fixture seeder retains test-schema compatibility.                                                                                                                                                                                                                                                                                                                                                                          |
| 2026-10-10 | Registry slice Pint and PHPStan                                                 | Pint passed on the touched PHP files. Targeted PHPStan over the registry models, factory, seeder, and relevant tests exits 0. The first targeted PHPStan pass found four typing/test-assertion issues; all were corrected before the clean rerun. The default sandbox reported Docker/Podman unavailable; approved elevated Sail execution succeeded.                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-10 | Independent central registry review and corrections                             | Read-only review found UUID mass-assignment/mutation risk, a central-to-tenant user relation, unguarded sample seeding, and missing settings-isolation proof. UUID assignment/update is now blocked, cross-connection user relations are removed, example seeding is local/testing-only, and a two-tenant PostgreSQL settings isolation test was added.                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-10 | Final registry/profile focused verification                                     | Exit 0: 34 tests, 134 assertions, including existing legacy company/user tests, central registry CRUD/UUID immutability, and CompanySetting writes isolated between two disposable tenant databases with no central settings table. Targeted PHPStan and Pint pass on changed registry paths.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 2026-10-10 | Step 3 completion suite                                                         | Exit 0: 41 tests, 216 assertions across central/tenant schemas, manager ACL/lifecycle, equal reference values, local order foreign keys and price snapshots, tenant settings isolation, and central mapping. Targeted PHPStan on 27 Step 3 files reports no errors; Pint passes. PostgreSQL databases/roles were created and cleaned only by the guarded test helper.                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-10 | Step 4 host and tenant-context regression                                       | Final host-routing rerun: 2 tests, 40 assertions pass. The combined host/model-context slice passed 3 tests, 44 assertions; the broader migration-path regression slice passed 74 tests, 390 assertions. Coverage includes two company databases, active/ready/missing tenants, separate central hosts and tenant parent domains, rejection of nested parent domains, order-form host boundary, public tracking/asset boundary, and context reset after exceptions.                                                                                                                                                                                                                                                                                                                                      |
| 2026-10-10 | Full PHP unit and feature suite                                                 | Final exit 0: 807 passed, 5,658 assertions. The first run had 8 failures because legacy tests referenced migration files at their old root paths; those references now target `database/migrations/legacy/`, and the final rerun passed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-10 | Full and targeted PHPStan                                                       | `vendor/bin/sail composer larastan` still exits 1 with the same 59 repository-baseline findings. Targeted analysis of 24 tenancy source paths reports no errors; `Order.php` remains part of the pre-existing findings and was not broadly refactored for this host-boundary step.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 2026-10-10 | Pint and legacy migration preservation                                          | Targeted Pint passes. The required `--dirty` run also touched moved legacy migrations; those formatting-only edits were restored and all 34 tracked moved migration files were compared byte-for-byte with their original tracked sources. `git diff --check` passes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-10 | Dusk after tenant model guards                                                  | `vendor/bin/sail artisan dusk` exited 2: 24 failed, 0 assertions. Test setup creates tenant-owned `User`/`Order` models without an initialized tenant, so the strict guard raises `TenancyNotInitializedException` before browser assertions. FrankenPHP remained configured; this is pending tenant-aware test fixtures/host setup in Steps 5 and 8, not evidence that the tenant browser journeys pass.                                                                                                                                                                                                                                                                                                                                                                                                |
| 2026-10-10 | Independent Step 4 review and correction                                        | Review found Stancl's subdomain parser accepts the first label, so nested configured parents could allow `acme.app.example.com` to resolve as `acme`. Application startup now rejects overlapping/nested tenant parent suffixes; the regression test and final full PHP suite pass. The supported split remains exact `central_hosts=app.example.com` plus parent `central_domains=example.com`.                                                                                                                                                                                                                                                                                                                                                                                                         |
| 2026-10-10 | Docker/Sail access for Step 4 checks                                            | Default sandbox again reported `Docker or Podman is not running`; approved elevated Sail access succeeded for the full PHP suite, targeted PHPStan, Pint, and Dusk. No host PHP or alternate web server was used.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| 2026-10-10 | Independent Step 5 security review and corrections                              | Review found guard state could leak between Octane requests, central logout/reset did not revoke active support grants, the grant token appeared in an HTTP query URL, and mobile-token issuance lacked tenant-aware throttling. The context now restores its captured guard, grants are revoked and audited, transfer uses a URL fragment immediately removed by the tenant page, and token issuance is rate-limited per tenant/email/IP. The legacy test-schema helper treats an absent `is_platform_support` column as false; an initial focused run had three fixture-compatibility failures, then the corrected suite passed.                                                                                                                                                                       |
| 2026-10-10 | Step 5 focused auth/order/handoff verification                                  | Exit 0: 17 tests, 337 assertions across auth/session guard cleanup, central handoff and revocation, token throttling, host boundaries, order-list scope, and audit attribution. Targeted PHPStan on changed application source reports no errors. The normal sandbox again could not see Docker/Podman; the user-approved elevated Sail run succeeded.                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-10 | Central-only tenant user administration decision                                | User confirmed that only Central Super Administrators may manage tenant users. `TenantUserAdministrationAuthorizationTest` proves tenant-local users, including a tenant Super Administrator, cannot list/create/view/update/delete or assign roles; the non-login support actor is authorized only inside its active tenant context.                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| 2026-10-10 | Prior full PHP unit/feature suite (superseded)                                  | Exit 0, 820 passed, 5,985 assertions after the valid bearer-token host-isolation regression. Superseded by the final run below, which includes cross-host order and attachment assertions.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 2026-10-10 | Prior FrankenPHP Dusk suite (superseded)                                        | Exit 0, 26 passed, 154 assertions with `OCTANE_SERVER=frankenphp`. Initial browser requests lacked Dusk Redis/Sanctum host settings; the test-only service now provides them. User declined switching Dusk to Laravel's normal server, so FrankenPHP was retained. A cosmetic `TTY mode requires /dev/tty` notice did not affect results.                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 2026-10-10 | Prior frontend, PHPStan, Pint, and production build (superseded)                | Earlier: Vitest 21 files/104 tests, PHPStan no errors, Pint passed, and Vite build succeeded with the existing `Show-*.js` 617.78 kB chunk-size warning.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-10 | Verification environment issues                                                 | Default sandbox access reported `Docker or Podman is not running`; approved elevated Sail access succeeded. An initial parallel PHPStan/Vitest/build run exhausted container resources (one Vitest timeout and build exit 137), then passed serially. The focused XSRF-header assertion first used the raw token instead of Laravel's encrypted cookie value and failed; after modeling the cookie prefix/encryption correctly, the focused feature and browser tests passed. After rebuilding, an initial full Dusk run had 8 failures because the long-lived Dusk worker served stale Vite manifest references (asset 404s); `vendor/bin/sail exec dusk php artisan octane:reload` refreshed only the test worker, and the focused journey plus final full suite passed. No Postman test was required. |
| 2026-10-10 | Tenant-host order and attachment boundary tests                                 | Final focused `TenantHostRoutingTest.php` plus `TenantOrderAttachmentHostIsolationTest.php`: 3 passed, 124 assertions. Alpha order list/show/history, authenticated and public attachment download/preview, and public tracking stay on Alpha; Beta sees no Alpha order, attachment, or tracking fallback. The two-tenant test uses disposable PostgreSQL databases and temporary private storage.                                                                                                                                                                                                                                                                                                                                                                                                       |
| 2026-10-10 | Two-company order-intake Dusk journey                                           | Final focused FrankenPHP Dusk test: 1 passed, 14 assertions. Both company hosts create separate orders with same-email staff accounts; each form offers only its own customer, and each order list excludes the other tenant's order. Returning to Alpha preserves its host-local session; Beta requires its own login and does not inherit the Alpha session.                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| 2026-10-10 | Dusk approval-button synchronization                                            | The first full-suite run and isolated rerun reproduced `ElementNotInteractableException` on the existing ready-for-delivery button. Added an enabled-state wait and scroll into view; the focused approval/delivery spec then passed (2 tests, 21 assertions), followed by a clean full FrankenPHP Dusk run. The FrankenPHP service remained unchanged.                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| 2026-10-10 | Prior full FrankenPHP Dusk suite (superseded)                                   | `vendor/bin/sail artisan dusk`: exit 0, 27 passed, 164 assertions. Superseded by the final run below after adding Beta-login/session assertions. FrankenPHP was retained. The only console notice was the cosmetic `TTY mode requires /dev/tty`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| 2026-10-10 | Prior full PHP unit/feature suite (superseded)                                  | `vendor/bin/sail artisan test --compact`: exit 0, 821 passed, 6,035 assertions. Superseded by the final run below after adding the last cross-host history assertions.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| 2026-10-10 | Prior static analysis, Pint, and Markdown formatting (superseded)               | Previous PHPStan run exited 0 across 170 paths and prior Sail Pint/Prettier checks passed. PhpStorm inspection tool was unavailable.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| 2026-10-10 | Tenant cache and private-filesystem isolation regression                        | Focused `TenantWorkerContextTest`: 1 passed, 18 assertions using Redis and isolated temporary local storage. Identical cache keys/lock names and file paths remain isolated across two PostgreSQL tenant databases; queue A/B switching still clears context after a failure.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| 2026-10-10 | Valid bearer-token host isolation review                                        | Independent review found the prior suite had only invalid token issuance. Added a valid Alpha token test: it authenticates on Alpha and receives 401 on Beta. Focused auth test: 1 passed, 72 assertions. Independent re-review returned no issues; the final full suite passed afterward.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| 2026-10-10 | Independent tenant-boundary test review                                         | Review found two gaps: Beta's browser journey did not require a fresh login, and order history lacked a positive Alpha-host assertion. Both are now covered; reviewer re-read the changes and reported no further issues.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| 2026-10-10 | Final full FrankenPHP Dusk suite                                                | `vendor/bin/sail artisan dusk`: exit 0, 27 passed, 168 assertions. FrankenPHP was retained. The only console notice was the cosmetic `TTY mode requires /dev/tty`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| 2026-10-10 | Independent CSRF review and correction                                          | Reviewer found that excluding `api/*` bypassed CSRF validation for Sanctum stateful, cookie-authenticated API mutations. Removed the broad exception and updated the order API fetch helper to send the decoded XSRF cookie in `X-XSRF-TOKEN`. Focused tenant auth regression: 1 passed, 74 assertions; frontend helper tests: 2 passed; two-company Dusk journey and the full browser suite pass with FrankenPHP.                                                                                                                                                                                                                                                                                                                                                                                       |
| 2026-10-10 | Final full PHP unit/feature suite                                               | `vendor/bin/sail artisan test --compact`: exit 0, 821 passed, 6,035 assertions.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| 2026-10-10 | Final frontend, static analysis, Pint, and production build                     | `vendor/bin/sail yarn test:unit`: 22 files/106 tests passed. `vendor/bin/sail composer test:types`: PHPStan exits 0 across 170 paths. Required Sail Pint and Prettier checks passed. Production build passed with the existing `Show-*.js` 617.78 kB chunk-size warning.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| 2026-10-10 | Sanitized default-database ownership audit                                      | Read-only Boost queries against configured PostgreSQL database `laravel` returned aggregate counts only: 7 companies (6 active), 237 users (231 unmapped), 59 orders (all with unmapped participants), 19 attachment references (18 orders/1 history, no orphans), and 74 catalog rows. Duplicate identity/UUID groups and missing references counted zero. No PII, credentials, or paths were retrieved.                                                                                                                                                                                                                                                                                                                                                                                                |

Current focused baseline command:

```sh
vendor/bin/sail artisan test --compact tests/Unit/app/Models/CompanyTest.php tests/Feature/app/Models/UserCompanyTest.php tests/Feature/app/Policies/UserPolicyTest.php tests/Feature/app/Policies/OrderPolicyTest.php tests/Feature/app/Http/Controllers/OrderCreateParticipantScopingTest.php
```

The latest full PHP unit/feature run passes 821 tests and 6,035 assertions; the latest FrankenPHP Dusk run passes 27
tests and 168 assertions. Frontend tests pass (22 files, 106 tests), and the configured `composer test:types` PHPStan
run has no errors. The prior 59-finding PHPStan run, earlier Dusk fixture failures, and the intermediate Dusk button
failure are historical and superseded by later successful runs. Production build and Pint pass; Vite reports an
existing 617.78 kB `Show-*.js` chunk warning. PhpStorm inspections were unavailable in this session. No Postman failure
was observed; Postman is not required for these tests.

Steps 5 and 6 are complete. Step 2 now proves the order list/show/history and attachment/tracking host boundaries, but
the complete child/refund, notification-event, catalog, and central/unknown-job matrix remains open. Step 7 proves
queue A/B/failure cleanup, Redis cache/lock isolation, local private-file isolation, and cross-host authenticated/public
attachment access; provider credentials, feature invalidation, central/missing/scheduled jobs, and full Octane cleanup
remain open. Step 8 proves tenant-host order creation and list isolation on both companies, but the full lifecycle and
browser-based company onboarding journey remain open. Step 9 has a sanitized audit and fresh two-company provisioning
rehearsal; database upgrade, failed-rollout recovery, and backup/restore rehearsals remain open. Step 10 awaits verified
hosting/DNS/TLS, recovery, and compliance inputs. No production deployment or destructive data operation was
performed or authorized.

For sandbox Docker/socket failures, request execution permission and retry the same Sail command; do not switch to
host PHP/Node. Run database suites serially unless per-process databases are isolated. For browser failures, distinguish
DNS/Selenium, unavailable Vite assets, and application assertions; build assets through Sail when appropriate.

## Source references and next-session instructions

- [Business rules](Business_Rules.md).
- [Earlier participant-scoping plan](ORDER_CREATE_PARTICIPANT_SCOPING_PLAN.md).
- [Stancl stable release and maintenance evidence](https://github.com/archtechx/tenancy/releases/tag/v3.10.1).
- [Stable release dependency constraints](https://raw.githubusercontent.com/archtechx/tenancy/v3.10.1/composer.json).
- [Stancl database managers and migrations](https://tenancyforlaravel.com/docs/v3/multi-database-tenancy/).
- [Stancl tenant identification](https://tenancyforlaravel.com/docs/v3/tenant-identification/).
- [Spatie alternative and application-owned provisioning](https://spatie.be/docs/laravel-multitenancy/v4/installation/using-multiple-databases).
- [Laravel 13 database connections](https://laravel.com/docs/13.x/database).
- [Laravel 13 Octane state guidance](https://laravel.com/docs/13.x/octane).
- [Official FrankenPHP releases and PHP 8.5 binary support](https://github.com/php/frankenphp/releases/).

Before executing: read this plan and current repository instructions, refresh installed versions and matching rules,
preserve unrelated changes, and start at the first incomplete authorized step. Capture new evidence here; do not
relabel pending implementation as complete based on documentation or package marketing. No tenancy package handles
all domain permissions, infrastructure isolation, or compliance requirements automatically.
