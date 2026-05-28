# Symfony integration

Although DocLite does not ship a dedicated Symfony bundle, it is straightforward to wire it in as a service.

## Installation

```bash
composer require dwgebler/doclite
```

## services.yaml

Add the following to your `config/services.yaml`:

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

## Usage in a service or controller

Type-hint `DatabaseInterface` in any constructor. Use the `$memoryDb` parameter name to inject the in-memory database:

```php
use Gebler\Doclite\DatabaseInterface;

class UserRepository
{
    public function __construct(
        private readonly DatabaseInterface $db,
    ) {}

    public function findByEmail(string $email): ?object
    {
        return $this->db->collection('users')->findOneBy(['email' => $email]);
    }
}
```

```php
use Gebler\Doclite\DatabaseInterface;

class CacheService
{
    public function __construct(
        private readonly DatabaseInterface $memoryDb,
    ) {}
}
```

## PSR-16 cache adapter

If you want to use DocLite as a PSR-16 cache store (for example, with Symfony's cache component), register `CollectionCacheAdapter` as a service:

```yaml
    Gebler\Doclite\Cache\CollectionCacheAdapter:
        arguments:
            $database: '@app.filedatabase'
```

See [Advanced — PSR-16 cache adapter](advanced.md#psr-16-cache-adapter) for full details.

---

See also:

- [Getting started](getting-started.md)
- [Collections & documents](collections.md)
- [Advanced](advanced.md)
