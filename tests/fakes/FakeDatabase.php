<?php
/**
 * FakeDatabase class. Non-verified fake implementing DB interface for unit tests.
 */

namespace Gebler\Doclite\Tests\fakes;

use DateTimeImmutable;
use Gebler\Doclite\Collection;
use Gebler\Doclite\Database;
use Gebler\Doclite\DatabaseInterface;
use Gebler\Doclite\Exception\DatabaseException;

/**
 * FakeDatabase
 */
class FakeDatabase implements DatabaseInterface
{

    private bool $readOnly = false;
    private bool $inTransaction = false;
    private string $transactionTable = '';

    private $data = [];
    private $cache = [];

    private $findResults= '';

    /**
     * @inheritDoc
     */
    public function collection(string $name): Collection
    {
        return new Collection($name, $this);
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Set read only status
     * @param bool $readOnly
     * @return $this
     */
    public function setReadOnly(bool $readOnly): self
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException(
                'Cannot begin transaction in read only mode',
                DatabaseException::ERR_READ_ONLY_MODE
            );
        }
        if ($this->inTransaction) {
            if ($this->transactionTable !== $name) {
                throw new DatabaseException(
                    'Transaction already in progress on collection ' . $name,
                    DatabaseException::ERR_COLLECTION_IN_TRANSACTION
                );
            }
            return false;
        }
        $this->inTransaction = true;
        $this->transactionTable = $name;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function commit(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot commit in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }
        if (!$this->inTransaction) {
            return false;
        }
        if ($this->transactionTable !== $name) {
            throw new DatabaseException(
                'Transaction already in progress on collection ' . $name,
                DatabaseException::ERR_COLLECTION_IN_TRANSACTION
            );
        }
        $this->inTransaction = false;
        $this->transactionTable = '';
        return true;
    }

    /**
     * @inheritDoc
     */
    public function rollback(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot rollback in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->inTransaction) {
            return false;
        }
        if ($this->transactionTable !== $name) {
            throw new DatabaseException(
                'Transaction already in progress on collection ' . $name,
                DatabaseException::ERR_COLLECTION_IN_TRANSACTION
            );
        }
        $this->inTransaction = false;
        $this->transactionTable = '';
        return true;
    }

    /**
     * @inheritDoc
     */
    public function replace(string $table, string $id, string $json): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot write in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }
        if (isset($this->data[$table])) {
            $this->data[$table][$id] = $json;
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $table, string $id): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot write in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }
        if (isset($this->data[$table][$id])) {
            unset($this->data[$table][$id]);
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function insert(string $table, string $json): bool
    {
        $data = json_decode($json, true);
        $id = $data[Database::ID_FIELD];
        return $this->replace($table, $id, $json);
    }

    /**
     * Set find results
     */
    public function setResults($data)
    {
        $this->findResults = $data;
    }

    /**
     * @inheritDoc
     */
    public function findAll(string $table, array $criteria): array
    {
        if (empty($criteria)) {
            return $this->data[$table];
        }
        return $this->findResults;
    }

    /**
     * @inheritDoc
     */
    public function find(string $table, array $criteria): string
    {
        return $this->findResults;
    }

    /**
     * @inheritDoc
     */
    public function getById(string $table, string $id): string
    {
        if (isset($this->data[$table][$id])) {
            return $this->data[$table][$id];
        }
        return '';
    }

    /**
     * @inheritDoc
     */
    public function tableExists(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @inheritDoc
     */
    public function createTable(string $name): bool
    {
        $this->data[$name] = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createCacheTable(string $name): bool
    {
        $this->data[$name] = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getCache(string $name, string $type, string $key, ?DateTimeImmutable $expiry): string
    {
        if (isset($this->cache[$name][$type][$key])) {
            if ($expiry < $this->cache[$name][$type][$key]['expiry']) {
                return $this->cache[$name][$type][$key]['data'];
            }
        }
        return '';
    }

    /**
     * @inheritDoc
     */
    public function setCache(string $name, string $type, string $key, string $cacheData, ?DateTimeImmutable $expiry): bool
    {
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = [];
        }
        if (!isset($this->cache[$name][$type])) {
            $this->cache[$name][$type] = [];
        }
        $this->cache[$name][$type][$key] = [
            'data' => $cacheData,
            'expiry' => $expiry,
        ];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flushTable(string $name): bool
    {
        if (isset($this->data[$name])) {
            $this->data[$name] = [];
        }
        if (isset($this->cache[$name])) {
            $this->cache[$name] = [];
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function createIndex(string $table, string ...$fields): bool
    {
        return true;
    }
}