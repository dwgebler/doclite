<?php

declare(strict_types=1);

namespace Gebler\Doclite;

interface QueryBuilderInterface
{
    /**
     * Join OR; create a new condition group selected by OR
     * @return QueryBuilderInterface
     */
    public function joinOr(): self;
    /**
     * Join AND; create a new condition group selected by AND
     * @return QueryBuilderInterface
     */
    public function joinAnd(): self;
    /**
     * Where clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return $this
     */
    public function where(string $field, string $condition, $value): self;
    /**
     * And clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return $this
     */
    public function and(string $field, string $condition, $value): self;
    /**
     * Or clause
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return $this
     */
    public function or(string $field, string $condition, $value): self;
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
     * Fetch query results.
     * @param string|null $className Custom class name
     * @param string|null $idField Custom class ID field
     * @return array
     */
    public function fetch(?string $className, ?string $idField): array;
    /**
     * Delete query results and return affected rows.
     * @return int
     */
    public function delete(): int;
}
