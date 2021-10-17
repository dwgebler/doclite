# DocLite
A powerful PHP NoSQL document store built on top of SQLite.

[![Build Status](https://travis-ci.com/dwgebler/doclite.svg?token=uj4HfXm5wqJXVuPAd984&branch=master)](https://app.travis-ci.com/github/dwgebler/doclite)

## Table of contents

- [About DocLite](#about-doclite)
  - [Why DocLite?](#why-doclite)
- [Getting Started](#getting-started)
- [The Database](#the-database)
  - [Creating a memory database](#creating-a-memory-database)
  - [Creating a file database](#creating-a-file-database)
  - [Error Handling](#error-handling)
  - [Import and export data](#import-and-export-data)
    - [Importing Data](#importing-data)
    - [Exporting data](#exporting-data)
  - [Advanced options](#advanced-options)
    - [Get DocLite version](#get-doclite-version)
    - [Optimize database](#optimize-database)
    - [Set synchronization mode](#set-synchronization-mode)
    - [Set rollback journal mode](#set-rollback-journal-mode)
- [Collections](#collections)
  - [About Collections](#about-collections)
  - [Obtain a collection](#obtain-a-collection)
  - [Create a document](#create-a-document)
  - [Save a document](#save-a-document)
  - [Retrieve a document](#retrieve-a-document)
  - [Map a document to a custom class](#map-a-document-to-a-custom-class)
  - [Delete a document](#delete-a-document)
  - [Query a collection](#query-a-collection)
    - [Find single document by values](#find-single-document-by-values)
    - [Find all matching documents by values](#find-all-matching-documents-by-values)
    - [Find all documents in collection](#find-all-documents-in-collection)
    - [Advanced queries](#advanced-queries)
    - [Query operators](#query-operators)
  - [Join collections](#join-collections)
  - [Caching results](#caching-results)
  - [Index a collection](#index-a-collection)
  - [Delete a collection](#delete-a-collection)
  - [Collection transactions](#collection-transactions)
  - [Full text search](#full-text-search)
- [Documents](#documents)
  - [About Documents](#about-documents)
  - [Getting and setting document data](#getting-and-setting-document-data)
  - [Mapping document fields to objects](#mapping-document-fields-to-objects)
  - [Document Unique Id](#document-unique-id)
  - [Saving a document](#saving-a-document)
  - [Deleting a document](#deleting-a-document)
  - [Document validation](#document-validation)
- [Other info](#other-info)
  - [Symfony integration](#symfony-integration)
  - [Licensing](#licensing)
  - [Bugs, issues](#bugs-issues)
  - [Contact the author](#contact-the-author)
  
## About DocLite

DocLite is a powerful NoSQL document store for PHP built on top of SQLite. It uses the 
PHP PDO SQLite library to access a SQLite database and automatically manage 
documents organized in to named collections, which are stored as JSON.

DocLite takes advantage of the SQLite JSON1 extension (this is usually bundled in to 
the libsqlite included with your PHP distribution, so you probably already have it) 
to store, parse, index and query JSON documents - giving you the power and flexibility 
of a fully transactional and ACID compliant NoSQL solution, yet contained within the
local file system. No need for more complex systems like Mongo, CouchDB or Elasticsearch 
when your requirements are slim. No need for any external dependencies, just PHP with 
PDO SQLite enabled.

DocLite provides a simple, intuitive, flexible and powerful PHP library that you can 
learn, install and start using in minutes.

### Why DocLite?

DocLite lends itself well to a variety of use cases, including but not limited to:

- Agile development and rapid prototyping while your requirements are evolving.  
  
- Powerful, self-contained NoSQL database for small to medium websites or 
  applications, such as blogs, business website, CMS, CRM or forums.
  
- A fast and reliable cache for data retrieved from remote databases, APIs or 
  servers. Process your data in to documents, save in DocLite and easily query 
  and filter your data as needed.

- Robust, performant, ACID compliant replacement for weaker, slower, flat-file 
  data stores utilizing JSON, XML or YAML.
  
- Application database for web apps installed and run on a local environment.

- Database for microservices and middleware.

- Fast in-memory database for data processing or machine learning algorithms.

Broadly speaking, DocLite is suitable for the [same uses cases](https://www.sqlite.org/whentouse.html) 
as the underlying SQLite engine it is built on, but where you desire a NoSQL solution.

## Getting Started

<details>
<summary>System requirements</summary>

- PHP 7.4 or above

- With PDO SQLite enabled, built against libsqlite ≥ 3.18.0 with JSON1 extension.

(on most systems, if you're running PHP 7.4 you probably already meet the second 
requirement)
</details>

<details>
<summary>Installation</summary>

Install with [Composer](https://getcomposer.org/)

`composer require dwgebler/doclite`
</details>

<details>
<summary>Usage Overview</summary>

DocLite provides both a `FileDatabase` and `MemoryDatabase` implementation. 
To create or open an existing database, simply create a `Database` object, specifying the file path if using a `FileDatabase`.

If your `FileDatabase` does not exist, it will be created (ensure your script has the appropriate write permissions). 
This will include creating any parent directories as required.

If you specify an existing directory without a filename, a default filename `data.db` will be used.

```php
use Gebler\Doclite\{FileDatabase, MemoryDatabase};

// To create or open an existing file database.
$db = new FileDatabase('/path/to/db');

// To open an existing file database in read-only mode.
$db = new FileDatabase('/path/to/existing/db', true);

// To create a new in-memory database.
$db = new MemoryDatabase();
```

Once you have opened a database, you can obtain a document `Collection` which will be automatically created 
if it does not exist.

```php
$users = $db->collection("user"); 
```

The `Collection` object can then be used to retrieve, create and manipulate documents.

```php
// Create a new User in the collection
$user = $users->get();

// Get the automatically generated document ID
$id = $user->getId();

// Set properties by magic set* methods
$user->setUsername("dwgebler");
$user->setRole("admin");
$user->setPassword(password_hash("admin", \PASSWORD_DEFAULT));
$user->setCreated(new \DateTimeImmutable);

// Update the user in the collection
$user->save();

// Retrieve this user later on by ID
$user = $users->get($id);

// Or search for a user by any field
$user = $users->findOneBy(["username" => "dwgebler"]);
```

In the example above, `$user` is an instance of a DocLite `Document`, but you can also 
hydrate objects of your own custom classes from a collection.

```php
class CustomUser
{
    private $id;
    private $username;
    private $password;
    
    public function getId() {...}
    public function setId($id) {...}
    public function getUsername() {...}
    public function setUsername($username) {...}    
}

// Retrieve a previously created user and map the result on to a CustomUser object.
// You can also pass a null ID as the first parameter to create a new CustomUser.
$user = $users->get($id, CustomUser::class);

// $user is now an instance of CustomUser and can be saved through the Collection.
$users->save($user);
```

To learn more about the `Collection` object including how to query a document store, please read the full 
documentation below.
</details>

## The Database

DocLite is built on top of SQLite 3 and supports two types of database; file and memory. 
The corresponding classes are `FileDatabase` and `MemoryDatabase`.

### Creating a memory database

`MemoryDatabase` is stored in volatile memory and is therefore ephemeral for the lifetime 
of your application scripts. Its constructor takes optional parameters:

- a boolean flag indicating whether to enable full text search features (defaults to `false`) - this
  feature requires SQLite to have been compiled with the [FTS5 extension](https://www.sqlite.org/fts5.html).
- an integer representing the maximum connection timeout in seconds (defaults to `1`) which
  is how long the connection should wait if the underlying SQLite database is locked.

```php
use Gebler\Doclite\MemoryDatabase;

$db = new MemoryDatabase();

// With full text search enabled and a 2-second connection timeout
$db = new MemoryDatabase(true, 2); 
```

### Creating a file database

`FileDatabase` constructor takes one mandatory and then some optional parameters; 
only the file or directory path to a new or existing database is required. 

Optional parameters are:

- a boolean flag indicating whether the database should be opened in read-only mode, which defaults to `false`.
- a boolean flag indicating whether to enable full text search features (defaults to `false`) - this
feature requires SQLite to have been compiled with the [FTS5 extension](https://www.sqlite.org/fts5.html).
- an integer representing the maximum connection timeout in seconds (defaults to `1`) which
  is how long the connection should wait if the underlying SQLite database is locked.

The path supplied to `FileDatabase` can be a relative or absolute path which is any of:

- An existing directory with read and write access.
- A non-existent file in a directory with read-write access.
- An existing database in a directory with read-write or read-only access (read-only mode).
- A non-existing directory path which your script has permission to create.

If no file name is specified, a default file name `data.db` will be used for the underlying database.

```php
use Gebler\Doclite\FileDatabase;

// Open a new database
$db = new FileDatabase('./data/mydb.db');

// Open an existing database in read-only mode
$db = new FileDatabase('./data/mydb.db', true);

// Open a new database called data.db in existing directory /home/data
$db = new FileDatabase('/home/data');

// All options - path, read-only mode, full text search and connection timeout
$db = new FileDatabase('./data/mydb.db', false, true, 1);

// Or, in PHP 8, named parameters:
$db = new FileDatabase(path: './data/mydb.db', readOnly: true, ftsEnabled: true);
```

If you open a database in read-only mode, you will be able to retrieve documents from a collection, 
but you will not be able to save them or create new documents or collections. 
Attempting to do so will trigger an error.

It is good practice wrapping `FileDatabase` creation in a try-catch block. 
Initializing a `FileDatabase` may throw either an `IOException` (for errors relating to the file system) 
or a `DatabaseException` (for errors establishing the DB connection).

```php
use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\FileDatabase;

try {
  $db = new FileDatabase('/path/to/db');
} catch (IOException $e) {
    var_dump($e->getMessage());
} catch (DatabaseException $e) {
    var_dump($e->getMessage());
}
```

### Error Handling

DocLite primarily throws a `DatabaseException` when any error occurs. This is true across 
the `Database`, `Collection` and `Document` types. A `Database` exception will 
include a message, an error code (see below), any underlying system exception if there was one (so normal 
`Exception` behaviour up to this point), plus any SQL query which was being executed 
(DocLite hides these from you during normal operation, of course, as it is a NoSQL solution, 
but they are useful for filing bug reports!), and an array of any relevant parameters - these 
may be things like a document ID, the document data, etc.

```php
use Gebler\Doclite\Exception\DatabaseException;
...
try {
    $user->setUsername("dwgebler");
    $user->save();
} catch (DatabaseException $e) {
    var_dump($e->getMessage(), $e->getCode(), $e->getQuery(), $e->getParams());
}
```

A `DatabaseException` can occur on any `Database`, `Collection` or `Document` 
method which interacts with the underlying database.

Error codes are represented by public constants in the `DatabaseException` class.  
The full list of error codes are as follows:

| Constant                         | Meaning |
| -------------                    | ------------ |
| ERR_COLLECTION_IN_TRANSACTION    | Attempted to begin, rollback or commit on a collection while a transaction on a different collection was already in progress. |
| ERR_CONNECTION                   | Unable to connect to database  |
| ERR_NO_SQLITE                    | PDO SQLite extension is not installed |
| ERR_NO_JSON1                     | SQLite does not have the JSON1 extension installed |
| ERR_NO_FTS5                      | FTS5 extension not installed
| ERR_INVALID_COLLECTION           | Invalid collection name |
| ERR_MISSING_ID_FIELD             | Custom class for mapping a document does not have an ID field |
| ERR_INVALID_FIND_CRITERIA        | Attempted to find a document by non-scalar value |
| ERR_INVALID_ID_FIELD             | Specified unique ID field for custom class does not exist, nor does default |
| ERR_ID_CONFLICT                  | Multiple documents in the same collection have the same ID |
| ERR_CLASS_NOT_FOUND              | Custom class name being used for a document does not exist |
| ERR_INVALID_UUID                 | Attempted to get the timestamp from an invalid UUID |
| ERR_QUERY                        | Error executing SQL query |
| ERR_READ_ONLY_MODE               | Attempted a write operation on a read only database |
| ERR_INVALID_JSON_SCHEMA          | Attempted to import an invalid JSON schema |
| ERR_INVALID_DATA                 | Data does not match loaded JSON schema |
| ERR_MAPPING_DATA                 | Unable to map document to class |
| ERR_IMPORT_DATA                  | Error importing data |
| ERR_IN_TRANSACTION               | Attempting locking operation while in a transaction |
| ERR_INVALID_TABLE                | Attempting to access invalid table |

### Import and export data

DocLite can import data from and export data to JSON, YAML, XML and CSV files. 
For this purpose, the `Database` object provides two methods, `import()` and 
`export()`.

> :warning: Import or export operations on very large collections may exhaust
> memory. This feature will (probably) be improved and made more efficient for
> working with large data sets in future.
> 
> It is recommended to use JSON for exports you intend to reload in 
> to a DocLite database. Support for other formats is experimental.

#### Importing Data

The data you want to import can be organized either in to files, where each file 
represents a collection of multiple documents, or a directory where each 
sub-directory represents a collection and contains a number of files each 
representing a single document.

`import(string $path, string $format, int $mode)`

`format` can be any of `json`, `yaml`, `xml`, or `csv`. This should also 
match the extension of the filename(s) containing your data.

When using the `csv` format, the first line of a CSV file is assumed to be a
header line containing field names.

`mode` can be either of the constants `Database::MODE_IMPORT_COLLECTIONS` or 
`Database::MODE_IMPORT_DOCUMENTS`.

Collection names are inferred from the subdirectory or file names. For example,
`/path/to/collections/users.json` will import to the `users` collection, as will
a sub-directory `/path/to/collections/users/` when importing a collection from
multiple files.

```php
// Create a new, empty database
$db = new FileDatabase('/path/to/data.db');

// Import the contents of a directory where each file is a collection
$db->import('/path/to/collections', 'json', Database::IMPORT_COLLECTIONS);

// Import the contents of a directory where each sub directory is a collection 
// of files representing single documents.
$db->import('/path/to/collections', 'json', Database::IMPORT_DOCUMENTS);
```

When you import documents in to a collection, any documents which have a unique 
ID matching an existing document in the database will overwrite that document. 
Otherwise, new documents will be created for any documents with an unmatched or 
missing ID.

> :bulb: Each Collection import will be wrapped in a single transaction, so 
> imports are atomic per collection. You can also speed up bulk imports by 
> setting the advanced options (see below) to alter the database's 
> synchronization and rollback journal modes to something a little more 
> permissive, if you understand the implications of doing so.

#### Exporting data

You can export the entire contents of one or more collections. Much like importing 
data, you can choose whether DocLite should export this as one file per collection 
containing multiple documents, or one directory per collection with one file per 
document.

`export(string $path, string $format, int $mode, array $collections = [])`

`format` can be any of `json`, `yaml`, `xml`, or `csv`.

`mode` can be either of the constants `Database::MODE_EXPORT_COLLECTIONS` or
`Database::MODE_EXPORT_DOCUMENTS`.

`collections` can be a mix of strings of collection names and/or `Collection` 
objects. If this is empty, all collections in the database will be exported.

```php
// Export the entire database to one file per collection in the specified 
// output directory.
$db->export('/path/to/export', 'json', Database::EXPORT_COLLECTIONS);

// Export the entire database to a directory structure with one file per document.
$db->export('/path/to/export', 'json', Database::EXPORT_DOCUMENTS);

// Export only the "User" and "Person" collections.
// Assume Collection $persons = $db->get("Person");
$db->export(
    '/path/to/export', 
    'json',
    Database::EXPORT_COLLECTIONS,
    ['User', $persons]
);
```

> :warning: The XML standard imposes some restrictions on entity names. When 
> exporting to this format, DocLite will replace any invalid characters in 
> document fields with underscores. This means you may not be able to recreate 
> your document store exactly as it was should you subsequently import these 
> files in to a DocLite database.

### Advanced options

DocLite `Database` objects have a few methods for more advanced options.

#### Get DocLite version
```php
// Return the version of DocLite as a SemVer string, e.g. 1.0.0
$db->getVersion();
```

#### Optimize database
Call `$db->optimize()` to attempt database optimization. This function does 
not return anything, though can throw a `DatabaseException` if something goes 
wrong. Periodic optimization can reduce database file size and improve performance.

#### Set synchronization mode

The underlying SQLite sync mode can be set to one of the following constants in
the `Database` class.
See [SQLite documentation](https://www.sqlite.org/pragma.html#pragma_synchronous) 
for details of the implications of changing this value; disabling sync can lead 
to data loss in the event of a crash or power loss.

| Constant              | Meaning |
| --------              | ------- |
| MODE_SYNC_OFF         | Disable sync |
| MODE_SYNC_NORMAL      | Normal sync **Default setting** |
| MODE_SYNC_FULL        | Full sync |
| MODE_SYNC_EXTRA       | Extra sync |

Call `$db->setSyncMode(Database::MODE_CONSTANT)` to set the mode. 
For example to set Full Sync mode, call `$db->setSyncMode(Database::MODE_SYNC_FULL)`.

This function returns `true` on success or `false` on failure.

Call `$db->getSyncMode()` to get the current mode which can be compared to one 
of the constants. The return type is `int`.

#### Set rollback journal mode

The underlying SQLite management of the rollback journal can be set to one of 
the following constants. 
See [SQLite documentation](https://www.sqlite.org/pragma.html#pragma_journal_mode) 
for the implications of changing this value; disabling the rollback journal can 
lead to unintended data state.

> :warning: **Warning:** If you disable the rollback journal, transactions, atomic commits 
> and rollbacks will no longer work. The behaviour of transaction methods on a 
> collection in this mode is undefined and may lead to unpredictable results or 
> data corruption. You should therefore not use the 
> [transaction methods](#collection-transactions) in MODE_JOURNAL_NONE.

| Constant              | Meaning |
| --------              | ------- |
| MODE_JOURNAL_NONE     | Disable the rollback journal |
| MODE_JOURNAL_MEMORY   | In-memory rollback journal only |
| MODE_JOURNAL_WAL      | Use the write ahead log **Default setting** |
| MODE_JOURNAL_DELETE   | Delete rollback journal at end of each transaction |
| MODE_JOURNAL_TRUNCATE | Truncate rollback journal at end of each transaction |
| MODE_JOURNAL_PERSIST  | Prevent the rollback journal being deleted |

Call `$db->setJournalMode(Database::MODE_CONSTANT)` to set the mode.  
For example to set WAL mode, call `$db->setJournalMode(Database::MODE_JOURNAL_WAL)`.

This function returns `true` on success or `false` on failure.

Call `$db->getJournalMode()` to get the current  mode which can be compared to one 
of the constants. The return type is `string`.

## Collections

### About Collections

Collections are at the heart of DocLite. A `Collection` represents a named group of documents 
(for example, "Users") and is analogous to a table in a structured database.

> :bulb: **Note**: Collections are represented in the underlying SQLite database as tables. 
They must therefore obey a few rules:
> 
> - Collection names cannot start with `sqlite_`
> - Collection names cannot start with a number.
> - Collection names may contain only alphanumeric characters and underscores.
> - Collection names cannot be longer than 64 characters.

A `Collection` object is the means by which you create, find, update and delete documents.

Every document in a collection must have a unique ID. You can either supply this yourself, 
or one will be created for you when you first instantiate a document. Auto generated IDs 
take the form of a [v1 UUID](https://en.wikipedia.org/wiki/Universally_unique_identifier#Version_1_(date-time_and_MAC_address)) 
which includes a timestamp of when the document was first created.

### Obtain a collection

Collections are obtained from a `FileDatabase` or `MemoryDatabase` by calling the `collection` 
method. If the collection does not exist, it will be automatically created.

```php
$userCollection = $db->collection("Users");
```

### Create a document

Once you have a collection, create a new document by calling the collection's `get` method.

```php
$newUser = $userCollection->get();
```

### Save a document

By default, documents are returned as a DocLite `Document` object, which provides 
a `save()` method. You can also save a document of any type by calling `save()` 
on the collection with the document object as a parameter.

```php
// works for DocLite Document objects
$newUser->save();

// works for both DocLite documents and documents mapped to custom types
$userCollection->save($newUser);
```

### Retrieve a document

`get` can also be used to retrieve a document by its ID.

```php
$existingUser = $userCollection->get($id);
```

### Map a document to a custom class

By default, retrieving a document will return a DocLite `Document` object, which provides magic 
methods and properties for you to access and manipulate the document data. It is however also 
possible to create or retrieve a document as an object of any custom class, provided that class 
has either public properties or getter/setter methods for the document fields you wish to hydrate.

```php
// Get a user as an object of type CustomUser.
$user = $userCollection->get($id, CustomUser::class);
```

By default, DocLite will look for a property called `id` to populate with the document's unique id. 
If you want to use a different property on a custom class to store this id, for example because your class 
does not have an `id` property, or you are using it for something else, you can specify a custom ID property 
name as a third parameter to `get`.

```php
$user = $userCollection->get($id, CustomUser::class, 'databaseId');
```

Alternatively, you can add a public property or getter/setter to your class called `docliteId` 
and DocLite will automatically attempt to populate this instead in the absence of an `id` property. 

While the `Document` class provides a built-in `save()` method as a convenience to update a Document 
in storage, documents represented as your own custom classes must be saved through the collection object.

```php
$userCollection->save($user);
```

If you are using a custom property on your class to hold the document's unique ID, you 
should supply the ID as an additional parameter.

```php
$userCollection->save($user, $user->getDatabaseId());
```

Finally, when saving a document represented as a custom class, you can specify an optional 
third parameter to list any properties on the object you do _not_ want to be stored in the 
document. It is only necessary to do this either for properties you wish to be excluded which
are public / have getter/setter methods, or public get methods which do not represent properties.

```php
$userCollection->save($user, $user->getDatabaseId(), ['nonDatabaseField']);
```

### Delete a document

Much like `save()`, there is both a convenience `delete()` method on DocLite 
`Document` objects and a `deleteDocument(object $document)` method on the 
collection itself.

```php
// Works for DocLite Document objects.
$user->delete();

// works for both DocLite documents and documents mapped to custom types
$userCollection->deleteDocument($user);
```


### Query a collection

The `Collection` object provides a range of methods to find documents by arbitrary criteria.

#### Find single document by values
Find a single document where all keys match the specified values by calling `findOneBy`.

```php
$user = $userCollection->findOneBy([
    'role' => 'admin',
    'name' => 'Mr Administrator',
]);
```

`findOneBy` takes optional custom class name and custom class ID field parameters in the same 
manner as `get`.

```php
$user = $userCollection->findOneBy(['username' => 'admin'], CustomUser::class, 'databaseId');
```

If a document which matches the criteria cannot be found, `null` is returned.

#### Find all matching documents by values

The function `findAllBy` works the same way as `findOneBy` but will return a 
generator which you can iterate over, or convert to array via PHP's 
`iterator_to_array` function.

```php
foreach($userCollection->findAllBy(['active' => true]) as $user) {
   ...
}
```

#### Find all documents in collection

To retrieve all documents in a collection, use `findAll()`. Like the previous two functions, 
`findAll` can take an optional custom classname and ID property as parameters.

```php
foreach($userCollection->findAll() as $user) {
   ...
}
```

#### Advanced queries

DocLite includes a powerful query building mechanism to retrieve or delete all documents 
in a collection matching arbitrary criteria.

To build a query, use any combination of the `where()`, `and()`, `or()`, `limit()`,
`offset()` and `orderBy()` functions on the collection object, followed by a 
call to `fetch()`, `delete()` or `count()`.

You can also run nested queries to group clauses together via `union()` 
(for grouping clauses by `OR`) and `intersect()` (for grouping clauses by `AND`).

You can query a document to any depth by separating nested fields with a `.` dot character, 
you can also add square brackets `[]` to the end of a field which is a list to query 
all the values inside that list for any match.

The advanced queries APIs are better understood by example.

For the following code snippets, imagine each document of your user collection looks 
like the following data example, expressed here as YAML:

<details><summary>Sample user document</summary>

```yaml
username: adamjones
first_name: Adam
last_name: Jones
password: "$2y$10$LRS.0xUCJjWSmQuWMMRsuurZ0OGlU.NH7KYXsipzkfUa0YREEarj2"
address:
  street: 123 Fake Street
  area: Testville
  county: Testshire
  postcode: TE1 3ST
roles:
- USER
- EDITOR
telephone: "+441234567890"
registered: true
active: true
lastLogin: "2021-02-13T10:34:40+00:00"
email: adamjones@example.com
api_access:
  "/v1/pages/":
  - POST
  - GET
  "/v1/contributors/":
  - GET
```
</details>

Here are some example queries you could run against a collection of these documents.

<details><summary>Sample queries</summary>

```php
$users = $db->collection("Users");

$activeUsers = $users->where('active', '=', true)->fetch();

$gmailUsers = $users->where('email', 'ENDS', '@gmail.com')->fetch();

$registeredAndNotActiveUsers = $users->where('registered', '=', true)
                                     ->and('active', '=', false)
                                     ->fetch();

$usersInPostalArea = $users->where('address.postcode', 'STARTS', 'TE1')->fetch();

$usersWith123InPhone = $users->where('telephone', 'CONTAINS', '123')->fetch();

$usersWithNoNumbersInUsername = $users->where('username', 'MATCHES', '^[A-Za-z]*$')
                                       ->fetch();
                                       
$usersWithEditorRole = $users->where('roles[]', '=', 'EDITOR')->fetch();

$usersWithEditorOrAdminRole = $users->where('roles[]', '=', 'ADMIN')
                                    ->or('roles[]', '=', 'EDITOR')
                                    ->fetch();
                                    
$usersWithEditorAndAdminRole = $users->where('roles', '=', ['ADMIN', 'EDITOR']);                                    
                                    
$usersWhoHaveAtLeastOneRoleWhichIsNotAdmin = $users->where('roles[]', '!=', 'ADMIN')->fetch();

/* 
 * This next one is trickier. "roles" is a list of values in our document.
 * As we can see above, roles[] != ADMIN would return all users who
 * have at least one role in their list which is not ADMIN.
 * But this means if a user has roles ["USER","ADMIN"], they would
 * be matched.
 * So for users who do NOT have the ADMIN role at all, we can
 * quote the value "ADMIN" and ask for matches where the entire list of roles
 * (so no square brackets) does not contain this value.
*/
$usersDoNotHaveAdminRole = $users->where('roles', 'NOT CONTAINS', '"ADMIN"')->fetch();

$deleteAllUsersWithEditorRole = $users->where('roles[]', '=', 'EDITOR')->delete();

$first10UsersOrderedByFirstName = $users->orderBy('first_name', 'ASC')
                                        ->limit(10)
                                        ->fetch();                                                               

$next10UsersOrderedByFirstName = $users->orderBy('first_name', 'ASC')
                                       ->limit(10)
                                       ->offset(10)
                                       ->fetch();                                                               

// Use [] on any field which is a list to search within its sub-items
$usersWithPostAccessToPagesApi = $users->where(
    'api_access./v1/pages/[]', '=', 'POST')->fetch();                                     

$allUsersWithPostAccessToAnyApi = $users->where('api_access[]', '=', 'POST')
                                        ->fetch();

/**
 * Nested queries are also possible.
 * To get all users where 
 * (active=true and address.postcode matches '^[A-Za-z0-9 ]*$')
 * OR
 * (roles[] list contains "EDITOR" and lastLogin > 2021-01-30)
 */
$nestedUsers = $users->where('active', '=', true)
                     ->and('address.postcode', 'MATCHES', '^[A-Za-z0-9 ]*$')
                     ->union()
                     ->where('roles[]', '=', 'EDITOR')
                     ->and('lastLogin', '>', '2021-01-30')
                     ->fetch();
```
</details>

> :bulb: Like `findAllBy()`, the `fetch()` method returns a generator, not an 
> array. If you would like all results at once, replace `fetch()` with 
> `fetchArray()`.

> :bulb: The `fetch()` method on advanced queries can take a custom class name
> and custom ID field as optional parameters, just like the `findOneBy`, 
> `findAllBy` and `findAll()` methods.

> :bulb: Speed up complex queries by enabling DocLite's caching feature.

#### Query operators

Advanced queries support the following operators:

| Operator | Meaning             |
| ----     | -------             |
| =        | Equals, exact match |
| !=       | Not equals |
| <        | Less than |
| \>       | Greater than |
| <=        | Less than or equal |
| \>=       | Greater than or equal |
| STARTS   | Text starts with |
| NOT STARTS  | Text does not start with |
| ENDS     | Text ends with |
| NOT ENDS     | Text does not end with |
| CONTAINS | Text contains |
| NOT CONTAINS | Text does not contain |
| MATCHES  | Text regular expression match |
| NOT MATCHES  | Text negative regular expression match |
| EMPTY | Has no value, null |
| NOT EMPTY | Has any value, not null |

### Join Collections

It is possible to join a collection to one or more other collections when running a query, 
to include matching results from these collections in the documents returned. This works much 
like a foreign key in a relational database.

For example, if you have a `users` collection and a `comments` collection, where some documents in 
`comments` contain a field `user_id`. You can query `users` and join on `comments`, such that any documents 
matching in `comments` for the same user ID will be included in the `users` document, under a field called `comments`.

```php
/**
 * Imagine a user document like:
 * {"__id":"1", "name":"John Smith"}
 * 
 * and a corresponding comments document like:
 * {"__id":"5", "user_id": "1", "comment":"Hello world!"} 
 * 
 * You can query the users collection with a join to retrieve an aggregated document like this:
 * {"__id":"1", "name":"John Smith", "comments":[{{"__id":"5", "comment":"Hello world!"}}]}
 */
$users = $db->collection('Users');
$comments = $db->collection("Comments");
$users->where('__id', '=', '1')->join($comments, 'user_id', '__id')->fetchArray();
```

The `Collection::join` method takes the collection to join as the first parameter, the name of the 
document field in that collection to use as a foreign key as the second parameter, and 
the corresponding field in documents in the joining collection (e.g. Users) to match against.

The above example therefore is looking for documents in `Comments` where the field `user_id` matches the 
field `__id` in Users.

As `join` is part of the standard query building interface on a `Collection`, you can combine with other 
query operators such as `where`, `and` etc. or other joins.

### Caching results

DocLite can cache the results of queries to speed up retrieval of complex result sets. 
For very simple queries, however, this may provide no benefit or even incur a small 
performance penalty, so you should only turn it on if you need to.

To turn on caching for a collection, call the collection object's 
`enableCache()` method.

Likewise, you can disable caching by calling `disableCache()`.

Cache results are valid for the cache lifetime, which defaults to 60 seconds. You can 
change the cache validity period by calling `setCacheLifetime($seconds)`. A cache 
lifetime of zero means cached results will never expire.

You can manually flush the cache by calling `clearCache()`.

```php
$userCollection->enableCache();

// Set the cache validity period to 1 hour.
$userCollection->setCacheLifetime(3600);

$userCollection->disableCache();

$userCollection->clearCache();
```

Finally, the `Database` object can be set to automatically prune expired 
entries whenever the cache is queried. This behaviour is disabled by default; 
to enable auto-pruning, call `enableCacheAutoPrune()` on the database object.

```php
$db->enableCacheAutoPrune();
```

> :bulb: For complex queries, the cache is very fast. If you are running a large 
> number of complex queries on a large data set and these queries are likely to 
> be repeated without the data changing in storage for the lifetime of the cache, 
> it is a good idea to make use of DocLite's caching.

### Index a collection

It is possible to build indexes on any document fields inside a collection to 
speed up queries against that field.

When you create a collection, an index is automatically added for the internal
ID field. To add a custom index, call the `addIndex` method with the name of a 
document field.

```php
$userCollection->addIndex('email');
```

To add a single index on multiple fields (as per a multicolumn index), 
simply call `addIndex` with the additional field names as separate 
parameters.

```php
$userCollection->addIndex('first_name', 'last_name');
```

> :bulb: **Note:** indexes are an advanced feature which work the same way they do 
in any other SQLite database, the only difference being they are created 
on document fields rather than a table column. Poorly chosen indexes may 
provide no benefit or even slow down queries.

### Delete a collection

To delete all documents in a collection entirely, call the `deleteAll()` method.

```php
$userCollection->deleteAll();
```

### Collection transactions

It is possible to wrap a sequence of database operations inside a transaction. 
To do this, use the `Collection`'s `beginTransaction()`, `commit()` and 
`rollback()` methods.

```php
$collection = $db->collection("Users");
$collection->beginTransaction();

// ...do some stuff, insert a bunch of records or whatever...

// commit the results and end the transaction
$collection->commit();

// or rollback the changes and end the transaction
$collection->rollback();
```

### Full text search

DocLite is able to build powerful full text indexes against collections to allow you to 
search and produce a list of documents, ordered by relevance, where specified fields match some text or phrase.

Full text search capability requires your PHP's `libsqlite` to be built with the FTS5 extension. 
Just like the JSON1 extension, this is usually bundled in to the standard distribution so you probably already have it.

To search a collection, ensure you have initialized your `Database` with the full text parameter set to `true` to enable 
this feature, then simply call the `search()` method on any collection, with the search phrase followed by an array of
the names of any document fields you wish to search against.

```php
$path = '/path/to/db';
$readOnly = false;
$ftsEnabled = true;
$timeout = 1;
$db = new FileDatabase($path, $readOnly, $ftsEnabled, $timeout);
$blogPosts = $db->collection("posts");
$results = $blogPosts->search('apache', ['title', 'summary', 'content']);
```

Results are automatically ordered by relevance. 

> :bulb: DocLite will intelligently manage your full text indexes to keep your database optimized.
> When you call `search()`, if there is no index for the set of fields you are searching on, it will be created automatically on the first search.
> If you later call `search()` on a superset of fields for an existing index, the original index 
> will be destroyed and a new, larger index encompassing all searched fields created. This is so DocLite can use the smallest possible index for _all_ the fields you wish to search against.
> 
> On small collections, this process is so fast you may not see an impact. If, however, you have a very
> large collection, the recommendation is to create your full text indexes by calling `search()` once from a 
> separate script, so that when your application first runs and calls `search()`, the relevant indexes already exist.

Because `search()` is part of the standard query fetching interface on a collection (same as `fetch()` and `count()`), it can be preceded by 
normal query filters using `where()`, `and()` etc. Similar to `fetch()`, the `search()` method returns 
a generator. You can convert the results to an array by using PHP's `iterator_to_array()` function.

## Documents

### About Documents

Documents are a variadic structured store of data in the form of key-value pairs, 
stored in the database as JSON; that is, each document inside a collection can have its own freeform
structure. It does not matter whether this matches the structure of any other documents 
in the same collection, that is up to how your application decides to use DocLite.

A document will by default be represented by the DocLite `Document` class, however it is also 
possible to create or retrieve documents from a collection and map them on to your own classes. 
See the Collection documentation for more details on this.

### Getting and setting document data

The `Document` class provides magic get and set methods and property accessors for arbitrary document 
keys. That is, once you have a `Document` from a `Collection`, you can set or read any properties 
you like by either method:

```php
$users = $db->collection("Users");
// Create a new Document with an auto generated UUID.
$user = $db->get();
// Create a new property called username via a magic setter.
$user->setUsername('dwgebler');
// Create a new property called password via a magic property.
$user->password = password_hash("admin", \PASSWORD_DEFAULT);
// Read the username property via a magic property.
echo $user->username;
// Read the password property via a magic getter.
echo $user->getPassword();
// Properties can contain scalar values, arrays, or even other Documents and
// custom objects.
$user->setRoles(['user', 'admin']);
```

There is one small semantic difference between the magic method and property access 
techniques; when using magic methods, the property names are converted from camelCase 
to snake_case, whereas direct property access is literal e.g.

```php
// setter uses camel case
$user->setFirstName('Dave');

// but the corresponding property created will be lower cased and snake_cased
echo $user->first_name;

// if you want a key in a document to be case sensitive, set it as a property only
$user->FirstName = 'Dave';

// you should now use the property access to retrieve its value later on
echo $user->FirstName;

// This will not work and will raise a ValueError on getFooBar(),
// because the method call will look for a property called foo_bar
$user->FooBar = 'baz';
$user->getFooBar();
```

The `Document` class also provides two further methods, `getValue` and `setValue`, 
to query a document by nested keys using a path in dot `.` notation. These 
methods can also be used to get or set fields with names which can't be 
expressed through magic set methods or properties.

`getValue()` raises a `ValueError` if the specified path cannot be found.

`setValue()` will automatically create any parent properties on a nested path.

```php
// This is the same as:
// $address = $user->getAddress();
// $postcode = $address['postcode'];
$user->getValue('address.postcode');

// Assume "roles" is a list, this will return an array
$user->getValue('roles');

// Retrieve the first role
$user->getValue('roles.0');

// Assume api_access is a dictionary of keys mapped to lists.
// This will return the list of data under the /v1/users/ key
// as an array.
$access = $user->getValue('api_access./v1/users/');
if (!in_array('POST', $access)) { ... }

// If address does not exist, it will be created with postcode as a key.
$user->setValue('address.postcode', 'TE1 3ST');

// Or set a value with special characters in the name:
$user->setValue('api_access./v1/users/', ['GET', 'POST']);
```

> :bulb: The values of document fields are arbitrary. Scalar values, arrays and 
> even objects of custom classes can all be stored in a document.

### Mapping document fields to objects

If you've retrieved a document as a default `Document` object, it is still 
possible to map document fields which represent custom objects to custom classes. 
To do this, use the `Document`'s `map()` method and pass it a field name (which 
can use the nested dot `.` notation as described above), along with either a 
class name or existing object instance if you wish to populate an existing object.

Consider you have the following custom class in your application:

<details><summary>Sample class</summary>

```php
class Person
{
    private $id;

    private $firstName;

    private $lastName;

    private $address = [];

    private $postcode;

    private $dateOfBirth;

    private $identityVerified;

    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }    

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getAddress(): ?array
    {
        return $this->address;
    }

    public function setAddress(array $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function setPostcode(string $postcode): self
    {
        $this->postcode = $postcode;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(\DateTimeImmutable $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    public function getIdentityVerified(): ?bool
    {
        return $this->identityVerified;
    }

    public function setIdentityVerified(bool $identityVerified): self
    {
        $this->identityVerified = $identityVerified;
        return $this;
    }
}
```

</details>

And a `User` document with the following structure:

<details><summary>Sample document</summary>

```yaml
__id: b83e319a-7887-11eb-8deb-b9e03d2e720d
username: daniel_johnson1
active: false
roles:
- CONTRIBUTOR
- AUTHOR
telephone: "+441254220959364"
password: "$2y$10$y8P2Cjph1F.iIc.s2j9aM.GW9qy8aOMeEfzDulQox465mgBJF.pPG"
person:
  firstName: Daniel
  lastName: Johnson
  address:
    house: '123'
    street: Test Road
    city: Testville
    Country: Testshire
  postcode: "TE1 3ST"
  dateOfBirth: '1980-03-23T00:00:00+00:00'
  identityVerified: true
```

</details>

When you initially retrieve the `Document`, the `person` key will contain an 
array. But you can map this to your `Person` class as follows:

```php
$user = $collection->get("b83e319a-7887-11eb-8deb-b9e03d2e720d");
$user->map('person', Person::class);

// $user->getPerson() now returns a Person object.

// Or you can map to an existing Person object.
$person = new Person();
$user->map('person', $person);
```

### Document Unique Id

Every document in the same collection must have a unique ID. 

By default, when you create a new document, an ID is generated for you as a
v1 UUID.

You can get or set a `Document` ID with the `getId()` and `setId(string $id)` 
methods.

> :bulb: **Note:** Changing a `Document` ID essentially treats it as a different document, i.e. 
providing a new unique ID will result in a new document being inserted in to your database 
when you save it. Likewise changing a document's ID to the ID of another document in the 
collection will cause that document to be overwritten.

If the ID was auto generated, you can obtain a `DateTimeImmutable` representing the 
document's time of creation by calling its `getTime()` method:

```php
$users = $db->collection("Users");
// Create a new Document with an auto generated UUID.
$user = $users->get();
// $date is a \DateTimeImmutable
$date = $user->getTime();
echo $date->format('d m Y H:i');
```

If you don't want to use an auto-generated ID for a new document, simply pass in your 
own ID to the collection's `get()` method. As long as the ID does not match any document 
in the collection's database storage, a new document will be created. Document IDs are 
strings.

```php
$users = $db->collection("Users");
// Create a new Document with a custom ID.
// If this ID already exists in the Users collection, that document will be returned.
$user = $users->get("user_3815");
```

### Saving a document

Documents represented as a DocLite `Document` object provide a convenience method to 
save the document to its collection. To save a `Document` in storage, call `save()`.

```php
$users = $db->collection("Users");
$user = $users->get();
$user->setUsername("admin");
$user->save();
```

If a document has been mapped on to a custom class, you will need to save it through 
its collection instead.

```php
$users = $db->collection("Users");
// Create a new document with an automatically generated UUID and
// retrieved as an object of type CustomUser.
$user = $users->get(null, CustomUser::class);
$user->setUsername("admin");
$users->save($user);
```

### Deleting a document

Documents represented as a DocLite `Document` object provide a convenience method to
delete the document from its collection. To delete a `Document` in storage, 
call `delete()`.

```php
$users = $db->collection("Users");
$user = $users->get("12345");
$user->delete();
```

If a document has been mapped on to a custom class, you will need to delete it 
through its collection instead.

```php
$users = $db->collection("Users");
// Create a new document with an automatically generated UUID and
// retrieved as an object of type CustomUser.
$user = $users->get("12345", CustomUser::class);
$users->deleteDocument($user);
```

### Document validation

It is possible to add [JSON Schema](https://json-schema.org/) validation to a `Document` via the `addJsonSchema()` 
method. This takes a single string parameter of a valid JSON schema. If the schema 
cannot be validated, a `DatabaseException` will be thrown.

```php
$user->addJsonSchema(file_get_contents('schema.json'));
```

Once you have loaded a schema, every time you set a document property or try to save 
the document, the document data will be validated against your schema. If the data fails 
to validate, a `DatabaseException` will be thrown.

You can also manually validate at any time by calling `validateJsonSchema()`.

```php
$user->addJsonSchema(file_get_contents('schema.json'));
try {
    $user->validateJsonSchema();
    // This will automatically call validateJsonSchema() anyway.
    $user->save();
    // As will this.
    $user->setUsername("foobar");
} catch (DatabaseException $e) {
    $params = $e->getParams();
    $error = $params['error'];
    echo "Document failed to validate against JSON Schema because:\n".$error;
}
```

Finally, you can unload a JSON Schema and remove the validaton by calling 
`removeJsonSchema()`.

```php
$user->removeJsonSchema();
```

## Other info

### Symfony integration

Although there is not a specific integration with the Symfony framework, it's 
trivial to inject DocLite as a service in to any Symfony application. Simply 
install DocLite via Composer as an app dependency, then modify your 
`services.yaml` as per the following example.

```yaml
    app.filedatabase:
        class: Gebler\Doclite\FileDatabase
        arguments:
            $path: "../var/data/app.db"
            $readOnly: false
    app.memorydatabase:
        class: Gebler\Doclite\MemoryDatabase

    Gebler\Doclite\DatabaseInterface: '@app.filedatabase'
    Gebler\Doclite\DatabaseInterface $memoryDb: '@app.memorydatabase'
```

You can now typehint a `DatabaseInterface` like any other service, using the 
alias `$memoryDb` as the parameter name if you'd like a `MemoryDatabase`.

### Licensing

DocLite is available under the MIT license as open source software. 

If you use DocLite and find it useful, I am very grateful for any support 
towards its future development. 

[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=info%40doclite.co.uk&item_name=DocLite+PHP+library&currency_code=GBP)

### Bugs, issues

Please raise an issue on the project GitHub if you encounter any problems. I am 
always interested in improving the software.

### Contact the author

You can email me on info@doclite.co.uk
