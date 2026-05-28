# Getting Started

- [Requirements](#requirements)
- [Installation](#installation)
- [Your first database](#your-first-database)
- [Memory database](#memory-database)
- [File database](#file-database)
- [Error handling](#error-handling)
- [Next steps](#next-steps)

## Requirements

- PHP 8.1 or above
- The `pdo_sqlite` extension enabled
- SQLite built with the JSON1 extension (bundled in most standard PHP distributions)
- SQLite ≥ 3.18.0

Upgrading from DocLite 1.x? See [UPGRADE-2.0.md](../UPGRADE-2.0.md) before installing.

## Installation

```bash
composer require dwgebler/doclite
```

## Your first database

DocLite provides both a `FileDatabase` and a `MemoryDatabase`. Creating one is all you need to get started.

```php
use Gebler\Doclite\FileDatabase;

$db = new FileDatabase('./data');
$users = $db->collection('users');

$alice = $users->get();
$alice->setValue('name', 'Alice');
$alice->setValue('email', 'alice@example.com');
$alice->save();

$found = $users->findOneBy(['email' => 'alice@example.com']);
echo $found->getValue('name'); // Alice
```

If the path passed to `FileDatabase` does not exist it is created (including any parent directories). If it is a directory without a filename, a default file `data.db` is used.

## Memory database

`MemoryDatabase` is stored in volatile memory and lives only for the lifetime of the PHP process.

Constructor parameters (all optional):

| Parameter | Type | Default | Description |
| --------- | ---- | ------- | ----------- |
| `$ftsEnabled` | `bool` | `false` | Enable full-text search (requires FTS5 extension) |
| `$timeout` | `int` | `1` | Connection timeout in seconds if SQLite is locked |
| `$logger` | `LoggerInterface\|null` | `null` | PSR-3 logger instance |

```php
use Gebler\Doclite\MemoryDatabase;

$db = new MemoryDatabase();

// With FTS, a 2-second timeout, and a PSR-3 logger
$logger = new \Monolog\Logger('doclite');
$db = new MemoryDatabase(true, 2, $logger);
```

## File database

`FileDatabase` constructor parameters:

| Parameter | Type | Default | Description |
| --------- | ---- | ------- | ----------- |
| `$path` | `string` | _(required)_ | Path to a new or existing database file or directory |
| `$readOnly` | `bool` | `false` | Open in read-only mode |
| `$ftsEnabled` | `bool` | `false` | Enable full-text search |
| `$timeout` | `int` | `1` | Connection timeout in seconds |
| `$logger` | `LoggerInterface\|null` | `null` | PSR-3 logger instance |
| `$useJsonb` | `bool` | `false` | Use JSONB storage for new collections (SQLite 3.45+) |

The `$path` may be:

- An existing directory with read-write access (creates `data.db` inside it).
- A non-existent file path whose parent directory is writable.
- An existing database file.

```php
use Gebler\Doclite\FileDatabase;

// Open (or create) a database file
$db = new FileDatabase('./data/mydb.db');

// Open in read-only mode
$db = new FileDatabase('./data/mydb.db', true);

// Default filename inside an existing directory
$db = new FileDatabase('/home/data');

// Named parameters (PHP 8.1+)
$db = new FileDatabase(path: './data/mydb.db', readOnly: true, ftsEnabled: true);

// With a PSR-3 logger
$logger = new \Monolog\Logger('mylogger');
$db = new FileDatabase('./data/mydb.db', false, true, 1, $logger);
```

Opening a database in read-only mode allows document retrieval but prevents writes. Any write attempt will throw a `DatabaseException`.

## Error handling

Wrap `FileDatabase` instantiation in a try-catch to handle filesystem and connection errors:

```php
use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\FileDatabase;

try {
    $db = new FileDatabase('/path/to/db');
} catch (IOException $e) {
    // filesystem problem
    var_dump($e->getMessage());
} catch (DatabaseException $e) {
    // database connection problem
    var_dump($e->getMessage());
}
```

`DatabaseException` is the primary exception type thrown by DocLite across `Database`, `Collection`, and `Document` operations. It exposes:

- `getMessage()` — human-readable description
- `getCode()` — one of the `ERR_*` constants on `DatabaseException`
- `getQuery()` — the SQL query that triggered the error (useful for bug reports)
- `getParams()` — array of relevant parameters

| Constant | Meaning |
| -------- | ------- |
| `ERR_COLLECTION_IN_TRANSACTION` | Attempted to begin/rollback/commit while a transaction on a different collection was already in progress |
| `ERR_CONNECTION` | Unable to connect to database |
| `ERR_NO_SQLITE` | PDO SQLite extension is not installed |
| `ERR_NO_JSON1` | SQLite does not have the JSON1 extension |
| `ERR_NO_FTS5` | FTS5 extension not installed |
| `ERR_INVALID_COLLECTION` | Invalid collection name |
| `ERR_MISSING_ID_FIELD` | Custom class does not have an ID field |
| `ERR_INVALID_FIND_CRITERIA` | Attempted to find by a non-scalar value |
| `ERR_INVALID_ID_FIELD` | Specified unique ID field does not exist |
| `ERR_ID_CONFLICT` | Multiple documents in the same collection have the same ID |
| `ERR_CLASS_NOT_FOUND` | Custom class for document mapping does not exist |
| `ERR_INVALID_UUID` | Attempted to get timestamp from an invalid UUID |
| `ERR_QUERY` | Error executing SQL query |
| `ERR_READ_ONLY_MODE` | Attempted a write operation on a read-only database |
| `ERR_INVALID_JSON_SCHEMA` | Attempted to import an invalid JSON schema |
| `ERR_INVALID_DATA` | Data does not match the loaded JSON schema |
| `ERR_MAPPING_DATA` | Unable to map document to class |
| `ERR_IMPORT_DATA` | Error importing data |
| `ERR_IN_TRANSACTION` | Attempting a locking operation while in a transaction |
| `ERR_INVALID_TABLE` | Attempting to access an invalid table |
| `ERR_UNIQUE_CONSTRAINT` | Attempting to insert a document that violates a unique index |

## Next steps

- [Collections & documents](collections.md) — CRUD, transactions, schemas, custom class mapping
- [Queries](queries.md) — query builder, joins, pagination, aggregation, indexing
- [Full-text search](full-text-search.md) — FTS5-powered search
- [Advanced](advanced.md) — import/export, journal modes, JSONB, PSR-16 cache adapter, logging
- [Symfony integration](symfony.md) — wiring DocLite into a Symfony app
