# Collections & Documents

- [About collections](#about-collections)
- [Obtain a collection](#obtain-a-collection)
- [Create a document](#create-a-document)
- [Save a document](#save-a-document)
- [Retrieve a document](#retrieve-a-document)
- [Map a document to a custom class](#map-a-document-to-a-custom-class)
- [Delete a document](#delete-a-document)
- [Bulk writes](#bulk-writes)
- [Delete a collection](#delete-a-collection)
- [Collection transactions](#collection-transactions)
- [About documents](#about-documents)
- [Getting and setting document data](#getting-and-setting-document-data)
- [Mapping document fields to objects](#mapping-document-fields-to-objects)
- [Document unique ID](#document-unique-id)
- [Document validation (per-document JSON Schema)](#document-validation-per-document-json-schema)
- [Collection-level JSON schema](#collection-level-json-schema)

## About collections

Collections are at the heart of DocLite. A `Collection` represents a named group of documents (for example, "users") and is analogous to a table in a structured database.

> **Note:** Collections are stored as SQLite tables, so they must follow these naming rules:
> - Cannot start with `sqlite_`
> - Cannot start with a number
> - May contain only alphanumeric characters and underscores
> - Cannot be longer than 64 characters

Every document in a collection must have a unique ID. You can either supply this yourself or one will be created for you as a [v1 UUID](https://en.wikipedia.org/wiki/Universally_unique_identifier#Version_1_(date-time_and_MAC_address)), which encodes the creation timestamp.

## Obtain a collection

Collections are obtained from a `FileDatabase` or `MemoryDatabase` by calling `collection()`. If the collection does not exist it will be created automatically.

```php
$users = $db->collection('users');
```

## Create a document

Create a new document by calling `get()` on the collection without an ID:

```php
$user = $users->get();
```

## Save a document

DocLite `Document` objects provide a `save()` method. You can also save any document type through the collection:

```php
// Convenience method on Document objects
$user->save();

// Works for both Document objects and custom-class documents
$users->save($user);
```

## Retrieve a document

Pass an ID to `get()` to retrieve an existing document:

```php
$user = $users->get($id);
```

Simple lookups by field value use `findOneBy` and `findAllBy`:

```php
$user = $users->findOneBy(['username' => 'alice']);

foreach ($users->findAllBy(['active' => true]) as $user) {
    // ...
}

foreach ($users->findAll() as $user) {
    // ...
}
```

For complex filtering see [Queries](queries.md).

## Map a document to a custom class

By default `get()` returns a `Document` object, but any class with public properties or getter/setter methods can be used:

```php
// Retrieve as CustomUser
$user = $users->get($id, CustomUser::class);

// Specify a custom ID property name
$user = $users->get($id, CustomUser::class, 'databaseId');
```

DocLite looks for a property called `id` to populate with the document's unique ID. If you want a different property name, pass it as the third argument or add a public `docliteId` property/getter-setter pair to your class.

Custom-class documents must be saved through the collection:

```php
$users->save($user);

// With a non-default ID property
$users->save($user, $user->getDatabaseId());

// Exclude specific properties from storage
$users->save($user, $user->getDatabaseId(), ['nonDatabaseField']);
```

`findOneBy`, `findAllBy`, `findAll`, and `fetch()` all accept the same optional custom class and ID-property parameters.

## Delete a document

```php
// Convenience method on Document objects
$user->delete();

// Works for both Document objects and custom-class documents
$users->deleteDocument($user);
```

## Bulk writes

```php
$count = $users->insertMany([
    ['name' => 'Alice'],
    ['name' => 'Bob'],
    ['name' => 'Carol'],
]);

$count = $users->saveAll($documents);

$doc = $users->upsertBy(
    ['email' => 'alice@example.com'],
    ['email' => 'alice@example.com', 'name' => 'Alice', 'role' => 'admin'],
);
```

`insertMany` and `saveAll` wrap their iterables in a single transaction and roll back on failure.

`upsertBy` finds an existing document matching all the key-value pairs in the first argument and updates it with the data in the second argument, or inserts a new document if no match is found.

## Delete a collection

To delete all documents in a collection, call `deleteAll()`:

```php
$users->deleteAll();
```

## Collection transactions

Wrap a sequence of operations in a transaction using `beginTransaction()`, `commit()`, and `rollback()`:

```php
$users->beginTransaction();

// ...insert, update, or delete documents...

// Commit and end the transaction
$users->commit();

// Or roll back and end the transaction
$users->rollback();
```

> **Warning:** If the rollback journal mode is set to `MODE_JOURNAL_NONE`, transactions will not work and their behaviour is undefined. See [Advanced](advanced.md#journal-mode) for details.

---

## About documents

Documents are a variadic, freeform store of key-value pairs stored as JSON. Each document inside a collection can have its own structure independent of other documents in the same collection.

By default, a document is represented by the DocLite `Document` class. You can also map documents onto your own classes — see [Map a document to a custom class](#map-a-document-to-a-custom-class) above.

## Getting and setting document data

`Document` provides magic getters, setters, and property accessors for arbitrary keys:

```php
$user = $users->get();

// Magic setter
$user->setUsername('dwgebler');
// Magic property
$user->password = password_hash('admin', PASSWORD_DEFAULT);
// Magic getter
echo $user->getUsername();
// Magic property read
echo $user->password;
// Arrays and objects are fine too
$user->setRoles(['user', 'admin']);
```

Magic methods convert camelCase to snake_case; direct property access is literal:

```php
$user->setFirstName('Dave');  // stored as "first_name"
echo $user->first_name;       // "Dave"

// For case-sensitive keys, use property access only
$user->FirstName = 'Dave';
echo $user->FirstName;
```

Use `getValue()` and `setValue()` to access nested fields with dot `.` notation, or keys that cannot be expressed as method names:

```php
// Nested read
$postcode = $user->getValue('address.postcode');

// List item
$firstRole = $user->getValue('roles.0');

// Nested write (creates "address" if it doesn't exist)
$user->setValue('address.postcode', 'TE1 3ST');

// Key with special characters
$user->setValue('api_access./v1/users/', ['GET', 'POST']);
$access = $user->getValue('api_access./v1/users/');
```

`getValue()` raises a `ValueError` if the path cannot be found. `setValue()` creates any missing parent properties along the path.

## Mapping document fields to objects

If you have retrieved a `Document` and a field contains data that maps to a custom class, use `map()`:

```php
$user = $users->get('b83e319a-7887-11eb-8deb-b9e03d2e720d');

// Map "person" field to a Person class
$user->map('person', Person::class);
// $user->getPerson() now returns a Person object

// Or map to an existing instance
$person = new Person();
$user->map('person', $person);
```

## Document unique ID

Every document has a unique ID. When you create a new document the ID is auto-generated as a v1 UUID. You can read or change it:

```php
$id = $user->getId();
$user->setId('custom-id');
```

> **Note:** Setting a document's ID to a new value causes a new document to be inserted on save. Setting it to an existing document's ID overwrites that document.

If the ID was auto-generated you can retrieve a `DateTimeImmutable` of its creation time:

```php
$date = $user->getTime();
echo $date->format('d m Y H:i');
```

To create a document with a custom ID, pass it to `get()`:

```php
$user = $users->get('user_3815');
```

## Document validation (per-document JSON Schema)

You can attach a [JSON Schema](https://json-schema.org/) to an individual document via `addJsonSchema()`. Once loaded, every `setValue()` call and every `save()` validates against the schema:

```php
$user->addJsonSchema(file_get_contents('schema.json'));

try {
    $user->validateJsonSchema();   // manual validation
    $user->save();                 // also validates automatically
    $user->setUsername('foobar'); // also validates automatically
} catch (DatabaseException $e) {
    $params = $e->getParams();
    echo 'Validation failed: ' . $params['error'];
}

$user->removeJsonSchema(); // remove schema and stop validating
```

## Collection-level JSON schema

You can enforce a JSON schema across an entire collection. Every write operation (`save`, `insertMany`, `saveAll`, `upsertBy`) will validate against it:

```php
$users->setSchema(json_encode([
    'type' => 'object',
    'required' => ['email', 'name'],
    'properties' => [
        'email' => ['type' => 'string', 'format' => 'email'],
        'name'  => ['type' => 'string'],
    ],
]));

// All writes now validate
$users->insertMany([['email' => 'a@b.com', 'name' => 'Alice']]);

$users->removeSchema(); // turn off enforcement
```

---

See also:

- [Queries](queries.md) — query builder, joins, pagination, aggregation, indexing
- [Full-text search](full-text-search.md) — FTS5-powered search
- [Advanced](advanced.md) — import/export, journal modes, JSONB, PSR-16 cache adapter
