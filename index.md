# DocLite
A powerful PHP NoSQL database and document store built on top of SQLite.

[![Build Status](https://travis-ci.com/dwgebler/doclite.svg?token=uj4HfXm5wqJXVuPAd984&branch=master)](https://travis-ci.com/dwgebler/doclite)

## Table of contents

- [About DocLite](#about-doclite)
  - [Why DocLite?](#why-doclite)
- [Getting Started](#getting-started)
  
## About DocLite

DocLite is a powerful NoSQL document store for PHP built on top of SQLite, providing a robust, fast and ACID compliant alternative to flat-file databases like SleekDB. It uses the 
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

### System requirements

- PHP 7.4 or above

- With PDO SQLite enabled, built against libsqlite ≥ 3.7.0 with JSON1 extension.

(on most systems, if you're running PHP 7.4 you probably already meet the second 
requirement)

### Installation

Install with [Composer](https://getcomposer.org/)

`composer require dwgebler/doclite`

### Usage Overview

DocLite provides both a `FileDatabase` and `MemoryDatabase` implementation. 
To create or open an existing database, simply create a `Database` object, specifying the file path if using a `FileDatabase`.

If your `FileDatabase` does not exist, it will be created (ensure your script has the appropriate write permissions). 
This will include creating any parent directories as required.

If you specify an existing directory without a filename, a default filename `data.db` will be used.

```php
use Gebler\DocLite\{FileDatabase, MemoryDatabase};

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


For full documentation, please see the project [on GitHub](https://github.com/dwgebler/doclite)
