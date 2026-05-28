<?php

declare(strict_types=1);

namespace Gebler\Doclite;

/**
 * Immutable page of results from a paginated query.
 *
 * @template T of object
 */
final class PaginatedResult
{
    public readonly int $pageCount;
    public readonly bool $hasMore;

    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
        $this->pageCount = $perPage > 0 ? (int)ceil($total / $perPage) : 0;
        $this->hasMore = $page < $this->pageCount;
    }
}
