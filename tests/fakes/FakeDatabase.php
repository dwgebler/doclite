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
use Psr\Log\LoggerInterface;

/**
 * FakeDatabase
 */
class FakeDatabase implements DatabaseInterface
{

    private bool $readOnly = false;
    private bool $ftsEnabled = false;
    private bool $inTransaction = false;
    private string $transactionTable = '';

    private $data = [];
    private $cache = [];
    private $fts = [];

    private $findResults;

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
     * Set FTS enabled
     * @param bool $enabled
     * @return $this
     */
    public function setFtsEnabled(bool $enabled): self
    {
        $this->ftsEnabled = $enabled;
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
    public function findAll(string $table, array $criteria): iterable
    {
        if (!empty($this->data[$table])) {
            foreach ($this->data[$table] as $i) {
                yield $i;
            }
        } else {
            if (!is_array($this->findResults)) {
                $this->findResults = [$this->findResults];
            }
            foreach ($this->findResults as $i) {
                yield $i;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function find(string $table, array $criteria): string
    {
        return (string)$this->findResults;
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
    public function getCache(string $name, string $type, string $key, ?DateTimeImmutable $expiry): iterable
    {
        $result = null;
        if (isset($this->cache[$name][$type][$key])) {
            if ($expiry < $this->cache[$name][$type][$key]['expiry']) {
                $result = $this->cache[$name][$type][$key]['data'];
            }
        }
        yield $result;
    }

    /**
     * @inheritDoc
     */
    public function setCache(
        string $name,
        string $type,
        string $key,
        string $dataKey,
        string $cacheData,
        ?DateTimeImmutable $expiry
    ): bool
    {
        if (!isset($this->cache[$name])) {
            $this->cache[$name] = [];
        }
        if (!isset($this->cache[$name][$type])) {
            $this->cache[$name][$type] = [];
        }
        $this->cache[$name][$type][$key] = [
            'data' => $cacheData,
            'dataKey' => $dataKey,
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
    public function createIndex(string $table, bool $unique, string ...$fields): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isFtsEnabled(): bool
    {
        return $this->ftsEnabled;
    }

    /**
     * @inheritDoc
     */
    public function scanFtsTables(string $table): array
    {
        $ftsTables = [];
        $table = strtolower($table);

        foreach ($this->fts as $key => $fields) {
            $hashId = str_replace('fts_' . $table . '_', '', $key);
            if (preg_match('/^fts_' . $table . '_([A-Za-z0-9])+$/', $key)) {
                $ftsTables[$hashId] = $fields;
            }
        }

        return $ftsTables;
    }

    /**
     * @inheritDoc
     */
    public function createFullTextIndex(string $table, string $ftsId, string ...$fields): bool
    {
        $innerTableName = strtolower($table) . '_' . $ftsId;
        $ftsTableName = strtolower('fts_' . $innerTableName);
        $this->fts[$ftsTableName] = $fields;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteFullTextIndex(string $table, string $hashId): bool
    {
        $innerTableName = strtolower($table) . '_' . $hashId;
        $ftsTableName = strtolower('fts_' . $innerTableName);
        if (isset($this->fts[$ftsTableName])) {
            unset($this->fts[$ftsTableName]);
        }
        return true;
    }


    public function setSyncMode(int $mode): bool
    {
        return true;
    }

    public function setJournalMode(string $mode): bool
    {
        return true;
    }

    public function getSyncMode(): int
    {
        return 1;
    }

    public function getJournalMode(): string
    {
        return 'WAL';
    }

    public function optimize(): bool
    {
        return true;
    }

    public function getVersion(): string
    {
        return '1.0';
    }

    public function enableQueryLogging(): void
    {
        // TODO: Implement enableQueryLogging() method.
    }

    public function disableQueryLogging(): void
    {
        // TODO: Implement disableQueryLogging() method.
    }

    public function enableSlowQueryLogging(): void
    {
        // TODO: Implement enableSlowQueryLogging() method.
    }

    public function disableSlowQueryLogging(): void
    {
        // TODO: Implement disableSlowQueryLogging() method.
    }

    public function setLogger(LoggerInterface $logger): void
    {
        // TODO: Implement setLogger() method.
    }
}