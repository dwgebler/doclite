# Queries

- [Simple lookups](#simple-lookups)
- [Advanced query builder](#advanced-query-builder)
  - [Query operators](#query-operators)
  - [Nested queries](#nested-queries)
- [Join collections](#join-collections)
- [Pagination](#pagination)
- [Aggregation](#aggregation)
- [Caching results](#caching-results)
- [Indexes](#indexes)
- [Ordering, limit and offset](#ordering-limit-and-offset)

## Simple lookups

The simplest way to find documents by field values:

```php
// Find a single matching document, or null
$user = $users->findOneBy(['username' => 'alice']);

// Find all matching documents (returns a generator)
foreach ($users->findAllBy(['active' => true]) as $user) {
    // ...
}

// Retrieve all documents
foreach ($users->findAll() as $user) {
    // ...
}
```

All three methods accept an optional custom class name and custom ID property as extra parameters (see [Collections](collections.md#map-a-document-to-a-custom-class)).

## Advanced query builder

For more complex filtering, use the `where()`, `and()`, `or()`, `limit()`, `offset()`, and `orderBy()` methods, then terminate with `fetch()`, `fetchArray()`, `delete()`, or `count()`.

You can query nested document fields by separating levels with `.` and append `[]` to a field name to search within all items in a list.

### Sample queries

The examples below assume user documents shaped like this:

```yaml
username: adamjones
first_name: Adam
last_name: Jones
address:
  postcode: TE1 3ST
roles:
  - USER
  - EDITOR
telephone: "+441234567890"
active: true
registered: true
lastLogin: "2021-02-13T10:34:40+00:00"
email: adamjones@example.com
api_access:
  "/v1/pages/":
    - POST
    - GET
```

```php
$users = $db->collection('users');

// Simple equality
$activeUsers = $users->where('active', '=', true)->fetch();

// Text operators
$gmailUsers = $users->where('email', 'ENDS', '@gmail.com')->fetch();

// Multiple conditions
$registered = $users->where('registered', '=', true)
                    ->and('active', '=', false)
                    ->fetch();

// Nested field
$tePostcode = $users->where('address.postcode', 'STARTS', 'TE1')->fetch();

// List membership — users who have the EDITOR role
$editors = $users->where('roles[]', '=', 'EDITOR')->fetch();

// List — users with ADMIN or EDITOR
$adminOrEditor = $users->where('roles[]', '=', 'ADMIN')
                       ->or('roles[]', '=', 'EDITOR')
                       ->fetch();

// Exact list match
$both = $users->where('roles', '=', ['ADMIN', 'EDITOR']);

// Users who do NOT have the ADMIN role at all
$notAdmin = $users->where('roles', 'NOT CONTAINS', '"ADMIN"')->fetch();

// Date range (BETWEEN is >= AND <=)
$start = new DateTimeImmutable('2021-03-01');
$end   = new DateTime('2021-06-30');
$signups = $users->where('date', 'BETWEEN', $start, $end)->fetchArray();

// Nested list lookup
$postAccess = $users->where('api_access./v1/pages/[]', '=', 'POST')->fetch();

// Ordering, limit, offset
$page1 = $users->orderBy('first_name', 'ASC')->limit(10)->fetch();
$page2 = $users->orderBy('first_name', 'ASC')->limit(10)->offset(10)->fetch();

// Count without fetching documents
$count = $users->where('active', '=', true)->count();

// Delete matching documents
$users->where('roles[]', '=', 'EDITOR')->delete();
```

> `fetch()` returns a generator. Use `fetchArray()` if you need a plain array.

> `fetch()` accepts the same optional custom class name and ID property as `findOneBy` and `findAll`.

### Query operators

| Operator | Meaning |
| -------- | ------- |
| `=` | Equals (exact match) |
| `!=` | Not equals |
| `<` | Less than |
| `>` | Greater than |
| `<=` | Less than or equal |
| `>=` | Greater than or equal |
| `BETWEEN` | Between two values, inclusive (>= AND <=) |
| `NOT BETWEEN` | Not between two values (< OR >) |
| `STARTS` | Text starts with |
| `NOT STARTS` | Text does not start with |
| `ENDS` | Text ends with |
| `NOT ENDS` | Text does not end with |
| `CONTAINS` | Text contains |
| `NOT CONTAINS` | Text does not contain |
| `MATCHES` | Regular expression match |
| `NOT MATCHES` | Negative regular expression match |
| `EMPTY` | Has no value (null) |
| `NOT EMPTY` | Has any value (not null) |

### Nested queries

Use `union()` to group conditions with `OR` and `intersect()` to group with `AND`:

```php
// (active=true AND postcode matches regex) OR (has EDITOR role AND lastLogin > date)
$result = $users->where('active', '=', true)
                ->and('address.postcode', 'MATCHES', '^[A-Za-z0-9 ]*$')
                ->union()
                ->where('roles[]', '=', 'EDITOR')
                ->and('lastLogin', '>', '2021-01-30')
                ->fetch();
```

## Join collections

Join one or more additional collections into a query result, similar to a foreign key relationship:

```php
/**
 * User: {"__id":"1", "name":"John Smith"}
 * Comment: {"__id":"5", "user_id":"1", "comment":"Hello world!"}
 *
 * Result: {"__id":"1","name":"John Smith","comments":[{"__id":"5","comment":"Hello world!"}]}
 */
$users    = $db->collection('users');
$comments = $db->collection('comments');

$users->where('__id', '=', '1')
      ->join($comments, 'user_id', '__id')
      ->fetchArray();
```

`Collection::join($collection, $foreignKey, $localKey)`:
- `$collection` — the collection to join
- `$foreignKey` — the field in the joined collection that references the local collection
- `$localKey` — the field in the local collection to match against

You can chain multiple joins and combine with other query operators.

## Pagination

`paginate(int $page, int $perPage)` returns a `PaginatedResult` value object with `items`, `total`, `page`, `perPage`, `pageCount`, and `hasMore`:

```php
$page = $users->where('status', '=', 'active')
              ->orderBy('created_at', 'DESC')
              ->paginate(1, 20);

foreach ($page->items as $item) {
    echo $item->getValue('name');
}
echo "Page {$page->page} of {$page->pageCount}";
```

`page` and `perPage` are clamped to a minimum of 1.

## Aggregation

```php
$total = $users->where('status', '=', 'active')->sum('amount');
$mean  = $users->where('status', '=', 'active')->avg('amount');
$max   = $users->where('status', '=', 'active')->max('amount');
$min   = $users->where('status', '=', 'active')->min('amount');

foreach ($users->where('amount', '>', 0)->groupBy('category', [
    'total' => 'SUM(amount)',
    'count' => 'COUNT(*)',
]) as $row) {
    echo "{$row['category']}: {$row['total']} ({$row['count']})";
}
```

`groupBy` aggregate expressions are restricted to `SUM(field)`, `AVG(field)`, `MIN(field)`, `MAX(field)`, and `COUNT(*)`. Anything else is rejected.

## Caching results

DocLite can cache query results to speed up repeated retrieval of complex result sets. For very simple queries this may not help or may even add slight overhead.

```php
$users->enableCache();

// Cache results for 1 hour (default is 60 seconds; 0 = never expires)
$users->setCacheLifetime(3600);

$users->disableCache();
$users->clearCache();
```

Enable automatic pruning of expired cache entries at the database level:

```php
$db->enableCacheAutoPrune();
```

> For complex, frequently repeated queries on large collections that don't change often, caching can give a significant speedup.

## Indexes

Create indexes on document fields to speed up queries:

```php
// Regular index on a single field
$users->addIndex('email');

// Composite index on multiple fields
$users->addIndex('first_name', 'last_name');

// Unique index — enforces no two documents share the same value
$users->addUniqueIndex('email');

// Partial index — only covers rows satisfying the predicate
$users->addPartialIndex(
    ['email'],
    "json_extract(json,'\$.status') = 'active'",
    unique: true,
);
```

If you try to insert a document that violates a unique index, a `DatabaseException` with code `ERR_UNIQUE_CONSTRAINT` is thrown.

Partial indexes only cover rows satisfying the predicate. The predicate is appended verbatim to the `CREATE INDEX` statement — it must be trusted, developer-authored SQL (the same trust contract as `Collection::executeDqlQuery`).

> Indexes work the same way they do in any SQLite database, only they are created on JSON document fields rather than table columns. Poorly chosen indexes may provide no benefit or even slow queries.

## Ordering, limit and offset

Chain `orderBy()`, `limit()`, and `offset()` before calling `fetch()` or `fetchArray()`:

```php
$users->orderBy('last_name', 'ASC')
      ->orderBy('first_name', 'ASC')
      ->limit(25)
      ->offset(50)
      ->fetch();
```

---

See also:

- [Collections & documents](collections.md)
- [Full-text search](full-text-search.md)
- [Advanced](advanced.md)
