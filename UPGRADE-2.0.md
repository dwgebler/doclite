# Upgrading from DocLite 1.x to 2.0

DocLite 2.0 is a major-version release. This document tracks the
breaking changes you'll need to address when upgrading.

## Required actions

### 1. PHP 8.1 minimum

DocLite 2.0 requires PHP 8.1 or newer. Versions 7.4 and 8.0 are no
longer supported and have been EOL upstream for over two years.

If you're stuck on an older PHP, pin to DocLite 1.x:

```json
"dwgebler/doclite": "^1.1"
```

### 2. `Database::find()` returns `?string`

Previously, `Database::find()` returned `string` and used `''` to
signal "no match". This was lossy — a stored document with an empty
JSON value couldn't be distinguished from an absent row. As of 2.0
the return type is `?string` and the no-match value is `null`.

Before:

```php
$json = $db->find('users', ['email' => $email]);
if ($json === '') {
    // not found
}
```

After:

```php
$json = $db->find('users', ['email' => $email]);
if ($json === null) {
    // not found
}
```

### 3. Mode constants are now PHP 8.1 enums

The `Database::MODE_SYNC_*`, `MODE_JOURNAL_*`, `MODE_IMPORT_*`, and
`MODE_EXPORT_*` constants are superseded by backed enums in the same
namespace: `SyncMode`, `JournalMode`, `ImportMode`, and `ExportMode`.

Before:

```php
$db->setSyncMode(Database::MODE_SYNC_NORMAL);
$db->setJournalMode(Database::MODE_JOURNAL_WAL);
```

After (preferred):

```php
$db->setSyncMode(SyncMode::Normal);
$db->setJournalMode(JournalMode::Wal);
```

The original constants remain available and continue to work as scalar
arguments — they are now `@deprecated` and will be removed in 3.0.
The new enums live in the same namespace: `SyncMode`, `JournalMode`,
`ImportMode`, `ExportMode`.

### 4. Default SQLite busy-timeout raised to 5 seconds

Previously 1 second. The old value caused "database is locked"
errors under even mild contention. Pass `timeout: 1` to the
`FileDatabase`/`MemoryDatabase` constructor to restore the old default
if you depend on the faster failure path.

### 5. Internal classes are now `final`

`Document`, `Collection`, `QueryBuilder`, `DocLiteNameConverter`,
`FileDatabase`, `MemoryDatabase`, `DatabaseConnection`, `FileSystem`,
`DatabaseException`, and `IOException` are now `final`. They were
never designed for extension. If you were subclassing them, prefer
composition or open an issue describing your use case.

### 6. `psr/log` requirement narrowed to `^3.0`

DocLite 2.0 drops support for `psr/log` 1.x and 2.x. If you inject a
PSR-3 logger, ensure it implements the 3.0 interface (no return-type
declarations elsewhere were affected).

### 7. Index naming uses double-underscore separator

Indexes created from now on use `idx_<table>__<field1>__<field2>` instead
of `idx_<table>_<field1>_<field2>`. Existing indexes from DocLite 1.x are
left untouched and continue to function. If your application introspects
SQLite's internal `sqlite_master` table by index name, update the
expected pattern.

### 8. Stricter field-name validation in QueryBuilder

`Collection::where`, `Collection::orderBy`, and other query-building
methods now validate field names against a stricter pattern:
segments of `[A-Za-z_][A-Za-z0-9_]*`, joined by `.`, with optional
`[<digits>]` array indices and an optional trailing `[]`.

Field names containing double quotes, backslashes, dollar signs,
single quotes, semicolons, or other punctuation are now rejected.
If you were passing such identifiers (in practice rare), either
rename your document fields or open an issue describing the use case.

### 9. REGEXP is hardened (MATCHES operator)

The custom REGEXP SQLite function used by the `MATCHES` / `NOT MATCHES`
QueryBuilder operators now uses `#` as the regex delimiter, escapes
embedded `#` characters, forces the `u` (Unicode) modifier, and
returns 0 (no match) for malformed patterns or patterns containing
NUL/control characters. If your application relied on injecting
PCRE delimiters or modifiers via the pattern string, that pathway
is closed. Standard PCRE syntax — character classes, anchors,
quantifiers, capture groups — continues to work as before.

### 10. YAML collection export uses a multi-document stream

Previously, exporting a collection in YAML mode produced a single YAML
array of all documents. From 2.0, collection exports stream documents
as a YAML 1.2 multi-document stream — each document is prefixed by
`---\n`. This change supports memory-bounded streaming of large
collections.

If you are loading YAML exports back into DocLite via `Database::import()`,
no changes are needed — the import path handles both forms. If you are
parsing the YAML directly in another tool, switch from `Yaml::parse()`
to `Yaml::parseFile()` with `Yaml::PARSE_CONSTANT` flags, or split on
`/^---$/m` and parse each chunk:

```php
foreach (preg_split('/^---\s*$/m', $yaml, -1, PREG_SPLIT_NO_EMPTY) as $chunk) {
    $document = Yaml::parse($chunk);
    // ...
}
```

### 11. `Database::createIndex()` signature widened

`createIndex` now accepts an optional `?string $where` parameter
between `$unique` and `...$fields`:

```php
// before
$db->createIndex('users', true, 'email');

// after (BC — pass null for non-partial indexes)
$db->createIndex('users', true, null, 'email');
```

If you weren't calling this method directly — `Collection::addIndex`
and `Collection::addUniqueIndex` are the public surface and are
unchanged — you don't need to do anything.

### 12. New `useJsonb` constructor parameter (additive)

`FileDatabase` and `MemoryDatabase` accept a new `bool $useJsonb = false` trailing
parameter. Passing `true` opts new collections into JSONB BLOB storage (requires
SQLite 3.45+); passing the default `false` (or omitting it entirely) keeps
the existing JSON text behaviour.

Existing databases opened without `useJsonb: true` are entirely unaffected — the
`_doclite_meta` table is only created when at least one JSONB collection is
requested, and existing text-JSON tables are never auto-migrated.

**Compatibility note**: the parameter is positional. If you were previously
passing arguments by position past the `$fileSystem` parameter (e.g. injecting
a `DatabaseConnectionInterface` and `FileSystemInterface` simultaneously), add
`useJsonb: true` as a named argument rather than a positional one to avoid
accidental mismatch — though in practice positional calls past `$fileSystem`
are uncommon.

### 13. Interface additions

The following methods were added to public interfaces. Concrete classes
shipped with DocLite (`FileDatabase`, `MemoryDatabase`, `Collection`,
`QueryBuilder`) already implement them. If you have your own
implementation of an interface, you'll need to add them too:

- `DatabaseInterface`: `getConnection()`, `isJsonbCollection()`,
  `executeDqlQuery()`, `executeDmlQuery()`.
- `CollectionInterface`: `insertMany()`, `saveAll()`, `upsertBy()`,
  `addPartialIndex()`, `setSchema()`, `removeSchema()`, `getSchema()`,
  `isJsonbStorage()`.
- `QueryBuilderInterface`: `paginate()`, `sum()`, `avg()`, `min()`,
  `max()`, `groupBy()`.
- `DatabaseConnectionInterface` (new in 2.0): full public surface of
  `DatabaseConnection`. If you were injecting a custom connection
  implementation, implement this interface.
