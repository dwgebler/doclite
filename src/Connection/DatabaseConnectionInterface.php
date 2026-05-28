<?php

/**
 * DatabaseConnectionInterface.
 */

declare(strict_types=1);

namespace Gebler\Doclite\Connection;

use Gebler\Doclite\Exception\DatabaseException;
use Psr\Log\LoggerInterface;

/**
 * DatabaseConnectionInterface
 *
 * Describes the contract that Database depends on for low-level
 * SQLite operations. The only production implementation is
 * DatabaseConnection; the interface exists so that unit tests can
 * inject a test double without requiring the concrete final class to
 * be mockable.
 */
interface DatabaseConnectionInterface
{
    /**
     * Begin a transaction.
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * Rollback a transaction.
     * @return bool
     */
    public function rollback(): bool;

    /**
     * Commit a transaction.
     * @return bool
     */
    public function commit(): bool;

    /**
     * Execute a prepared statement and return affected rows.
     * @param string $query
     * @param mixed ...$params
     * @return int
     * @throws DatabaseException
     */
    public function executePrepared(string $query, ...$params): int;

    /**
     * Execute a statement and return affected rows.
     * @param string $query
     * @return int
     * @throws DatabaseException
     */
    public function exec(string $query): int;

    /**
     * Execute a query and return the first column of first row,
     * or null if no row was found.
     * @param string $query
     * @param mixed ...$params
     * @return ?string
     * @throws DatabaseException
     */
    public function valueQuery(string $query, ...$params): ?string;

    /**
     * Execute a query and return all results as an array.
     * @param string $query
     * @param mixed ...$params
     * @return array
     * @throws DatabaseException
     */
    public function queryAll(string $query, ...$params): array;

    /**
     * Execute a query and return results as a generator.
     * @param string $query
     * @param mixed ...$params
     * @return iterable
     * @throws DatabaseException
     */
    public function query(string $query, ...$params): iterable;

    /**
     * Clear internal query cache.
     */
    public function clearQueryCache(): void;

    /**
     * Enable full query logging.
     */
    public function enableQueryLogging(): void;

    /**
     * Disable full query logging.
     */
    public function disableQueryLogging(): void;

    /**
     * Enable slow query logging.
     */
    public function enableSlowQueryLogging(): void;

    /**
     * Disable slow query logging.
     */
    public function disableSlowQueryLogging(): void;

    /**
     * Set the logger.
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void;
}
