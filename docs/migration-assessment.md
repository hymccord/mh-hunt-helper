# PHP framework migration assessment and proposed roadmap

Date: 2026-11-01. Status: proposal for review; no application changes authorized or made.

## Recommendation

Migrate incrementally to Laravel 13 as a single application, retaining the existing MySQL schemas and public contracts. Use controllers, request validation, explicit application services, named database connections, Blade templates, and console commands. Keep SQL-heavy reporting in focused query classes using the query builder or bound SQL. Do not require an Eloquent conversion of every table.

Laravel is my recommendation because this application needs conventional routing, validation, configuration, database access, views, HTTP integrations, and operational tooling together. Symfony 7.4 LTS is a credible alternative if the team values its longer support cycle or already knows Symfony. Adding only a small router would reduce initial churn but leave more validation, configuration, and operational conventions to assemble and maintain.

As verified against official release pages, Laravel 13 supports PHP 8.3–8.5 and receives security fixes until March 17, 2028. Symfony 7.4 requires PHP 8.2+ and receives security fixes until November 2029. The repository's development Dockerfile already defaults to PHP 8.5; production PHP, MySQL, extensions, and hosting capabilities remain unverified.

Sources: [Laravel release policy](https://laravel.com/docs/13.x/releases), [Symfony 7.4 lifecycle](https://symfony.com/releases/7.4).

## What exists today

- 51 PHP files under `public/`, including public entry points, implementation includes, configuration, and templates.
- Composer dependencies are Predis and Ramsey UUID, plus PHP CS Fixer for development. No framework, project autoload mapping, or automated test suite was found in the inspected tracked project files.
- `public/intake.php` orchestrates ban/origin/version/UUID/user/time checks, Relic Hunter handling, hunt timing, payload fixes, hunt writes, giveaway forwarding, and the final response by executing includes in order.
- `public/hunt-intake.php` mixes field validation, catalog creation, duplicate detection, hunt persistence, stages, loot, location-specific processing, generic details, and auras in roughly 377 lines.
- There are three logical database connections: hunt helper, map spotter, and converter. Some SQL explicitly names schemas such as `mhhunthelper`, `mhmapspotter`, and `mhconverter`.
- Read endpoints include `searchByItem.php`, `searchByMouse.php`, `searchByDrop.php`, and `filters.php`. Browser scripts depend on existing file URLs and response shapes. `searchByUser.php` mixes reads, user resolution, and HTML.
- Reporting reads aggregate tables such as `attractions`, `drops`, time-filter variants, and `aggr_convertible_item`. Their complete generation process and database definitions are not present here.
- Relic Hunter, errors, and rejections also use JSON files. Root scripts perform maintenance; `reset_tracker.php` still references a `src/` path. Development documentation also contains old `src/` references.
- Existing uncommitted changes were present during analysis, including ingestion, UUID, time checks, and dependency changes. This assessment describes the working tree, not just HEAD.

## Why the code is difficult to change

| Evidence | Consequence | Proposed boundary |
| --- | --- | --- |
| `intake.php` executes a chain of includes | Ordering and early exits determine behavior implicitly | Controller orchestrates explicit validation and use cases |
| Globals such as `$pdo`, `$user_id`, `$hunt_id`, `$processed_details`, and `$_POST` flow between files | Dependencies and mutations are difficult to test independently | Typed input objects and constructor/method dependencies |
| `filters.php` is both an include and a JSON endpoint | Including a dependency can emit headers or output | Filter query service plus separate controller |
| `uuid.php` issues or consumes tokens depending on a constant | One script has two contracts and hidden control flow | UUID controller and token service |
| `send_response.php` normally masks status/message as success; other paths simply `die()` | HTTP success cannot establish whether data was accepted | Internal acceptance outcomes plus explicit legacy response mapping |
| Hunt duplicate lookup and catalog writes precede `beginTransaction()` | Concurrent duplicates and partial side effects are possible; actual constraints require inspection | Audited unique keys, atomic writes, defined transaction ownership |
| Hunt transaction catch logs and rolls back but does not propagate failure | `intake.php` can continue to giveaway forwarding and success | Explicit failure result; integrations only after successful persistence |
| Map and convertible record groups lack an enclosing transaction in the inspected files | Mid-request failure can leave incomplete groups | Transaction on each owning connection |
| JSON read/modify/write storage | Concurrent updates can overwrite each other | Database-backed state or locked/atomic file adapter during transition |
| `giveaway-intake.php` makes synchronous `file_get_contents()` calls | Integration latency/failure affects intake; retries are unspecified | HTTP adapter with timeout; durable post-commit delivery if required |

These are code-level risks, not claims that production data is already corrupted. Existing schema constraints could mitigate some concurrency risks.

## Target structure

```text
app/
  Http/Controllers/       # Small intake, search, page, and UUID controllers
  Http/Requests/          # Endpoint-specific validation
  Http/Middleware/        # Origin, version, ban, and legacy protocol handling
  Hunts/                 # HuntInput, RecordHunt, HuntWriter, timing policies
  Hunts/Details/         # Location handler registry; Claw Shot City handler
  Maps/                  # RecordMap and map queries
  Convertibles/          # RecordConvertible and converter queries
  RelicHunter/           # Hint confirmation, aliases, tracker persistence
  Reporting/             # Attraction/drop/filter queries and result shaping
  SubmissionTokens/      # Token issuance and atomic consumption
  Integrations/          # Giveaway HTTP delivery
  Console/Commands/      # Maintenance operations
resources/views/         # Blade layouts and existing page markup
routes/                  # Explicit legacy-compatible routes
tests/                   # Contract, MySQL integration, and policy tests
```

Use concrete services by default; introduce interfaces where an external dependency or genuinely interchangeable implementation warrants one. Controllers translate HTTP input/output; services own business operations; query classes own reporting SQL. Avoid moving the include chain into one large controller or generic service.

## Compatibility requirements

1. Preserve `.php` endpoint paths, actual accepted methods, nested form encoding, query parameters, numeric/string representations, array shapes, empty results, headers, and OPTIONS behavior until clients can migrate.
2. Record each endpoint's own guard sequence. Do not blindly apply every hunt guard to maps, convertibles, or Relic Hunter hints.
3. Preserve legacy outward responses through an adapter while logging truthful internal outcomes: accepted, duplicate, rejected, failed. Any new public error/status policy needs a deliberate client rollout.
4. Test string booleans, missing versus zero values, nullable charms, scalar versus array stages/auras, unrecognized details, catalog names, and location handlers.
5. Preserve current timing behavior as an initial contract: normal timestamp age within 30 seconds; linked hunts from 30 seconds in the future through 3600 seconds old; current 899-second latest-hunt rule and charm exception. Review these policies separately after migration.
6. Inspect UUID behavior with and without a supplied token, including the current early return of `1` when absent. A stricter token requirement, TTL, or changed consumption order is a behavior change, not incidental framework setup.
7. Keep current aggregate tables, time-filter allowlists, rounding, ordering, cache headers, database collations, and UTC-day semantics. Do not replace analytical SQL with ORM relationship loops.
8. Hunter hashes identify submissions; CORS and hashes are not an authenticated user system. Do not introduce sessions/login or broad CSRF exceptions for unrelated routes. Put extension intake on deliberately stateless routes.

## Phased migration

### 1. Establish a trustworthy baseline

- Inventory entry points versus internal includes, route methods, consumers, writable files, production runtime, Redis availability, integrations, cron jobs, and deployment routing.
- Obtain schema-only exports for all three databases, including indexes, foreign keys, triggers, views, routines, and scheduled events; locate aggregate-generation jobs. Do not download full public backups merely to build a development environment.
- Build a minimal sanitized MySQL fixture database. Capture representative request/response fixtures and database effects for valid, rejected, duplicate, malformed, and early-exit submissions.
- Add a test harness around the legacy application, with time and external services controlled. Use MySQL for SQL integration tests because the application uses MySQL-specific syntax and semantics.
- Establish request latency, database query cost, accepted/rejected/failed counts, and aggregate freshness baselines. Sanitise logged payloads and avoid introducing raw hunter-hash logging by default.

Exit: reproducible local setup, explicit contracts, and a documented list of unknown production dependencies.

### 2. Introduce the framework and migrate one read endpoint

- Create Laravel alongside the legacy application with separate bootstraps. Configure environment-backed secrets, named `hunts`, `maps`, and `convertibles` connections, logging, and health checks.
- Route a small explicit allowlist at the web server/reverse proxy to Laravel. Legacy URLs continue to hit the legacy runtime; no catch-all execution of arbitrary PHP paths.
- Move a low-risk read endpoint such as `filters.php` first. Compare headers, response shapes, database effects, and latency against the baseline.
- Add CI for tests, formatting, and static analysis of migrated code. Verify CLI and FPM PHP versions and extensions.

Exit: one real endpoint served by Laravel, with a verified route-level rollback. An unmatched route must not silently fall through into arbitrary legacy files.

### 3. Move reporting and server-rendered pages

- Migrate `searchByItem.php` by query family, then mouse/drop searches and reporting pages. Give each database connection explicit ownership; audit fully qualified schema names before changing SQL.
- Preserve the time-filter allowlist when constructing table identifiers. Bind values; never accept arbitrary SQL identifiers from requests.
- Move header/footer/page markup to Blade layouts while retaining existing JavaScript and CSS. Preserve intentionally rendered HTML and escape dynamic values appropriately.
- Separate user lookup from user creation. Characterize `searchByUser.php`'s current creation side effect before deciding whether to remove it.

Exit: read contracts and representative query plans match; browser flows work without a frontend rewrite.

### 4. Migrate complete submission flows one at a time

- Start with maps or convertibles after fixtures establish their contracts. Introduce validated inputs and explicit record operations with a transaction on the owning database.
- Keep user creation in the hunts database explicit. A Laravel transaction on one connection cannot make changes to the other databases atomic; define retry/recovery expectations for partial cross-database progress.
- Audit existing duplicate constraints and historical duplicates before adding keys. Select idempotency keys from actual client guarantees; the convertible code explicitly lacks a duplicate check, so do not invent a key from assumptions about asset hashes.
- Move error/rejection and Relic Hunter flows with endpoint-specific guards. Test hint confirmation by distinct hunter within the 24-hour window, five-confirmation threshold, UTC location lookup, and location aliases.
- Keep JSON consumers working through an adapter or generated compatibility snapshot while changing the storage implementation.

Exit: no partial record groups on injected database failures; duplicates and retries have defined outcomes; existing clients pass contract tests.

### 5. Migrate hunt ingestion

- Normalize legacy input once into `HuntInput`; make payload fixes explicit normalization steps.
- Split timing/eligibility checks, catalog resolution, hunt writing, location handlers, and response mapping. Preserve the Relic Hunter short-circuit before normal hunt processing.
- Audit and enforce the hunt identity currently checked as `(user_id, entry_id, timestamp)`. Handle concurrent insertion using verified database constraints, not only a preflight SELECT. Deduplicate existing records and validate index cost before production DDL.
- Define transaction scope for hunt, stage, loot, detail, pillage, aura, and location rows. Decide explicitly which catalog/user creations may survive rejected submissions.
- Use explicit acceptance results so rollback cannot trigger giveaway delivery. For reliable asynchronous delivery, persist an outbox entry in the hunt transaction and deliver with bounded retries and an idempotency strategy agreed with the receiving service.
- Move token behavior into its own service. Atomic consume and expiring keys are desirable follow-ups, but introduce them with compatibility fixtures and an intentional rollout. Use a separate key namespace and avoid conflicting with the existing Redis cleanup script.

Exit: valid and rejected fixtures match the intended contract; concurrent duplicate submissions produce one logical hunt; injected child-write failures roll back; failed/duplicate hunts do not trigger giveaway delivery.

### 6. Finish operations and retire legacy execution

- Convert cleanup, tracker reset, and import scripts into commands; document schedules, locking, dry-run options where relevant, and retry behavior. Retire broad Redis `KEYS *` cleanup only after all old token writers are gone.
- Establish a schema baseline for existing installations and a separate fresh-install fixture path. New migrations must not recreate populated production tables.
- Move secrets and implementation files outside the served directory. Leave only the framework front controller and intended static assets public.
- Verify backup/restore, queue supervision if used, shared-state behavior across instances, aggregate jobs, and deployment cache/config management.
- Remove legacy files only after traffic and client inventory establish that every active route has migrated. Update CONTRIBUTING and deployment docs.

Exit: no executable include chain or global connection state remains; every active route has an owner, a testable contract, and operational documentation.

## Rollout and rollback

- Deploy by endpoint with explicit ownership. Keep exactly one live writer per routed request; do not mirror POST requests into a second production writer.
- Compare read responses through replay or safe shadow reads. Replay submissions only against isolated fixture databases and stubbed integrations.
- Canary ingestion by a controlled cohort, with stable routing for related UUID and submission calls where shared token state requires it.
- Monitor internal acceptance outcomes and persisted row counts as well as HTTP responses; legacy responses often look successful on rejection.
- Keep database changes additive and usable by both runtimes during transition. Rehearse route rollback before canarying; reverting application routing will not undo persisted writes, queued deliveries, or incompatible schema changes.
- Choose numerical stop thresholds from the measured baseline before rollout. Roll back on unexplained acceptance changes, duplicates, partial writes, excessive latency, or aggregate freshness regressions.

## Scope and open decisions

This is a proposed migration roadmap, not an implementation-ready ticket list. Framework selection, runtime compatibility, database definitions, actual client payloads, production volume, deployment control, integration reliability requirements, and allowed maintenance windows need confirmation before final task sizing.

Keep the work in small, single-purpose PRs as requested by CONTRIBUTING.md. The first deliverable should be contract fixtures plus a schema inventory, followed by one Laravel-served read endpoint. Avoid combining the migration with schema redesign, a SPA rewrite, microservices, or new authentication.

Success means contributors can change a hunt rule or add a location handler without editing unrelated reporting, rendering, and transport code; submission integrity is verified; existing clients continue working; and deployment has a tested rollback path.
