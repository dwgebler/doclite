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
     * Return true when this collection uses JSONB blob storage.
     * @return bool
     * @throws DatabaseException
     */
    public function isJsonbStorage(): bool;

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

    /**
     * Find a document by criteria; if it exists, merge $data into it and save;
     * otherwise create a new document, populate with $data, and save. Returns
     * the resulting Document.
     *
     * @param array<string, scalar> $criteria
     * @param array<string, mixed>  $data
     * @throws DatabaseException
     */
    public function upsertBy(array $criteria, array $data): Document;

    /**
     * Create a partial index on the given JSON field(s) restricted to rows
     * matching the supplied SQL predicate.
     *
     * The predicate is appended verbatim to the CREATE INDEX statement — it
     * MUST be trusted, developer-authored SQL (the same trust contract as
     * executeDqlQuery). Never pass user-supplied input here.
     *
     * @param list<string> $fields JSON field names to index
     * @param string $where Predicate (without the leading WHERE keyword)
     * @param bool $unique Whether to create a unique partial index
     * @return bool
     * @throws DatabaseException
     */
    public function addPartialIndex(array $fields, string $where, bool $unique = false): bool;

    /**
     * Set a JSON schema that will be enforced on every document saved to this collection.
     * @param string $jsonSchema
     * @return self
     */
    public function setSchema(string $jsonSchema): self;

    /**
     * Remove the collection-level JSON schema.
     * @return self
     */
    public function removeSchema(): self;

    /**
     * Get the collection-level JSON schema string, or null if none is set.
     * @return ?string
     */
    public function getSchema(): ?string;
}
