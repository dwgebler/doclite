<?php

declare(strict_types=1);

namespace Gebler\Doclite;

use DateTimeImmutable;
use Gebler\Doclite\Exception\DatabaseException;

/**
 * Database
 */
interface DatabaseInterface
{
    /**
     * Get or create a document collection.
     * @param string $name
     * @return Collection
     * @throws DatabaseException
     */
    public function collection(string $name): Collection;
    /**
     * Get FTS enabled.
     * @return bool
     */
    public function isFtsEnabled(): bool;
    /**
     * Scan the database for full text search tables matching a collection name and return a
     * dictionary of such table names converted to hash IDs and mapped to a list of indexed columns.
     * @param string $table
     * @return array
     * @throws DatabaseException
     */
    public function scanFtsTables(string $table): array;
    /**
     * Get read only mode.
     * @return bool
     */
    public function isReadOnly(): bool;
    /**
     * Begin a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function beginTransaction(string $name): bool;
    /**
     * Commit a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function commit(string $name): bool;
    /**
     * Rollback a transaction on a collection.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function rollback(string $name): bool;
    /**
     * Update a JSON record with the specified ID in the specified table,
     * or create it if it does not exist.
     * @param string $table
     * @param string $id
     * @param string $json
     * @return bool
     * @throws DatabaseException
     */
    public function replace(string $table, string $id, string $json): bool;
    /**
     * Delete a JSON record in the specified table.
     * @param string $table
     * @param string $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(string $table, string $id): bool;
    /**
     * Insert a JSON record in to the specified table.
     * @param string $table
     * @param string $json
     * @return bool
     * @throws DatabaseException
     */
    public function insert(string $table, string $json): bool;
    /**
     * Find all JSON records matching key=value criteria from specified table.
     * @param string $table
     * @param array $criteria
     * @return iterable A list of JSON strings
     * @throws DatabaseException
     */
    public function findAll(string $table, array $criteria): iterable;
    /**
     * Find a single JSON record by key=value criteria from the specified table.
     * @param string $table
     * @param array $criteria
     * @return string
     * @throws DatabaseException
     */
    public function find(string $table, array $criteria): string;
    /**
     * Retrieve a JSON record by Id from the specified table.
     * @param string $table
     * @param string $id
     * @return string
     * @throws DatabaseException
     */
    public function getById(string $table, string $id): string;
    /**
     * Check if a table exists.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function tableExists(string $name): bool;
    /**
     * Create a table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function createTable(string $name): bool;
    /**
     * Create a results cache table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function createCacheTable(string $name): bool;
    /**
     * Get records from cache table.
     * @param string $name
     * @param string $type
     * @param string $key
     * @param ?DateTimeImmutable $expiry
     * @return iterable
     * @throws DatabaseException
     */
    public function getCache(string $name, string $type, string $key, ?DateTimeImmutable $expiry): iterable;
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
    ): bool;
    /**
     * Delete all rows from a table.
     * @param string $name
     * @return bool
     * @throws DatabaseException
     */
    public function flushTable(string $name): bool;
    /**
     * Create an index on a table for the specified JSON field(s).
     * @param string $table
     * @param string ...$fields
     * @return bool
     * @throws DatabaseException
     */
    public function createIndex(string $table, string ...$fields): bool;
    /**
     * Create a full text index against the specified table and JSON fields.
     * @param string $table
     * @param string $ftsId A unique ID for this FTS table, comprising the hash of its field names
     * @param string ...$fields
     * @return bool
     * @throws DatabaseException
     */
    public function createFullTextIndex(string $table, string $ftsId, string ...$fields): bool;
}
