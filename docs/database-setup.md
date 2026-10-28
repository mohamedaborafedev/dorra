# Dorra Design Library database foundation

This document records the initial database phase. Later application features and
updated requirements are summarized in [the current delivery report](reports/delivery.md).

Implemented and verified on 2026-10-28 in the existing starter-kit project.

## Existing installation and preservation

The installed framework is Laravel **12.70.2**, PHP **8.4.12**, MySQL **8.4.3**.
The task describes Laravel 13, but no framework upgrade was performed: this database-only
change uses the installed framework's migration/model conventions. A Laravel 13 upgrade
and its compatibility validation remain a separate task.

The existing `.env` already selected MySQL database `Dorra` (server reports `dorra`),
with one existing user. Its credentials and database name were preserved. No SQLite
database file was present at `database/database.sqlite`. No reset, fresh migration,
table recreation, or user deletion was executed. Existing authentication, theme,
system tables, and email uniqueness remain intact. Existing unrelated composer changes
were left untouched.

## Setup and commands

For a NEW installation, create an empty MySQL database with utf8mb4, then copy
`.env.example` and provide your own credentials. The example uses `dorra_design`,
`127.0.0.1:3306`, `root`, and an empty local password placeholder. Require MySQL
8.0.16+ for enforced CHECK constraints; MySQL 8.4 is the verified target.
Do not repoint an existing installation without planning its data migration.

```sh
php artisan migrate
php artisan db:seed
# Alternatively, seed individually:
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=UserSeeder
# Explicit large-data opt-in (not part of DatabaseSeeder):
php artisan db:seed --class=DesignSeeder
# Counts, actual SHOW INDEX/SHOW CREATE TABLE, FKs, and EXPLAIN:
php artisan dorra:verify-database
php artisan test
php vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

`UserSeeder` and `DesignSeeder` refuse environments other than local/testing.
Categories are matched by slug. Users are matched by email; existing accounts,
roles and passwords are never overwritten or promoted. New sample accounts are
`admin@dorra.test` and `viewer@dorra.test`. Each receives the
local sample password `password`, hashed by the existing User password cast.
At the user's request, all three existing local accounts were also explicitly reset
to `password`. Re-running the seeder still preserves existing passwords.
No authentication UI changed.
Role is intentionally excluded from User's mass-assignable fields.

Design seeding inserts batches of 1,000 and tops up the TOTAL designs count to
100,000. Re-running is a no-op at/above that count and resumes after completed
batches without overwriting existing designs. Run seeders sequentially, without
concurrent writers: this is a local sample generator, not a synchronization system.
A fresh empty database with the same ordered categories produces the same sample
titles, statuses, category distribution and timestamps. Existing data contributes
to the target count; it is not replaced to force a pristine fixture. Category IDs,
auto-increment gaps, and actual user passwords need not match across databases.

The factory creates smaller randomized fixtures with Arabic/English titles. Tests
exercise seeding with 30/37 records; they never generate 100,000 records. All designs
share the real local file `public/placeholders/design.png`; no external images.

## Schema

| Table | Foundation |
|---|---|
| users | Existing columns retained; `role VARCHAR(20) NOT NULL DEFAULT 'viewer'`, MySQL CHECK admin/viewer. Existing rows become viewers. |
| categories | Unsigned bigint PK id; required name VARCHAR(255); required unique slug VARCHAR(255); nullable timestamps. |
| designs | Unsigned bigint PK id; required unsigned bigint category_id FK; required title VARCHAR(255); status VARCHAR(20) NOT NULL DEFAULT 'draft', MySQL CHECK draft/published; nullable thumbnail VARCHAR(255); nullable timestamps. |

Category has many designs; Design belongs to exactly one category. The non-null FK
rejects missing parents and restricts category deletion. Models use typed relationships,
fillable lists, factories, and backed enums for role/status. MySQL enforces allowed
values even for direct inserts; SQLite test migrations omit the MySQL CHECK DDL,
so SQLite tests alone are not evidence of MySQL CHECK enforcement.

## Index choices and actual query plans

| Index | Columns in order | Reason and limitation |
|---|---|---|
| users PRIMARY | id | Existing row identity. |
| users_email_unique | email | Existing email uniqueness and login lookup. |
| categories PRIMARY | id | Category identity / FK target. |
| categories_slug_unique | slug | Idempotent seed lookup and uniqueness; no duplicate slug index. |
| designs PRIMARY | id | Identity and deterministic tie-breaker. |
| idx_designs_category_status_created | category_id, status, created_at, id | Equality on category then status leaves the trailing date/id sequence available for ordering. Leftmost category prefix also supports the FK, so no separate category FK index is needed. Without status equality it does not directly provide the requested global date ordering within a category. Status-only filters cannot use its leftmost prefix. |
| idx_designs_title | title | B-tree candidate for `LIKE 'Modern%'` or an Arabic prefix. A leading wildcard (`LIKE '%Modern%'`) removes the selective leftmost prefix. This index does not simultaneously provide date/id sorting. |
| idx_designs_created_id | created_at, id | Newest listing, stable date/id order, LIMIT 24. Filtered listings may scan and reject many rows. Deep OFFSET pagination can still become expensive; future cursor pagination needs a documented policy for nullable timestamps. |

All secondary index directions are **ASC**, as produced by Schema Builder; this is
intentional and verified in actual `SHOW INDEX` (`Collation: A`). MySQL can scan
these indexes backward for the requested `created_at DESC, id DESC` after equality
constraints. Physical DESC storage could allow a forward scan; it was not required
to satisfy these queries, and no performance advantage is claimed. Mixed sort
directions need separate consideration. See the [MySQL descending index documentation](https://dev.mysql.com/doc/refman/8.4/en/descending-indexes.html).

`docs/database-verification.json` contains actual SHOW INDEX, SHOW CREATE TABLE,
foreign keys, counts, and EXPLAIN outputs. Only the primary and three requested
secondary indexes exist on designs; there is no redundant FK-only index.

Observed optimizer choices on this dataset (estimates, not timings):

- Category 3 + published: `idx_designs_category_status_created`, backward scan.
- Default newest 24: `idx_designs_created_id`, backward scan.
- `Modern%` + newest: optimizer chose `idx_designs_created_id` plus filtering,
  rather than the title index. A possible title index does not guarantee its use.
- Category-only and status-only: creation index with filtering on this dataset.

No standalone status, updated_at or thumbnail indexes were added. A future
`(status, created_at, id)` index is a candidate only after measuring status-filtered
queries. Category-only queries may similarly warrant a different composite after
measurement. These are initial candidates, supported by observed execution plans,
not a proven optimal strategy for every distribution.

## Arabic, English, collation and time zones

The existing `utf8mb4_unicode_ci` collation is retained for compatibility, including
the existing unique email index. It stores Arabic and English. English matching is
case-insensitive, with collation-defined accent/equivalence behavior. It is not an
Arabic search normalizer, transliterator, stemmer, or fuzzy matcher. Test any desired
hamza/alef, diacritic and Unicode normalization policy explicitly before building
search. SQL equality and LIKE do not necessarily treat multi-character equivalences
identically; do not extrapolate from one comparison to all searches.

Actual equality probes on this MySQL server returned:
`Modern = modern`: 1; `أحمد = احمد`: 1; `شعار = شِعار`: 0.
These observations do not promise all Arabic spelling variants match. See
[MySQL collation implementation details](https://dev.mysql.com/doc/refman/8.4/en/charset-collation-implementations.html).
Future search should bind parameters and escape literal `%` and `_` when they are
user text, then append the intended trailing wildcard.

The MySQL connection now explicitly uses `DB_TIMEZONE`, default `+00:00`, consistent
with the application's UTC timestamps. Initial seeding encountered the server's local
DST gap and stopped after one completed batch. UTC fixed the connection and the
generator resumed; no rows were deleted. Previously stored TIMESTAMP instants are
preserved; UTC sessions may display pre-existing instants differently from local-time
sessions. Choose user-facing timezone conversion in the later application phase.

## Actual verification

- MySQL 8.4.3, active session UTC.
- Users: **3** total: original user preserved plus one new Admin and one new Viewer.
  Roles: 1 admin, 2 viewers. Both new passwords are hashed.
- Categories: **10**; designs: **100,000**; orphan designs: **0**.
- Arabic titles: **50,000**; English titles: **50,000**.
- Draft: **50,001**; published: **49,999**; distinct creation times: **100,000**.
- Re-running all seeders left these counts unchanged.
- Transactional MySQL probes were rolled back: invalid role/status rejected (3819),
  missing category rejected (1452), deleting a referenced category rejected (1451),
  duplicate category slug rejected (1062). Actual MySQL schema and indexes inspected.
- **39 tests passed, 94 assertions**, including all original 32 tests. The task's
  historical count of 38 was not the count present in this checkout.
- Larastan/PHPStan level 7: no errors.

## Reproducible future benchmark

No before/after timings have been measured or claimed. Keep the same 100,000-row
dataset, MySQL version, collation, configuration and query parameters. Save SQL,
EXPLAIN, EXPLAIN ANALYZE actual execution details, and repeated wall-clock timings
for each query. Record run counts, warm-up, buffer pool/cache state, competing
workload, and whether caches are warm or cold. Report medians and spread; isolate
SQL time from Blade rendering, transport and image loading.

Indexes already exist, so obtain a non-destructive baseline using identical queries
with `IGNORE INDEX (idx_designs_category_status_created, idx_designs_title,
idx_designs_created_id)` on the same table, verifying EXPLAIN each time. This compares
plans without those secondary access paths, not index maintenance/storage costs.
For a true physical index-free baseline, use a separate disposable database clone
of this exact dataset, retain primary/FK-supporting indexes, and document that
difference. Never drop required indexes or reset the active database to benchmark.
Use `LIMIT 24` and `ORDER BY created_at DESC, id DESC` consistently.

## File inventory and remaining scope

Created: migrations `2026_10_28_000001_add_role_to_users_table.php`,
`2026_10_28_000002_create_categories_table.php`, `2026_10_28_000003_create_designs_table.php`;
`app/Enums/{UserRole,DesignStatus}.php`; `app/Models/{Category,Design}.php`;
`database/factories/{CategoryFactory,DesignFactory}.php`;
`database/seeders/{CategorySeeder,UserSeeder,DesignSeeder}.php`;
`app/Console/Commands/VerifyDesignDatabase.php`; `tests/Feature/DesignDatabaseTest.php`;
`public/placeholders/design.png`; this document; `docs/database-verification.json`.

Modified: `.env.example`, `config/database.php`, `app/Models/User.php`,
`database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`.
The existing `.env` was not modified.

Database foundation is implemented. CRUD, search/filter UI, authorization policies
and middleware, APIs, framework upgrade, and performance benchmarking remain future
work. None of those application features were introduced here.
