
<div align="right">
  <details>
    <summary >🌐 Language</summary>
    <div>
      <div align="center">
        <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=en">English</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=zh-CN">简体中文</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=zh-TW">繁體中文</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=ja">日本語</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=ko">한국어</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=hi">हिन्दी</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=th">ไทย</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=fr">Français</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=de">Deutsch</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=es">Español</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=it">Italiano</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=ru">Русский</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=pt">Português</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=nl">Nederlands</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=pl">Polski</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=ar">العربية</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=fa">فارسی</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=tr">Türkçe</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=vi">Tiếng Việt</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=id">Bahasa Indonesia</a>
        | <a href="https://openaitx.github.io/view.html?user=dwgebler&project=doclite&lang=as">অসমীয়া</
      </div>
    </div>
  </details>
</div>

# DocLite

A powerful PHP NoSQL document store built on top of SQLite.

[![CI](https://github.com/dwgebler/doclite/actions/workflows/ci.yml/badge.svg)](https://github.com/dwgebler/doclite/actions/workflows/ci.yml)

## About

DocLite is a NoSQL document store for PHP built on SQLite. It uses the PHP PDO SQLite library to automatically manage documents organised into named collections, stored as JSON. DocLite takes advantage of the SQLite JSON1 extension to store, parse, index, and query JSON documents — giving you the power of a fully transactional, ACID-compliant NoSQL solution contained entirely within the local filesystem.

DocLite lends itself to a variety of use cases: rapid prototyping, small-to-medium websites and applications, a fast local cache for remote API data, a robust replacement for flat-file stores, and a database for microservices or local web apps. Broadly, it suits the [same use cases as SQLite itself](https://www.sqlite.org/whentouse.html), but where a NoSQL document model is preferred.

## Install

```bash
composer require dwgebler/doclite
```

Requires PHP 8.1+ and the `pdo_sqlite` extension.

Upgrading from 1.x? See [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Quick start

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

## Documentation

- [Getting started](docs/getting-started.md) — installation, requirements, your first database
- [Collections & documents](docs/collections.md) — CRUD, transactions, schemas, custom class mapping
- [Queries](docs/queries.md) — query builder, joins, pagination, aggregation, indexing
- [Full-text search](docs/full-text-search.md) — FTS5-powered search
- [Advanced](docs/advanced.md) — import/export, journal modes, JSONB, PSR-16 cache adapter, logging
- [Symfony integration](docs/symfony.md) — wiring DocLite into a Symfony app

## License

MIT. See [LICENSE](LICENSE).

## Reporting bugs

Please raise an issue on [GitHub](https://github.com/dwgebler/doclite/issues).

## Reporting vulnerabilities

See [SECURITY.md](SECURITY.md).
