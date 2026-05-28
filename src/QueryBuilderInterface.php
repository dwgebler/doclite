<?php

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\PaginatedResult;

interface QueryBuilderInterface
{
    /**
     * Union; create a new condition group selected by OR
     * @return QueryBuilderInterface
     */
    public function union(): self;
    /**
     * Intersect; create a new condition group selected by AND
     * @return QueryBuilderInterface
     */
    public function intersect(): self;
    /**
     * Where clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @param array $values Additional values for multi-value clauses
     * @return $this
     */
    public function where(string $field, string $condition, $value, ...$values): self;
    /**
     * And clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @param array $values Additional values for multi-value clauses
     * @return $this
     */
    public function and(string $field, string $condition, $value, ...$values): self;
    /**
     * Or clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @param array $values Additional values for multi-value clauses
     * @return $this
     */
    public function or(string $field, string $condition, $value, ...$values): self;
    /**
     * Limit clause. Null for no limit.
     * @param int|null $limit
     * @return $this
     */
    public function limit(?int $limit): self;
    /**
     * Offset clause. Null for no offset.
     * @param int|null $offset
     * @return $this
     */
    public function offset(?int $offset): self;
    /**
     * Order by clause. $direction must be ASC or DESC.
     * @param string $field
     * @param string $direction
     * @return $this
     */
    public function orderBy(string $field, string $direction): self;
    /**
     * Join a collection to another using a field as a foreign key.
     * @param Collection $collection Foreign collection to join
     * @param string $field The document field in the foreign collection to match against key
     * @param string $key The field in the joining collection to match against foreign field
     * @param bool $excludeField Flag indicating whether the foreign field should be excluded from the resultant fields.
     * @return $this
     */
    public function join(CollectionInterface $collection, string $field, string $key, bool $excludeField = false): self;
    /**
     * Search a full text index for a collection.
     * @param string $phrase The phrase to search
     * @param string[] $fields List of document fields to search
     * @param string|null $className Custom class name
     * @param string|null $idField Custom class ID field
     * @return iterable
     * @throws DatabaseException
     */
    public function search(
        string $phrase,
        array $fields,
        ?string $className = null,
        ?string $idField = null
    ): iterable;
    /**
     * Fetch query results.
     * @param string|null $className Custom class name
     * @param string|null $idField Custom class ID field
     * @return iterable
     * @throws DatabaseException
     */
    public function fetch(?string $className, ?string $idField): iterable;
    /**
     * Fetch as an array.
     * @param string|null $className Custom class name
     * @param string|null $idField Custom class ID field
     * @return array
     * @throws DatabaseException
     */
    public function fetchArray(?string $className = null, ?string $idField = null): array;
    /**
     * Delete query results and return affected rows.
     * @return int
     * @throws DatabaseException
     */
    public function delete(): int;
    /**
     * Count query results.
     * @throws DatabaseException
     * @return int
     */
    public function count(): int;

    /**
     * Execute a paginated query and return a PaginatedResult.
     *
     * @param int $page 1-based page number (clamped to at least 1)
     * @param int $perPage Page size (clamped to at least 1)
     * @param string|null $className Custom class name
     * @param string|null $idField Custom class ID field
     * @return PaginatedResult
     * @throws DatabaseException
     */
    public function paginate(int $page, int $perPage, ?string $className = null, ?string $idField = null): PaginatedResult;

    /**
     * Return the sum of a numeric field across all matching documents.
     * @param string $field
     * @return float 0.0 if no rows match
     * @throws DatabaseException
     */
    public function sum(string $field): float;

    /**
     * Return the average of a numeric field across all matching documents.
     * @param string $field
     * @return float 0.0 if no rows match
     * @throws DatabaseException
     */
    public function avg(string $field): float;

    /**
     * Return the minimum value of a field across all matching documents.
     * @param string $field
     * @return mixed null if no rows match
     * @throws DatabaseException
     */
    public function min(string $field): mixed;

    /**
     * Return the maximum value of a field across all matching documents.
     * @param string $field
     * @return mixed null if no rows match
     * @throws DatabaseException
     */
    public function max(string $field): mixed;

    /**
     * Group matching documents by a field and compute optional aggregates.
     *
     * @param string $field The field to group by.
     * @param array<string, string> $aggregates Map of alias => aggregate expression.
     *   Allowed forms: 'SUM(field)', 'AVG(field)', 'MIN(field)', 'MAX(field)', 'COUNT(*)'.
     * @return iterable<array<string, mixed>>
     * @throws \InvalidArgumentException if any aggregate expression is not in the allowed set.
     * @throws DatabaseException
     */
    public function groupBy(string $field, array $aggregates = []): iterable;
}
