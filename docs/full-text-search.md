# Full-text search

DocLite can build powerful full-text indexes against collections, letting you search for documents and rank them by relevance.

## Requirements

Full-text search requires SQLite compiled with the [FTS5 extension](https://www.sqlite.org/fts5.html). This is usually bundled in standard PHP distributions alongside the JSON1 extension.

## Enable FTS

Pass `true` as the `$ftsEnabled` parameter when creating your database:

```php
use Gebler\Doclite\FileDatabase;

$db = new FileDatabase(
    path: '/path/to/db',
    readOnly: false,
    ftsEnabled: true,
);
```

Or for a `MemoryDatabase`:

```php
use Gebler\Doclite\MemoryDatabase;

$db = new MemoryDatabase(ftsEnabled: true);
```

If FTS5 is not available and you try to use `search()`, a `DatabaseException` with code `ERR_NO_FTS5` is thrown.

## Searching a collection

Call `search()` on a collection with a phrase and a list of document fields to search against:

```php
$posts = $db->collection('posts');
$results = $posts->search('apache', ['title', 'summary', 'content']);
```

Results are automatically ordered by relevance. `search()` returns a generator — use `iterator_to_array()` to convert to an array.

## Combining FTS with filters

Because `search()` is part of the standard query-building interface, you can precede it with `where()`, `and()`, `or()`, etc.:

```php
$results = $posts->where('published', '=', true)
                 ->search('apache', ['title', 'summary', 'content']);
```

## Index management

DocLite manages full-text indexes automatically:

- If no index exists for the set of fields you search on, it is created on the first `search()` call.
- If you later call `search()` on a **superset** of fields covered by an existing index, the old index is dropped and a new, larger index is created to cover all searched fields.

On small collections this is near-instant. On very large collections you may want to pre-warm indexes from a separate script so they exist before your application starts serving traffic:

```php
// Run once from a CLI script to pre-build the index
$posts->search('warmup', ['title', 'summary', 'content']);
```

---

See also:

- [Getting started](getting-started.md) — enabling FTS at database creation
- [Collections & documents](collections.md)
- [Queries](queries.md)
