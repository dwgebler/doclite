<?php

/**
 * Database class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use DateTimeImmutable;
use Exception;
use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\FileSystem\FileSystemInterface;

use const ARRAY_FILTER_USE_KEY;
use const DIRECTORY_SEPARATOR;

/**
 * Database
 */
abstract class Database implements DatabaseInterface
{
    /**
     * Internal ID field.
     * @var string
     */
    public const ID_FIELD = '__id';
    /**
     * Pragma modes for sync behaviour
     * @var int
     */
    public const MODE_SYNC_OFF = 0;
    public const MODE_SYNC_NORMAL = 1;
    public const MODE_SYNC_FULL = 2;
    public const MODE_SYNC_EXTRA = 3;
    /**
     * Pragma modes for rollback journal behaviour
     * @var string
     */
    public const MODE_JOURNAL_NONE = 'OFF';
    public const MODE_JOURNAL_MEMORY = 'MEMORY';
    public const MODE_JOURNAL_WAL = 'WAL';
    public const MODE_JOURNAL_DELETE = 'DELETE';
    public const MODE_JOURNAL_TRUNCATE = 'TRUNCATE';
    public const MODE_JOURNAL_PERSIST = 'PERSIST';
    /**
     * Import/export modes
     * @var int
     */
    public const MODE_IMPORT_COLLECTIONS = 0;
    public const MODE_IMPORT_DOCUMENTS = 1;
    public const MODE_EXPORT_COLLECTIONS = 0;
    public const MODE_EXPORT_DOCUMENTS = 1;
    /**
     * Version number.
     * @var string
     */
    private const VERSION = '1.1.1';
    /**
     * DB connection
     * @var DatabaseConnection
     */
    protected DatabaseConnection $conn;
    /**
     * @var FileSystemInterface
     */
    protected FileSystemInterface $fileSystem;
    /**
     * @var bool
     */
    protected bool $cacheAutoPrune = false;
    /**
     * @var bool
     */
    protected bool $inTransaction = false;
    /**
     * @var ?string
     */
    protected ?string $transactionTable = null;
    /**
     * @var bool
     */
    protected bool $readOnly = false;
    /**
     * @var bool
     */
    protected bool $ftsEnabled = false;

    /**
     * Get product version
     * @return string
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Get FTS enabled
     * @return bool
     */
    public function isFtsEnabled(): bool
    {
        return $this->ftsEnabled;
    }

    /**
     * Get read only mode.
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Enable cache auto pruning.
     * @return self
     */
    public function enableCacheAutoPrune(): self
    {
        return $this->setCacheAutoPrune(true);
    }

    /**
     * Set cache auto pruning
     * @param bool
     * @return self
     */
    public function setCacheAutoPrune(bool $auto): self
    {
        $this->cacheAutoPrune = $auto;
        return $this;
    }

    /**
     * Get cache auto prune setting
     * @return bool
     */
    public function getCacheAutoPrune(): bool
    {
        return $this->cacheAutoPrune;
    }

    /**
     * Import collections in to database.
     * @param string $path
     * @param string $format json, yaml, xml or csv
     * @param int $mode One of the import/export mode constants
     * @return bool
     * @throws DatabaseException
     */
    public function import(string $path, string $format, int $mode): bool
    {
        $format = strtolower($format);
        if (!in_array($format, ['json', 'yaml', 'xml', 'csv'])) {
            return false;
        }

        if (
            $mode !== self::MODE_IMPORT_COLLECTIONS &&
            $mode !== self::MODE_IMPORT_DOCUMENTS
        ) {
            return false;
        }

        if ($mode === self::MODE_IMPORT_COLLECTIONS) {
            $files = $this->fileSystem->scanFiles($path, $format);
            foreach ($files as $collection) {
                $this->importCollectionFile($collection);
            }
        } else {
            $files = array_filter(
                $this->fileSystem->scanFiles($path, $format, true),
                fn($key) => !empty($key),
                ARRAY_FILTER_USE_KEY
            );
            $this->importDocumentFiles($files);
        }

        return true;
    }

    /**
     * Import documents in to a collection from a single file.
     * @param string $file Full path to file. Collection name will be
     *      inferred from file name.
     * @return void
     * @throws DatabaseException
     */
    private function importCollectionFile(string $file): void
    {
        $pathInfo = pathinfo($file);
        $rollback = $this->getJournalMode() !== self::MODE_JOURNAL_NONE;
        if ($rollback) {
            $this->beginTransaction($pathInfo['filename']);
        }
        try {
            $collection = $this->collection($pathInfo['filename']);
            $format = $pathInfo['extension'];
            $data = $this->fileSystem->read($file);
            $serializer = $collection->getSerializer();
            $items = $serializer->decode($data, $format);
            foreach ($items as $item) {
                if (!isset($item[self::ID_FIELD])) {
                    $item[self::ID_FIELD] = $collection->getUuid();
                }
                $document = new Document($item, $collection);
                $document->save();
                unset($document);
            }
            if ($rollback) {
                $this->commit($pathInfo['filename']);
            }
        } catch (Exception $e) {
            if ($rollback) {
                $this->rollback($pathInfo['filename']);
            }
            throw new DatabaseException(
                'Unable to import documents from collection',
                DatabaseException::ERR_IMPORT_DATA,
                $e,
                '',
                [
                    'error' => $e->getMessage(),
                    'collection' => $pathInfo['filename'],
                    'format' => $format ?? null,
                ]
            );
        }
    }

    /**
     * Get rollback journal mode.
     * @return string
     * @throws DatabaseException
     */
    public function getJournalMode(): string
    {
        return strtoupper($this->conn->valueQuery('PRAGMA journal_mode'));
    }

    /**
     * Begin a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
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
        if ($this->conn->beginTransaction()) {
            $this->inTransaction = true;
            $this->transactionTable = $name;
            return true;
        }
        return false;
    }

    /**
     * Get or create a document collection.
     * @param string $name
     * @return Collection
     * @throws DatabaseException
     */
    public function collection(string $name): Collection
    {
        return new Collection($name, $this);
    }

    /**
     * Commit a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
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
        if ($this->conn->commit()) {
            $this->inTransaction = false;
            $this->transactionTable = null;
            return true;
        }
        return false;
    }

    /**
     * Rollback a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
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
        if ($this->conn->rollback()) {
            $this->inTransaction = false;
            $this->transactionTable = null;
            return true;
        }
        return false;
    }

    /**
     * Import documents in to a collection from a set of files.
     * @param array $files Dictionary mapping of collection names
     *  to a list of absolute file pths.
     * @return void
     * @throws DatabaseException
     */
    private function importDocumentFiles(array $files): void
    {
        foreach ($files as $collectionName => $documentFiles) {
            $collection = $this->collection($collectionName);
            $rollback = $this->getJournalMode() !== self::MODE_JOURNAL_NONE;
            if ($rollback) {
                $this->beginTransaction($collectionName);
            }
            try {
                foreach ($documentFiles as $file) {
                    $pathInfo = pathinfo($file);
                    $format = $pathInfo['extension'];
                    $data = $this->fileSystem->read($file);
                    $serializer = $collection->getSerializer();
                    $item = $serializer->decode($data, $format);
                    if ($format === 'csv' && count($item) === 1) {
                        $item = $item[0];
                    }
                    if (!isset($item[self::ID_FIELD])) {
                        $item[self::ID_FIELD] = $collection->getUuid();
                    }
                    $document = new Document($item, $collection);
                    $document->save();
                    unset($document);
                }
                if ($rollback) {
                    $this->commit($collectionName);
                }
            } catch (Exception $e) {
                if ($rollback) {
                    $this->rollback($collectionName);
                }
                throw new DatabaseException(
                    'Unable to import documents from collection',
                    DatabaseException::ERR_IMPORT_DATA,
                    $e,
                    '',
                    [
                        'error' => $e->getMessage(),
                        'format' => $format ?? null,
                        'collection' => $collectionName,
                        'file' => $file ?? null,
                    ]
                );
            }
        }
    }

    /**
     * Export the database to flat files.
     * @param string $path Directory path to write files
     * @param string $format json, yaml, xml or csv
     * @param int $mode One of the export mode constants
     * @param array $collections Collection names and/or instances to export,
     *  defaults to all collections in database.
     * @return bool
     * @throws DatabaseException
     * @throws IOException
     */
    public function export(string $path, string $format, int $mode, array $collections = []): bool
    {
        if (!$this->fileSystem->isDirectory($path)) {
            $mkPath = $path . DIRECTORY_SEPARATOR . 'data';
            if (!$this->fileSystem->createPath($mkPath)) {
                return false;
            }
        }

        $format = strtolower($format);
        if (!in_array($format, ['json', 'yaml', 'xml', 'csv'])) {
            return false;
        }

        if (
            $mode !== self::MODE_EXPORT_COLLECTIONS &&
            $mode !== self::MODE_EXPORT_DOCUMENTS
        ) {
            return false;
        }

        if (empty($collections)) {
            $collections = array_column($this->conn->queryAll("SELECT name FROM sqlite_master WHERE type ='table' " .
                "AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'fts_%' AND name NOT LIKE '%_cache';"), 'name');
        }

        foreach ($collections as $i => $collection) {
            if (is_string($collection)) {
                $collections[$i] = $this->collection($collection);
            }
        }

        foreach ($collections as $collection) {
            $this->exportCollection($path, $format, $mode, $collection);
        }
        return true;
    }

    /**
     * Export a single collection to flat files.
     * @param string $path
     * @param string $format json, yaml, xml or csv
     * @param int $mode One of the export mode constants
     * @param Collection $collection
     * @return void
     * @throws DatabaseException
     * @throws IOException
     */
    private function exportCollection(string $path, string $format, int $mode, Collection $collection): void
    {
        $documents = $collection->findAll();
        $serializer = $collection->getSerializer();
        if ($mode === self::MODE_EXPORT_COLLECTIONS) {
            $documentGroup = [];
            foreach ($documents as $document) {
                $documentData = $document->getData();
                if ($format === 'xml') {
                    $documentData = $this->sanitizeXmlArrayKeys($documentData);
                }
                $documentGroup[] = $documentData;
            }

            $serialized = $serializer->serialize(
                $documentGroup,
                $format,
                ['yaml_inline' => 4, 'yaml_indent' => 0]
            );
            $filePath = $path . DIRECTORY_SEPARATOR .
                        $collection->getName() . '.' . $format;
            $this->fileSystem->addFile($filePath, FileSystemInterface::ATTR_READABLE |
                        FileSystemInterface::ATTR_WRITABLE, $serialized);
            return;
        }
        foreach ($documents as $document) {
            $mkPath = $path . DIRECTORY_SEPARATOR . $collection->getName() .
                DIRECTORY_SEPARATOR . 'data';
            $this->fileSystem->createPath($mkPath);
            $documentData = $document->getData();
            if ($format === 'xml') {
                $documentData = $this->sanitizeXmlArrayKeys($documentData);
            }
            $serialized = $serializer->serialize($documentData, $format);
            $filePath = $path . DIRECTORY_SEPARATOR .
                $collection->getName() . DIRECTORY_SEPARATOR .
                $document->getId() . '.' . $format;
            $this->fileSystem->addFile($filePath, FileSystemInterface::ATTR_READABLE |
                FileSystemInterface::ATTR_WRITABLE, $serialized);
        }
    }

    /**
     * Sanitize XML array keys by replacing invalid characters
     * with underscores. Recursive.
     * @param array $data
     * @return array
     */
    private function sanitizeXmlArrayKeys(array $data): array
    {
        $sanitized = [];
        foreach ($data as $k => $v) {
            if (is_string($k)) {
                $r = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $k);
            } else {
                $r = $k;
            }
            if (is_array($v)) {
                $sanitized[$r] = $this->sanitizeXmlArrayKeys($v);
            } else {
                $sanitized[$r] = $v;
            }
        }
        return $sanitized;
    }

    /**
     * Get synchronous mode.
     * @return int
     * @throws DatabaseException
     */
    public function getSyncMode(): int
    {
        return (int)$this->conn->valueQuery('PRAGMA synchronous');
    }

    /**
     * Set synchronous mode.
     * @param int $mode
     * @return bool
     * @throws DatabaseException
     */
    public function setSyncMode(int $mode): bool
    {
        if (
            in_array($mode, [
                self::MODE_SYNC_OFF,
                self::MODE_SYNC_NORMAL,
                self::MODE_SYNC_FULL,
                self::MODE_SYNC_EXTRA,
            ])
        ) {
            return $this->conn->exec(sprintf(
                'PRAGMA synchronous=%d',
                $mode
            )) === 0;
        }
        return false;
    }

    /**
     * Set rollback journal mode.
     * @param string $mode
     * @return bool
     * @throws DatabaseException
     */
    public function setJournalMode(string $mode): bool
    {
        if (
            in_array(
                $mode,
                [
                    self::MODE_JOURNAL_NONE,
                    self::MODE_JOURNAL_MEMORY,
                    self::MODE_JOURNAL_DELETE,
                    self::MODE_JOURNAL_PERSIST,
                    self::MODE_JOURNAL_TRUNCATE,
                    self::MODE_JOURNAL_WAL,
                ]
            )
        ) {
            return $this->conn->exec(sprintf(
                'PRAGMA journal_mode=%s',
                $mode
            )) === 0;
        }
        return false;
    }

    /**
     * Attempt to optimize database.
     * @return void
     * @throws DatabaseException
     */
    public function optimize()
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot optimize in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }
        if ($this->inTransaction) {
            throw new DatabaseException(
                sprintf('Cannot optimize when transaction in progress [%s]', $this->transactionTable),
                DatabaseException::ERR_IN_TRANSACTION
            );
        }
        $this->conn->clearQueryCache();
        $this->conn->exec('VACUUM;');
        $this->conn->exec('PRAGMA optimize;');
        $this->conn->exec('PRAGMA main.wal_checkpoint(TRUNCATE);');
    }

    /**
     * Update a JSON record with the specified ID in the specified table,
     * or create it if it does not exist.
     * @param string $table
     * @param string $id
     * @param string $json
     * @return bool
     * @throws DatabaseException
     */
    public function replace(string $table, string $id, string $json): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot write in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($table)) {
            return false;
        }
        if (!$this->inTransaction) {
            $this->conn->beginTransaction();
        }
        $query = sprintf('UPDATE "%s" SET json=json_patch(json, ?) ' .
            'WHERE json_extract(json,\'$.%s\') = ?', $table, self::ID_FIELD);
        $updated = $this->conn->executePrepared($query, $json, $id);
        if ($updated === 1) {
            if (!$this->inTransaction) {
                $this->conn->commit();
            }
            return true;
        } elseif ($updated === 0) {
            $query = sprintf('INSERT INTO "%s" (json) VALUES (json(?))', $table);
            $inserted = $this->conn->executePrepared($query, $json) === 1;
            if (!$this->inTransaction) {
                $this->conn->commit();
            }
            return $inserted;
        } else {
            if (!$this->inTransaction) {
                $this->conn->rollback();
            }
            throw new DatabaseException(
                'ID conflict; multiple rows would be updated',
                DatabaseException::ERR_ID_CONFLICT,
                null,
                "",
                ['id' => $id, 'rows' => $updated]
            );
        }
    }

    /**
     * Validate table name.
     * @param string $name
     * @return bool
     */
    private function isValidTableName(string $name): bool
    {
        if (strlen($name) < 1 || strlen($name) > 64) {
            return false;
        }
        if (strpos(strtolower($name), 'sqlite_') === 0) {
            return false;
        }
        if (!preg_match('/^[A-Za-z]{1}[A-Za-z0-9_]*$/', $name)) {
            return false;
        }
        return true;
    }

    /**
     * Delete a JSON record in the specified table.
     * @param string $table
     * @param string $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(string $table, string $id): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot delete in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($table)) {
            return false;
        }

        return $this->conn->executePrepared(
            sprintf('DELETE FROM "%s" WHERE json_extract(json,\'$.%s\') = ?', $table, self::ID_FIELD),
            $id
        ) === 1;
    }

    /**
     * Insert a JSON record in to the specified table.
     * @param string $table
     * @param string $json
     * @return bool
     * @throws DatabaseException
     */
    public function insert(string $table, string $json): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot insert in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($table)) {
            return false;
        }
        return $this->conn->executePrepared(sprintf('INSERT INTO "%s" (json) VALUES (json(?))', $table), $json) === 1;
    }

    /**
     * Find all JSON records matching key=value criteria from specified table.
     * @param string $table
     * @param array $criteria
     * @return iterable A list of JSON strings
     * @throws DatabaseException
     */
    public function findAll(string $table, array $criteria): iterable
    {
        if (count($criteria) === 0) {
            if (!$this->isValidTableName($table)) {
                throw new DatabaseException(
                    sprintf('Invalid collection name [%s]', $table),
                    DatabaseException::ERR_INVALID_COLLECTION
                );
            }

            $query = sprintf('SELECT json FROM "%s" ORDER BY ' .
                "json_extract(json, '$.%s') ASC", $table, self::ID_FIELD);
            $parameters = [];
        } else {
            list('query' => $query, 'parameters' => $parameters) =
                $this->buildFindQuery($table, $criteria);
        }

        $rows = $this->conn->query($query, ...$parameters);
        foreach ($rows as $row) {
            yield $row['json'];
        }
    }

    /**
     * Build a query with parameters for finding JSON records by key/value
     * pairs.
     * @param string $table
     * @param array $criteria
     * @return array
     * @throws DatabaseException
     */
    private function buildFindQuery(string $table, array $criteria): array
    {
        if (!$this->isValidTableName($table)) {
            throw new DatabaseException(
                sprintf('Invalid collection name [%s]', $table),
                DatabaseException::ERR_INVALID_COLLECTION
            );
        }

        if (
            count($criteria) !== count(array_filter($criteria, "is_scalar"))
        ) {
            throw new DatabaseException(
                'Can only find() by scalar values',
                DatabaseException::ERR_INVALID_FIND_CRITERIA,
                null,
                '',
                ['criteria' => $criteria]
            );
        }

        if (count($criteria) === 1) {
            $key = array_key_first($criteria);
            if (is_bool($criteria[$key])) {
                $criteria[$key] = (int)$criteria[$key];
            }
        }

        $criteriaFields = array_map(function ($key) {
            return '$.' . $key;
        }, array_keys($criteria));

        $placeholders = implode(',', array_fill(
            0,
            count($criteria),
            '?'
        ));
        $criteriaValues = array_values($criteria);
        $whereClause = 'json_extract(json, %2$s)';
        if (count($criteriaValues) === 1) {
            $whereClause = "json_array({$whereClause})";
        }

        $query = sprintf(
            'SELECT json FROM "%1$s" WHERE ' . $whereClause . ' = json_array(%2$s)',
            $table,
            $placeholders
        );
        $parameters = array_merge($criteriaFields, $criteriaValues);
        return ['query' => $query, 'parameters' => $parameters];
    }

    /**
     * Find a single JSON record by key=value criteria from the specified table.
     * @param string $table
     * @param array $criteria
     * @return string
     * @throws DatabaseException
     */
    public function find(string $table, array $criteria): string
    {
        if (count($criteria) === 0) {
            return '';
        }

        list('query' => $query, 'parameters' => $parameters) =
            $this->buildFindQuery($table, $criteria);
        return $this->conn->valueQuery($query, ...$parameters);
    }

    /**
     * Retrieve a JSON record by Id from the specified table.
     * @param string $table
     * @param string $id
     * @return string
     * @throws DatabaseException
     */
    public function getById(string $table, string $id): string
    {
        if (!$this->isValidTableName($table)) {
            throw new DatabaseException(
                sprintf('Invalid collection name [%s]', $table),
                DatabaseException::ERR_INVALID_COLLECTION
            );
        }

        return $this->conn->valueQuery(
            sprintf("SELECT json FROM %s WHERE json_extract(json,'$.%s') = ?", $table, self::ID_FIELD),
            $id
        );
    }

    /**
     * Check if a table exists.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function tableExists(string $name): bool
    {
        if (!$this->isValidTableName($name)) {
            throw new DatabaseException(
                sprintf('Invalid collection name [%s]', $name),
                DatabaseException::ERR_INVALID_TABLE
            );
        }
        return !empty($this->conn->queryAll("SELECT name FROM sqlite_master WHERE type='table' AND name=?;", $name));
    }

    /**
     * Create a table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function createTable(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot create table in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($name)) {
            return false;
        }
        return $this->conn->exec(sprintf('CREATE TABLE IF NOT EXISTS "%s" (json TEXT NOT NULL)', $name)) === 0;
    }

    /**
     * Create a results cache table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function createCacheTable(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot create table in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($name)) {
            return false;
        }
        $success = $this->conn->exec(sprintf('CREATE TABLE IF NOT EXISTS "%s" (
            "type" TEXT NOT NULL, 
            "key" TEXT NOT NULL, 
            "datakey" TEXT,
            "data" TEXT, 
            "expiry" TEXT)', $name)) === 0;
        $this->conn->exec(sprintf('CREATE INDEX idx_%1$s_key ON "%1$s" ("type","key")', $name));
        $this->conn->exec(sprintf('CREATE UNIQUE INDEX idx_%1$s_data_key ON "%1$s" ("type","key","datakey")', $name));
        return $success;
    }

    /**
     * Get records from cache table.
     * @param string $name
     * @param string $type
     * @param string $key
     * @param ?DateTimeImmutable $expiry
     * @return iterable
     * @throws DatabaseException
     */
    public function getCache(string $name, string $type, string $key, ?DateTimeImmutable $expiry): iterable
    {
        if (!$this->isValidTableName($name)) {
            throw new DatabaseException(
                sprintf('Invalid cache table name [%s]', $name),
                DatabaseException::ERR_INVALID_TABLE
            );
        }

        if ($this->cacheAutoPrune) {
            $this->conn->exec(sprintf('DELETE FROM "%s" WHERE expiry < datetime(\'now\')', $name));
        }

        $parameters = [$type, $key];
        $query = sprintf('SELECT "data" FROM "%s" WHERE "type" = ? AND "key" = ?', $name);
        if ($expiry) {
            $query .= ' AND "expiry" > ?';
            $parameters[] = $expiry->format('Y-m-d H:i:s.v');
        }

        $rows = $this->conn->query($query, ...$parameters);
        foreach ($rows as $row) {
            if (isset($row['data'])) {
                yield $row['data'];
            } else {
                yield;
            }
        }
    }

    /**
     * Write records to a cache table.
     * @param string $name
     * @param string $type
     * @param string $key
     * @param string $dataKey
     * @param string $cacheData
     * @param ?DateTimeImmutable $expiry
     * @return bool
     * @throws DatabaseException
     */
    public function setCache(
        string $name,
        string $type,
        string $key,
        string $dataKey,
        string $cacheData,
        ?DateTimeImmutable $expiry
    ): bool {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot write cache in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($name)) {
            return false;
        }

        $formattedExpiry = null;
        if ($expiry) {
            $formattedExpiry = $expiry->format('Y-m-d H:i:s.v');
        }

        $parameters = [$type, $key, $dataKey, $cacheData, $formattedExpiry];
        $query = sprintf('REPLACE INTO "%s" ("type", "key", "datakey", "data", "expiry") ' .
            'VALUES (?, ?, ?, ?, ?)', $name);
        return $this->conn->executePrepared($query, ...$parameters) === 1;
    }

    /**
     * Delete all rows from a table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function flushTable(string $name): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot flush table in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($name)) {
            return false;
        }
        return $this->conn->exec(sprintf('DELETE FROM "%s"', $name)) !== 0;
    }

    /**
     * Scan the database for full text search tables matching a collection name and return a
     * dictionary of such table names converted to hash IDs and mapped to a list of indexed columns.
     * @param string $table
     * @return array
     * @throws DatabaseException
     */
    public function scanFtsTables(string $table): array
    {
        if (!$this->isValidTableName($table)) {
            return [];
        }
        $ftsTables = [];
        $table = strtolower($table);

        $tables = array_column($this->conn->queryAll("SELECT name FROM sqlite_master WHERE type ='table' " .
            "AND name LIKE 'fts_{$table}_%'"), 'name');

        foreach ($tables as $fTable) {
            $hashId = str_replace('fts_' . $table . '_', '', $fTable);
            if (preg_match('/^fts_' . $table . '_([A-Za-z0-9])+$/', $fTable)) {
                $columns = array_column($this->conn->queryAll(sprintf("PRAGMA table_info('%s')", $fTable)), 'name');
                foreach ($columns as $column) {
                    $ftsTables[$hashId][] = str_replace($table . '_', '', $column);
                }
            }
        }

        return $ftsTables;
    }

    /**
     * Delete a full text index for a collection.
     * @param string $table
     * @param string $hashId
     * @return bool
     * @throws DatabaseException
     */
    public function deleteFullTextIndex(string $table, string $hashId): bool
    {
        if (!$this->ftsEnabled) {
            throw new DatabaseException('FTS not enabled', DatabaseException::ERR_NO_FTS5);
        }
        if ($this->readOnly) {
            throw new DatabaseException(
                'Cannot delete FT index in read only mode',
                DatabaseException::ERR_READ_ONLY_MODE
            );
        }
        if (!$this->isValidTableName($table)) {
            return false;
        }

        $innerTableName = strtolower($table) . '_' . $hashId;
        $ftsTable = 'fts_' . $innerTableName;
        $viewName = 'v_' . $innerTableName;
        $triggers = [
            $ftsTable . '_ai',
            $ftsTable . '_au',
            $ftsTable . '_ad',
        ];

        $this->conn->exec("DROP VIEW IF EXISTS {$viewName};");
        foreach ($triggers as $trigger) {
            $this->conn->exec("DROP TRIGGER IF EXISTS {$trigger};");
        }
        $this->conn->exec("DROP TABLE IF EXISTS {$ftsTable};");
        return true;
    }

    /**
     * Create a full text index against the specified table and JSON fields.
     * @param string $table
     * @param string $ftsId A unique ID for this FTS table, comprising the hash of its field names
     * @param string ...$fields
     * @return bool
     * @throws DatabaseException
     */
    public function createFullTextIndex(string $table, string $ftsId, string ...$fields): bool
    {
        if (!$this->ftsEnabled) {
            throw new DatabaseException('FTS not enabled', DatabaseException::ERR_NO_FTS5);
        }
        if ($this->readOnly) {
            throw new DatabaseException(
                'Cannot create FT index in read only mode',
                DatabaseException::ERR_READ_ONLY_MODE
            );
        }

        if (!$this->isValidTableName($table)) {
            return false;
        }

        $innerTableName = strtolower($table) . '_' . $ftsId;

        $viewName = 'v_' . $innerTableName;
        $ftsTableName = strtolower('fts_' . $innerTableName);

        $existingFts = (bool)$this->conn->valueQuery('SELECT COUNT(*) FROM sqlite_master WHERE ' .
            'type=\'table\' and name=?', $ftsTableName);

        if ($existingFts) {
            return true;
        }

        $fieldsMap = [];
        foreach ($fields as $field) {
            if (strlen($field) < 1 || strlen($field) > 64) {
                return false;
            }
            if (!preg_match('/^[A-Za-z0-9_]*$/', $field)) {
                return false;
            }
            $key = strtolower($table) . '_' . strtolower($field);
            $fieldsMap[$key]['field'] = sprintf("json_extract(json, '$.%s')", $field);
            $fieldsMap[$key]['trigger_new'] = sprintf("json_extract(new.json, '$.%s')", $field);
            $fieldsMap[$key]['trigger_old'] = sprintf("json_extract(old.json, '$.%s')", $field);
        }

        $fieldsList = [];
        foreach ($fieldsMap as $k => $v) {
            $fieldsList[] = $v['field'] . ' AS ' . $k;
        }
        $fieldsQuery = implode(',', $fieldsList);

        $viewCreated = $this->conn->exec(sprintf(
            "CREATE VIEW %s AS SELECT ROWID AS id, %s FROM %s;",
            $viewName,
            $fieldsQuery,
            $table
        )) === 0;
        if (!$viewCreated) {
            return false;
        }
        $fieldColumnList = implode(',', array_keys($fieldsMap));
        $ftsTableCreated = $this->conn->exec(sprintf(
            "CREATE VIRTUAL TABLE %s USING fts5(%s, content='%s', content_rowid='id');",
            $ftsTableName,
            $fieldColumnList,
            $viewName
        )) === 1;
        if (!$ftsTableCreated) {
            return false;
        }
        $this->conn->exec(sprintf('INSERT INTO %1$s(%1$s) VALUES(\'rebuild\')', $ftsTableName));

        $nTriggerColumnList = implode(',', array_column($fieldsMap, 'trigger_new'));
        $oTriggerColumnList = implode(',', array_column($fieldsMap, 'trigger_old'));

        $iTriggerTemplate = "CREATE TRIGGER {$ftsTableName}_ai AFTER INSERT ON $table BEGIN " .
            "INSERT INTO $ftsTableName (rowid, " . $fieldColumnList . ") VALUES (new.rowid, %s); END;";
        $iTriggerQuery = sprintf($iTriggerTemplate, $nTriggerColumnList);

        $dTriggerTemplate = "CREATE TRIGGER {$ftsTableName}_ad AFTER DELETE ON $table BEGIN " .
            "INSERT INTO $ftsTableName ($ftsTableName, rowid, " . $fieldColumnList . ") VALUES " .
            "('delete', old.rowid, %s); END;";
        $dTriggerQuery = sprintf($dTriggerTemplate, $oTriggerColumnList);

        $uTriggerTemplate = "CREATE TRIGGER {$ftsTableName}_au AFTER UPDATE ON $table BEGIN " .
            "INSERT INTO $ftsTableName ($ftsTableName, rowid, " . $fieldColumnList . ") VALUES " .
            "('delete', old.rowid, %s); " .
            "INSERT INTO $ftsTableName (rowid, " . $fieldColumnList . ") VALUES (new.rowid, %s); " .
            "END;";
        $uTriggerQuery = sprintf($uTriggerTemplate, $oTriggerColumnList, $nTriggerColumnList);

        $this->conn->exec($iTriggerQuery);
        $this->conn->exec($uTriggerQuery);
        $this->conn->exec($dTriggerQuery);

        return true;
    }

    /**
     * Create an index on a table for the specified JSON field(s).
     * @param string $table
     * @param string ...$fields
     * @return bool
     * @throws DatabaseException
     */
    public function createIndex(string $table, string ...$fields): bool
    {
        if ($this->readOnly) {
            throw new DatabaseException('Cannot create index in read only mode', DatabaseException::ERR_READ_ONLY_MODE);
        }

        if (!$this->isValidTableName($table)) {
            return false;
        }

        $fieldsMap = [];
        foreach ($fields as $field) {
            if (strlen($field) < 1 || strlen($field) > 64) {
                return false;
            }
            if (!preg_match('/^[A-Za-z0-9_]*$/', $field)) {
                return false;
            }
            $fieldsMap[] = sprintf("json_extract(json, '$.%s')", $field);
        }

        $fieldsQuery = implode(',', $fieldsMap);
        $indexName = 'idx_' . strtolower($table) . '_' .
            implode('_', array_map('strtolower', $fields));
        $existingIndex = (bool)$this->conn->valueQuery('SELECT COUNT(*) FROM sqlite_master WHERE ' .
            'type=\'index\' and name=?', $indexName);
        if (!$existingIndex) {
            return $this->conn->exec(sprintf("CREATE INDEX %s ON %s(%s)", $indexName, $table, $fieldsQuery)) === 0;
        }

        return true;
    }

    /**
     * Execute a DQL query and return the results as an array.
     * @param string $query
     * @param array $parameters
     * @return iterable
     * @throws DatabaseException
     */
    public function executeDqlQuery(string $query, array $parameters): iterable
    {
        $rows = $this->conn->query($query, ...$parameters);
        foreach ($rows as $row) {
            yield $row;
        }
    }

    /**
     * Execute a DML query and return the number of affected rows
     * @param string $query
     * @param array $parameters
     * @return int
     * @throws DatabaseException
     */
    public function executeDmlQuery(string $query, array $parameters): int
    {
        return $this->conn->executePrepared($query, ...$parameters);
    }
}
