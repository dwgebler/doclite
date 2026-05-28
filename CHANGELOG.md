# Changelog

## 2.0.0 (unreleased)

Major version. Breaking changes — see `UPGRADE-2.0.md`.

### Tooling
- Bumped minimum PHP to 8.1 (drops 7.4 and 8.0).
- Added `psr/simple-cache` dependency.
- Narrowed `psr/log` to `^3.0`.
- Raised PHPUnit to `^9.6 || ^10.5`, Psalm to `^5.0`.
- Replaced Travis CI with GitHub Actions, matrix PHP 8.1/8.2/8.3/8.4.
- Added CodeQL workflow and Dependabot configuration.
- Added Psalm and PHP_CodeSniffer configurations (`psalm.xml`, `phpcs.xml.dist`).
- Added Docker dev image (`.docker/`) for consistent local tool execution.
- Extracted `CollectionInterface`, `QueryBuilderInterface`, and `DatabaseConnectionInterface` — callers that already type-hint the concrete classes are unaffected; these interfaces are the new recommended type-hint surface.

### Breaking
- `Database::find()` return type is now `?string`; the no-match sentinel is `null` (was `''`). See UPGRADE-2.0.md §2.
- Mode constants (`Database::MODE_SYNC_*` etc.) are superseded by `SyncMode`, `JournalMode`, `ImportMode`, `ExportMode` enums; constants are `@deprecated` and will be removed in 3.0. See UPGRADE-2.0.md §3.
- Default SQLite busy-timeout raised from 1 s to 5 s. See UPGRADE-2.0.md §4.
- `Document`, `Collection`, `QueryBuilder`, `FileDatabase`, `MemoryDatabase`, `DatabaseConnection`, and related classes are now `final`. See UPGRADE-2.0.md §5.
- `psr/log` requirement narrowed to `^3.0`. See UPGRADE-2.0.md §6.
- Index names now use double-underscore separator (`idx_table__field`). See UPGRADE-2.0.md §7.
- `QueryBuilder` field-name validation is now stricter; punctuation other than `.` and `[]` is rejected. See UPGRADE-2.0.md §8.
- YAML collection exports now stream as a YAML 1.2 multi-document stream instead of a single array. See UPGRADE-2.0.md §10.
- `DatabaseInterface::createIndex` signature widened: new `?string $where` parameter inserted before `...$fields`. See UPGRADE-2.0.md §11.
- `DatabaseInterface`, `CollectionInterface`, and `QueryBuilderInterface` gained new methods. See UPGRADE-2.0.md §12.

### Security
- REGEXP custom function (used by the `MATCHES`/`NOT MATCHES` operators) now rejects NUL/control characters, uses `#` as the safe delimiter with embedded `#` escaped, forces the `u` (Unicode) modifier, and maps `preg_match` errors to 0 (no match) instead of propagating false.
- `DocLiteNameConverter` custom-id is now scoped to a single deserialize call; concurrent generator iterations using different custom-id fields can no longer corrupt each other's converter state.
- `QueryBuilder` field-name validation hardened against injection of special characters (see Breaking section).

### Bug fixes
- `Database::find()` returned `''` on no match, making empty stored documents indistinguishable from absent rows — now returns `null`. (BC fix — see UPGRADE-2.0.md §2.)
- Index names used a single-underscore separator that collided when table or field names contained underscores; now uses `__`.
- `Collection::save()` now validates a document against the collection's JSON schema (if set) before writing to SQLite.
- Cached prepared statements that still had an open cursor caused "SQL logic error: cannot reuse a statement" on reuse; the cursor is now closed before re-execution.
- `Database::scanFtsTables()` prefix-stripping regex and FTS table detection corrected.
- `executePrepared()` now rethrows all SQLSTATE 23000 (unique-constraint) errors instead of silently swallowing them.
- `FileSystem::addFile()` chmod bitmask logic corrected (was ANDing instead of ORing permission bits).
- `Document::getValue()` / `setValue()` now support backslash-escaped dots (`a\.b`) to address a top-level field whose name literally contains a dot, without treating it as a nested-path separator.

### Features
- `Collection::insertMany()` and `Collection::saveAll()` — wrap an iterable of writes in a single transaction.
- `Collection::upsertBy(criteria, data)` — single-call upsert; returns the resulting Document.
- `QueryBuilder::paginate(page, perPage)` returns a `PaginatedResult` with items, total, page, perPage, pageCount, hasMore.
- Aggregation on QueryBuilder: `sum()`, `avg()`, `min()`, `max()`, `groupBy(field, aggregates)`.
- `Collection::addPartialIndex(fields, where, unique=false)` for SQLite partial indexes.
- Opt-in JSONB storage for SQLite 3.45+ (`useJsonb: true` on `FileDatabase`/`MemoryDatabase`).
- Streaming collection export — bounded memory regardless of collection size.
- `Cache\CollectionCacheAdapter` — PSR-16 SimpleCache implementation.
- `Collection::setSchema()` — collection-wide JSON schema validation on save.
- `SyncMode`, `JournalMode`, `ImportMode`, `ExportMode` backed enums replace the `Database::MODE_*` integer/string constants.

### Performance
- Prepared-statement cache capped at 256 entries with LRU eviction; long-running processes no longer accumulate statements unboundedly.
- `FileSystem` realpath/`is_readable`/`is_writable` results are cached in-process; `addFile`/`createPath` invalidate the affected entry, `FileSystem::clearCache()` empties everything.

### Documentation
- Added `SECURITY.md`, `CONTRIBUTING.md`, `UPGRADE-2.0.md`.
- Split monolithic `README.md` into a `docs/` tree.

## 1.1.8

- Fixes #32 where JSON functions are now built in to SQLite 3.38.0 upwards and the check 
for the JSON1 extension was failing. This is now fixed by checking the SQLite version too.

## 1.1.7

- Removes forward slash escaping in serialized JSON data and unquotes results of `json_extract` when finding by multiple fields. This fixes issue #26. 

## 1.1.6

- Added support for injecting a PSR-3 `LoggerInterface` on database connections, and
  functions to log exceptions, all queries or slow queries (see README).
- Added `Collection::addUniqueIndex` function to enable creation of unique indexes.

## 1.1.5

- Bumped some dependencies for compatibility with newer versions of PHP.

## 1.1.4

- Fixed bug where floating point values in QueryBuilder queries incorrectly fail to match in database.

## 1.1.3

- Fixed bug where documents mapped to custom classes do not encode internal ID field correctly 
if the ID contains escapable characters.

## 1.1.2

- Added BETWEEN query operator, equivalent to `field >= {value1} AND field <= {value2}`.
- Added native `DateTimeInterface` handler for query values.

## 1.1.1

- Fixed bug where invalid joins (for example, join on empty collection) could cause 
`TypeError`.
- Fixed incorrect version number in `Database::getVersion()`.

## 1.1.0

- Added support for full text searches (see README docs).
- Added support for joining collections (see README docs).
- Added timeout option to `FileDatabase` and `MemoryDatabase` constructors to indicate 
the SQLite busy timeout in seconds (how long to wait to acquire if DB is locked on connection).

## 1.0.5

  - No functional changes in this release, just changed the licence terms to the more 
    permissive MIT licence.

## 1.0.4
 
  - Fixed bug where QueryBuilder queries are effectively terminated after the 
    first use, due to bound parameters not being reset.
  - Fixed bug where documents mapped to custom classes can't be saved when 
    they don't have the internal ID field or `docliteid` set, due to missing 
    automatic mapping of the known ID field to the internal ID.

## 1.0.3

  - Added `enableCacheAutoPrune`, `setCacheAutoPrune` and `getCacheAutoPrune` 
    database methods.
  - Fixed small bug where new documents with auto-generated UUID made an 
    unnecessary query against the database.
  - Fixed incorrect SQLite version referenced in documentation.  

## 1.0.2

  - Initial public release.
