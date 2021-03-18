# Changelog

## 1.0.4 Unreleased (master)
 
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