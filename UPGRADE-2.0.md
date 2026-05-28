# Upgrading from DocLite 1.x to 2.0

DocLite 2.0 is a major-version release. This document tracks the
breaking changes you'll need to address when upgrading.

This file is updated as work on the 2.0 branch lands. Bullet points
without a section reference are placeholders — concrete before/after
examples are added when the corresponding code change merges.

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
`DatabaseConnection`, and `FileSystem` are now `final`. They were
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
