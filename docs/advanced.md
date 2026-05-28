# Advanced

- [Import and export data](#import-and-export-data)
  - [Importing data](#importing-data)
  - [Exporting data](#exporting-data)
- [JSONB storage (SQLite 3.45+)](#jsonb-storage-sqlite-345)
- [PSR-16 cache adapter](#psr-16-cache-adapter)
- [Sync mode](#sync-mode)
- [Journal mode](#journal-mode)
- [Query logging and PSR-3 logger](#query-logging-and-psr-3-logger)
- [Other database methods](#other-database-methods)

## Import and export data

DocLite can import and export data as JSON, YAML, XML, or CSV.

> **Warning:** Import/export operations on very large collections may exhaust memory. For production use with large datasets, prefer JSON format. Support for XML, YAML, and CSV is experimental.

### Importing data

Data can be organized in two ways:

- **Collections mode** — one file per collection, each containing multiple documents.
- **Documents mode** — one directory per collection, each directory containing one file per document.

```php
$db = new FileDatabase('/path/to/data.db');

// One file per collection
$db->import('/path/to/data', 'json', Database::IMPORT_COLLECTIONS);

// One directory per collection, one file per document
$db->import('/path/to/data', 'json', Database::IMPORT_DOCUMENTS);
```

Collection names are inferred from file or directory names (e.g. `users.json` → `users` collection).

When using CSV format, the first line of each file is treated as a header row containing field names.

If a document in the import data has an ID matching an existing document, it overwrites that document. Otherwise a new document is created.

> Each collection import is wrapped in a single transaction and is atomic. You can speed up bulk imports by temporarily relaxing the [sync](#sync-mode) and [journal](#journal-mode) modes.

### Exporting data

```php
// Export all collections — one file per collection
$db->export('/path/to/export', 'json', Database::EXPORT_COLLECTIONS);

// Export all collections — one file per document
$db->export('/path/to/export', 'json', Database::EXPORT_DOCUMENTS);

// Export only specific collections
$persons = $db->collection('persons');
$db->export(
    '/path/to/export',
    'json',
    Database::EXPORT_COLLECTIONS,
    ['users', $persons],
);
```

`export(string $path, string $format, int $mode, array $collections = [])`:
- `$path` — output directory
- `$format` — `json`, `yaml`, `xml`, or `csv`
- `$mode` — `Database::EXPORT_COLLECTIONS` or `Database::EXPORT_DOCUMENTS`
- `$collections` — mix of collection name strings and/or `Collection` objects; omit to export all

> **Note (XML):** The XML standard imposes restrictions on entity names. DocLite replaces invalid characters in field names with underscores, which may prevent exact round-trip restore.

### Streaming export

For large collections, use streaming export to avoid loading all documents into memory at once:

```php
$db->exportStream('/path/to/export', 'json', Database::EXPORT_COLLECTIONS);
```

The streaming export writes one document at a time, making it suitable for collections too large to hold in memory.

## JSONB storage (SQLite 3.45+)

When SQLite 3.45 or newer is available, you can opt in to BLOB-backed JSONB storage for new collections. Reads still return text JSON; the storage difference is internal.

```php
$db = new FileDatabase('./data', useJsonb: true);
```

If the SQLite library is older, the flag is silently ignored and tables fall back to TEXT JSON. Existing TEXT-JSON databases are not auto-migrated; they continue to function as before.

## PSR-16 cache adapter

`CollectionCacheAdapter` implements `Psr\SimpleCache\CacheInterface` backed by a DocLite collection.

```php
use Gebler\Doclite\Cache\CollectionCacheAdapter;

$cache = new CollectionCacheAdapter($db);
$cache->set('greeting', 'hello', 3600);
$cache->get('greeting'); // 'hello'
```

The adapter:
- Honours PSR-16 key constraints
- Accepts `int` (seconds) and `DateInterval` TTLs
- Stores values via PHP `serialize()` so arrays and objects round-trip correctly

## Sync mode

The SQLite synchronization mode controls how aggressively SQLite writes are flushed to disk. Disabling sync can improve write throughput but risks data loss on crash or power failure.

See [SQLite documentation](https://www.sqlite.org/pragma.html#pragma_synchronous) for full details.

| Constant | Meaning |
| -------- | ------- |
| `SyncMode::OFF` | Disable sync |
| `SyncMode::NORMAL` | Normal sync (**default**) |
| `SyncMode::FULL` | Full sync |
| `SyncMode::EXTRA` | Extra sync |

```php
use Gebler\Doclite\Enum\SyncMode;

$db->setSyncMode(SyncMode::FULL);
$mode = $db->getSyncMode(); // returns SyncMode enum case
```

## Journal mode

The rollback journal mode controls how SQLite manages transaction rollback data.

> **Warning:** Setting `JournalMode::NONE` disables the rollback journal. Transactions, atomic commits, and rollbacks will not work and their behaviour is undefined. Do not use [collection transactions](collections.md#collection-transactions) with `JournalMode::NONE`.

See [SQLite documentation](https://www.sqlite.org/pragma.html#pragma_journal_mode) for full details.

| Constant | Meaning |
| -------- | ------- |
| `JournalMode::NONE` | Disable the rollback journal |
| `JournalMode::MEMORY` | In-memory rollback journal only |
| `JournalMode::WAL` | Write-ahead log (**default**) |
| `JournalMode::DELETE` | Delete journal at end of each transaction |
| `JournalMode::TRUNCATE` | Truncate journal at end of each transaction |
| `JournalMode::PERSIST` | Prevent the journal from being deleted |

```php
use Gebler\Doclite\Enum\JournalMode;

$db->setJournalMode(JournalMode::WAL);
$mode = $db->getJournalMode(); // returns JournalMode enum case
```

## Query logging and PSR-3 logger

Pass a [PSR-3](https://www.php-fig.org/psr/psr-3/) logger instance to the database constructor or via `setLogger()` at any time:

```php
$logger = new \Monolog\Logger('doclite');
$db->setLogger($logger);
```

Once a logger is set, exceptions are automatically logged at `error` level.

Enable query logging:

```php
// Log all queries at debug level
$db->enableQueryLogging();

// Log only slow queries (>500 ms) at warning level
$db->enableSlowQueryLogging();

// Disable
$db->disableQueryLogging();
$db->disableSlowQueryLogging();
```

## Other database methods

```php
// Return the DocLite version as a SemVer string, e.g. "2.0.0"
$db->getVersion();

// Optimize the database (reduces file size, improves performance)
// Throws DatabaseException on failure.
$db->optimize();
```

---

See also:

- [Getting started](getting-started.md)
- [Collections & documents](collections.md)
- [Queries](queries.md)
- [Symfony integration](symfony.md)
