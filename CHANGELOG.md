# Changelog

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
