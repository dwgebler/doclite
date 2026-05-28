<?php

/**
 * CollectionInterface.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Exception\DatabaseException;

/**
 * CollectionInterface
 *
 * Describes the contract that QueryBuilder depends on when it holds a
 * reference to a collection.  The only production implementation is
 * Collection; the interface exists so that unit tests can inject a
 * test double without requiring the concrete final class to be mockable.
 */
interface CollectionInterface
{
    /**
     * Get the collection name.
     * @return string
     */
    public function getName(): string;

    /**
     * Execute a DML query and return the number of affected rows.
     * @param string $query
     * @param array $parameters
     * @return int
     * @throws DatabaseException
     */
    public function executeDmlQuery(string $query, array $parameters): int;

    /**
     * Execute a DQL query and fetch results as documents or raw data.
     * @param string $query
     * @param array $parameters
     * @param bool $fetchRaw
     * @param ?string $class
     * @param ?string $classIdProperty
     * @return iterable
     * @throws DatabaseException
     */
    public function executeDqlQuery(
        string $query,
        array $parameters,
        bool $fetchRaw = false,
        ?string $class = null,
        ?string $classIdProperty = null
    ): iterable;

    /**
     * Retrieve or create a full-text search index on document fields.
     * @param string ...$fields
     * @return string Hash of the indexed fields
     * @throws DatabaseException
     */
    public function getFullTextIndex(string ...$fields): string;

    /**
     * Insert many raw documents in a single transaction.
     *
     * Each row is a plain associative array; if it does not contain the
     * internal ID field, one is generated.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return int Number of rows inserted.
     * @throws DatabaseException
     */
    public function insertMany(iterable $rows): int;

    /**
     * Save a sequence of documents (Document instances or plain objects) in
     * a single transaction.
     *
     * @param iterable<object> $documents
     * @param list<string> $ignoreFields Field names to omit when serializing plain objects.
     * @return int Number of documents saved.
     * @throws DatabaseException
     */
    public function saveAll(iterable $documents, array $ignoreFields = []): int;
}
