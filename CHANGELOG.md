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

### Bug fixes
- (added as Phase 1 commits land)

### Security
- (added as Phase 2 commits land)

### Features
- `Collection::insertMany()` — wrap an iterable of raw rows in a single transaction.
- `Collection::saveAll()` — same for an iterable of Document/object arguments.
- `Collection::upsertBy(criteria, data)` — single-call upsert: returns the resulting Document, whether created or updated.
- `QueryBuilder::paginate(page, perPage)` returns a `PaginatedResult` with items, total, page, perPage, pageCount, and hasMore.
- Aggregation on QueryBuilder: `sum()`, `avg()`, `min()`, `max()`, `groupBy(field, aggregates)`.

### Documentation
- Added `SECURITY.md`, `CONTRIBUTING.md`, `UPGRADE-2.0.md`.

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
