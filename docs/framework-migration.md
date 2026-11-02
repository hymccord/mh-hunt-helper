# Migration plan: mh-hunt-helper to Laravel

## What's there now

There are about 3,700 lines of PHP in roughly 45 entry-point files under `public/`, plus about 1,000 lines of jQuery. The files fall into four groups:

| Group | Files | Pattern |
|---|---|---|
| **Intake APIs** (used by the extension) | `intake.php`, `uuid.php`, `map_intake.php`, `convertible_intake.php`, `rejection_intake.php`, `error_intake.php`, `rh_intake.php` | A chain of `require_once` calls that act as middleware |
| **Public JSON APIs** | `searchByItem.php`, `filters.php` | A `switch` on `item_type`, with the database connection picked per branch |
| **HTML pages** | `index`, `attractions`, `loot`, `mapper`, `maphelper`, `scavhelper`, `rh-tracker`, `searchByUser`, `faq`, … | `$title`/`$js` globals, then `require common-header.php`, then markup mixed with SQL |
| **Cron/CLI** | `cleanup-redis.php`, `reset_tracker.php`, `insert_gold_values.php` | Standalone scripts |

### What makes it spaghetti

1. **Control flow runs through `require_once`.** The order of includes in `intake.php` is the request pipeline. Files share state through globals (`$pdo`, `$user_id`, `$hunt_id`, `$processed_details`, `$_POST`) and exit with `die()` or `sendResponse()` from deep inside includes. `rh_intake.php`, `uuid.php` and `filters.php` each act as both an include and an entry point.
2. **Config is global variables.** `db-connect.php` uses `global` to build three PDO connections. `searchByItem.php` overwrites `$pdo` with a different database.
3. **Validation is copied by hand.** `hunt-intake.php:11-82` repeats the same block 13 times, and the `caught` check logs the wrong message ("Location name is missing").
4. **Request input gets mutated.** `temp-fixes.php` and `hunt-intake.php` rewrite `$_POST['stage']` and `$_POST['auras']` in place.
5. **Writes aren't atomic.**
   - In `hunt-intake.php`, the duplicate check and all the get-or-create catalog writes (locations, traps, mice…) run before `beginTransaction()`. Two concurrent submissions can both pass the duplicate check.
   - `map_intake.php` and `convertible_intake.php` have no transaction, so a failure mid-request leaves a partial record group.
   - The three databases are separate connections, so no single transaction can cover all of them.
6. **Errors are swallowed.**
   - A failed hunt transaction is logged and rolled back, but `intake.php` carries on to the giveaway call and returns success.
   - `sendResponse` always returns "success" because `$dev_mode = false` is hard-coded, and many other paths just `die()` with an empty body.
   - Nobody can tell from the outside whether a submission was stored.
7. **State lives in JSON files inside `public/`.** `tracker.json`, `rejections.json` and `errors.json` are read, modified and rewritten with no locking. `reset_tracker.php` points at `src/tracker.json`, which no longer exists.
8. **HTML output isn't escaped.**
   - `attractions.php` and `loot.php` echo `$_GET['mouse']` and `$_GET['item']` straight into attributes.
   - `searchByUser.php` and `helper-results-body.php` print names that users submitted.
   - Together that's both reflected and stored XSS.
9. **A GET request writes to the database.** `searchByUser.php` includes `check-userid.php`, which inserts a user row for any hash you look up.
10. **Every file in `public/` can be requested directly.** Include files like `hunt-intake.php` and `db-connect.php` are protected only by the `not_direct_access` constant.
11. **There are no tests, CI, autoloading or namespaces.** Composer is only used for predis and uuid.

### Checks each intake endpoint runs today

Each endpoint has its own set of checks, in its own order. They must not be collapsed into one shared middleware group. This table reflects `HEAD`:

| Endpoint | Checks, in order | Then |
|---|---|---|
| `intake.php` | Ban → CORS → Version → UUID → User → Time | RH short-circuit → 15-minute rule → temp fixes → hunt write → giveaway |
| `map_intake.php` | Ban → CORS → Version → User | Map write (map spotter DB) |
| `convertible_intake.php` | Ban → CORS → Version → User | Convertible write (converter DB) |
| `rejection_intake.php` | CORS → UUID → Ban → Time → Version | JSON file + `rejections` table |
| `error_intake.php` | CORS → UUID → Ban → Time → Version | JSON file only |
| `rh_intake.php` (standalone, `hint` posted) | CORS → UUID → Ban → Time | Hint confirmation → RH tracker |
| `uuid.php` (no `uuid` param) | CORS | Issue token in Redis |

Map and convertible submissions don't send a UUID or a timestamp, so applying the hunt checks to them would reject every one.

## Constraints

- **The URL contract is frozen.** The extension and third parties (markethunt, tsitu's tools) call `*.php` paths directly with form-encoded `$_POST` arrays. Every existing URL, method, parameter shape, empty result, header and OPTIONS response has to keep working.
- **The response quirks are part of the contract.** Many paths return an empty body via `die()`, and errors are masked as success. Each one needs to be preserved deliberately or changed deliberately, alongside an extension release.
- **The database schema is owned elsewhere** (mhct-db-docker and the aggregation jobs). The app reads and writes three databases and uses cross-schema names like `mhhunthelper.stages`. There will be no migrations and no ORM-driven schema.
- **Hunter hashes aren't authentication.** There are no sessions or logins, and this migration shouldn't add any.
- **Intake gets a lot of traffic**, so per-request overhead matters.
- **CONTRIBUTING says to keep PRs small**, so the migration has to be incremental.
- **Timing rules carry over unchanged at first.** The rules are:
  - A normal hunt's timestamp must be within 30 seconds of the server's clock.
  - A linked hunt can be up to 30 seconds in the future or up to 3600 seconds old.
  - The 899-second gap between hunts is enforced, except when the last hunt used charm 2584.

  Review these rules separately, after the migration.

## Recommendation: Laravel, migrated incrementally (strangler fig)

**Why Laravel:** it covers this app's problems with built-in features:

- Form Requests replace the copy-pasted validation.
- Middleware replaces the require chain.
- `config/database.php` handles the three named connections.
- Redis support with TTLs makes `cleanup-redis.php` unnecessary.
- Blade escapes output by default, which fixes the XSS.
- The scheduler replaces the cron scripts.
- HTTP tests provide the safety net.

It also has the largest pool of volunteer contributors. Use the query builder or raw SQL rather than Eloquent models, since the schema isn't ours. Don't rewrite the analytical SQL as ORM relationship loops.

Slim 4 is the lighter alternative, but you'd assemble DI, validation, templating and testing yourself, and that's how you end up with spaghetti again.

**Why incremental:** Laravel's `public/index.php` runs only when the requested file doesn't exist. So legacy `.php` files keep being served until you delete one and add a route with the same path, for example `Route::post('/intake.php', …)`. Each endpoint can move in its own PR. Rolling back an endpoint means restoring its file.

### Target layout

```text
app/
  Http/Controllers/       # Thin controllers: translate HTTP in and out
  Http/Requests/          # One Form Request per endpoint
  Http/Middleware/        # Ban, IntakeCors, PublicCors, ExtensionVersion, ConsumeUuid, ResolveUser, TimeWindow
  Intake/                 # IntakeOutcome enum, LegacyResponder, LookupTable
  Hunts/                  # HuntInput, RecordHunt, HuntRateLimit, SubmissionFilters
  Hunts/Details/          # LocationDetailHandler registry, ClawShotCityHandler
  Maps/                   # RecordMap, map queries
  Convertibles/           # RecordConvertible, converter queries
  RelicHunter/            # Hint confirmation, location aliases, tracker state
  Reporting/              # Attraction, drop and filter queries
  SubmissionTokens/       # UUID issue and consume
  Integrations/           # GiveawayNotifier
  Console/Commands/       # Maintenance commands
resources/views/          # Blade layout and pages
routes/                   # Legacy-compatible routes
tests/                    # Contract and MySQL integration tests
```

Use concrete classes by default. Add an interface only where there is a real second implementation or an external service to fake. Don't move the include chain into one big controller.

### Shared pieces

- **`IntakeOutcome`**: every submission flow returns `Accepted`, `Duplicate`, `Rejected` or `Failed`, and that outcome is logged. A `LegacyResponder` then maps it to today's response (masked success or empty body). This gives honest metrics without changing what the extension sees. Changing the outward response later is a deliberate step that ships with an extension release.
- **`LookupTable`**: replaces the seven copies of "select id, insert if missing" (locations, traps, mice, stages, detail types and values, auras), with a per-request cache. Use `INSERT … ON DUPLICATE KEY` only where the schema export confirms a unique key.
- **Stateless intake routes**: register intake endpoints without the `web` middleware group. That group checks CSRF tokens, which the extension doesn't send, so every POST would fail with a 419 error. Don't add broad CSRF exceptions to `web` instead.
- **Redis isolation**: give Laravel its own Redis key prefix and database index. `cleanup-redis.php` runs `KEYS *` and deletes any key whose value isn't a timestamp from the last 80 seconds, so it would wipe Laravel's cache, queue and lock keys if they shared a keyspace.

## Phases

Each phase ends with an **exit** condition that must hold before the next one starts.

### Phase 0: Safety net (no framework yet)

1. Keep the local dev shortcuts out of commits (see [Local dev shortcuts](#local-dev-shortcuts)).
2. Run php-cs-fixer once across the whole repo as a separate PR, so later diffs stay readable.
3. Get **schema-only exports** of all three databases: tables, indexes, unique keys, foreign keys, triggers and views. Find where the aggregate tables (`attractions`, `drops`, `aggr_*`) are generated. The transaction and duplicate-handling work in later phases depends on knowing which unique keys actually exist.
4. Build a small **sanitized fixture database** from those exports for tests and CI, instead of using the full mhct-db image.
5. Add **black-box characterization tests**: Pest tests that send real extension payloads to the running Docker stack and assert on database rows and response bodies.
   - Cover the happy path and each `die()` and `sendResponse` exit for every endpoint.
   - Cover the input variations: string booleans (`"true"`/`"false"`), missing vs. zero values, no charm, `stage`/`auras` as a string vs. an array, unrecognized hunt details, and location handlers.
6. Add GitHub Actions: lint, cs-fixer, and the tests against the fixture database.

**Exit:** local setup is reproducible, every endpoint has contract tests, and the open questions below are answered or written down as unknown.

### Phase 1: Scaffold alongside the legacy code

- Install Laravel at the repo root (`app/`, `routes/`, `config/`) and keep `public/` as the document root.
- Move settings into `.env`, and have the legacy `config.php` read from `.env` too, so both share one source of config.
- Define three DB connections (`hunts`, `maps`, `convertibles`), plus Redis with its own prefix and database index.
- Set up the stateless route file for intake endpoints.
- Configure the fallback in nginx and Apache: serve the file if it exists, otherwise send the request to Laravel. Check the `try_files` behaviour in the serversideup nginx image.
- Move the legacy home page `index.php` into a `/` route, since Laravel needs that filename.

**Exit:** the home page is served by Laravel, every other URL still hits its legacy file, and CI runs both.

### Phase 2: Read-only JSON APIs (low risk)

- **`filters.php`:** a `FilterRepository` plus a `PublicCors` middleware. Split out the include behaviour so that loading filters never emits headers.
- **`searchByItem.php`:** keep the dispatcher URL, but have it delegate to one query class per `item_type` (Mouse, Loot, Map, MouseMaps, Convertible, ItemConvertibles), each on its own connection.
  - The time filter stays a whitelist from `FilterRepository`. Table names are never built from raw request input.
  - Keep the current rounding, ordering and `Cache-Control` headers.
  - Check the fully qualified schema names (`mhmapspotter.`, `mhconverter.`) before changing any SQL.

**Exit:** responses match the legacy output byte for byte in the contract tests.

### Phase 3: HTML pages

- `common-header.php` and `common-footer.php` become a Blade layout. Keep the existing JavaScript and CSS; this isn't a frontend rewrite.
- The page logic in `searchByMouse.php` and `searchByDrop.php` moves into `Reporting/` services, and the views use Blade's auto-escaping.
- `searchByUser.php` looks users up without creating them. First check whether anything relies on the creation side effect.
- Keep the `*.php` URLs as routes. Clean URLs can be added later with redirects.

**Exit:** every page works in the browser, and the XSS cases from "What makes it spaghetti" are covered by tests.

### Phase 4: Small submission flows

Move these before hunt intake so the patterns are proven on simpler code. Use one PR per endpoint, and give each one exactly the checks listed for it in the table above.

- **`map_intake.php` and `convertible_intake.php`:**
  - Use a Form Request and a `RecordMap` or `RecordConvertible` operation inside a transaction on the owning connection.
  - The user row lives in the hunts database, so it can't be in the same transaction. Accept that a user row may survive a failed map write.
  - Convertible intake has no duplicate check today. Don't invent one from guesses about `asset_package_hash`.
- **`rejection_intake.php` and `error_intake.php`:** move the JSON-file state into a DB table or Redis. If anything outside reads the `.json` files, keep a route at the old path that serves the same shape.
- **Standalone `rh_intake.php`:** test hint confirmation (5 distinct hunters within 24 hours), the UTC-day location lookup, and the location aliases. `tracker.json` moves the same way as the other JSON files.

**Exit:** a database failure injected partway through a request leaves no partial record group, and each endpoint logs its `IntakeOutcome`.

### Phase 5: Hunt intake (the core work)

Map the `intake.php` checks to middleware, in the same order as today:

```
Ban → IntakeCors → ExtensionVersion → ConsumeUuid → ResolveUser → TimeWindow
```

Then:

- **`HuntSubmissionRequest`** validates the input and normalizes it once into a `HuntInput` object. Rewrite the temp-fixes as explicit normalization steps in `SubmissionFilters`, not as edits to `$_POST`.
- **Order inside the controller:** check the Relic Hunter short-circuit (`rh_environment`) first, then `HuntRateLimit` (the 899-second rule), then filters, then `RecordHunt`.
- **`RecordHunt`:**
  - Runs the duplicate check, the hunt insert, and the stage, loot, pillage, detail, aura and location-handler rows in one transaction.
  - Handles concurrent duplicates with a unique key on `(user_id, entry_id, timestamp)`, not just the SELECT before the insert. Before adding that key in production, check for existing duplicates and measure the index cost.
  - Decide explicitly whether catalog rows (new traps, mice…) should survive a rejected hunt.
- **A `LocationDetailHandler` registry** keyed by location ID replaces `locations/*.php`.
- **`GiveawayNotifier`** runs only after a successful commit (`DB::afterCommit`), using the HTTP client with a timeout and URL-encoded parameters. A failed or duplicate hunt never triggers it.
- **`uuid.php`** moves to a `SubmissionTokens` service with the same lifetime and consumption order as today. Switching to `SET … EX` and an atomic get-and-delete comes as a separate follow-up PR with its own tests.

**Exit:** valid and rejected fixtures match the contract, two concurrent copies of the same hunt produce one row, a failure injected into a child-row insert rolls back the whole hunt, and failed hunts never reach the giveaway.

### Phase 6: CLI scripts and cleanup

- `reset_tracker.php` and `insert_gold_values.php` become Artisan commands, registered in the scheduler instead of system cron. Fix the stale `src/` path along the way.
- Delete `cleanup-redis.php` only after every UUID writer uses expiring keys.
- Delete the legacy files, `check-direct-access.php` and the `not_direct_access` constant. After this, `public/` holds only Laravel's front controller and static assets.
- Add PHPStan and raise its level over time.
- Update CONTRIBUTING (it still references `src/`) and the deployment docs.
- Updating the frontend (Bootstrap 3, jQuery 3.5, DataTables 1.10) is a separate project. Don't bundle it with this one, and don't bundle schema redesign or new authentication either.

**Exit:** no include chain or global connection state remains, and every route has an owner and a contract test.

Expect roughly 25–35 small PRs. Phases 4 and 5 carry most of the risk.

## Open questions

1. **How is production hosted?** The `HTID` line in `.htaccess` suggests managed or shared Apache. Can we run `composer install` and `php artisan` there, and add a scheduler cron? Which PHP version and extensions are installed?
2. **Who reads `tracker.json`, `rejections.json`, `errors.json` and `searchByItem.php` from outside?** Any external reader adds URLs that must keep working.
3. **Does the extension depend on the empty-body `die()` responses or the masked errors?** This decides whether the responder keeps that behaviour or fixes it.
4. **What's in the production `check-ban.php`?** It's gitignored, so its contents aren't visible from the repo.
5. **Which unique keys exist today?** In particular, is there one on `hunts (user_id, entry_id, timestamp)`? Answered by the Phase 0 schema export.
6. **Does the giveaway service handle duplicate notifications?** If not, a retry after a timeout could count a hunt twice.

## Local dev shortcuts

Shortcuts that make local testing easier must never be committed. Examples seen in a working tree:

- **`uuid.php`:** returning `1` early when `uuid` is empty, which skips UUID validation for every intake, and sending `Access-Control-Allow-Origin: *`.
- **`rejection_intake.php`:** commenting out the version check and lowering the file limit from 250 to 5.

These are not part of the contract. Contract tests and fixtures must be captured from `HEAD`, not from a working tree with shortcuts applied. Phase 1 replaces these with an `.env` flag, so local dev no longer needs code edits.
