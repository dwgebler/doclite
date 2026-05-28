<?php

declare(strict_types=1);

namespace Gebler\Doclite\Internal;

use InvalidArgumentException;

/**
 * Bounded LRU cache. Designed to hold PDOStatement instances but tolerant of
 * any value. Eviction happens on put when count exceeds capacity. clear()
 * calls closeCursor() on any value that exposes it.
 *
 * @template T
 */
final class QueryCache
{
    /** @var array<string, T> */
    private array $entries = [];

    public function __construct(private readonly int $capacity = 256)
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException('QueryCache capacity must be positive');
        }
    }

    /**
     * @return T|null
     */
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }
        $value = $this->entries[$key];
        // Move to end of insertion order → MRU
        unset($this->entries[$key]);
        $this->entries[$key] = $value;
        return $value;
    }

    /**
     * @param T $value
     */
    public function put(string $key, mixed $value): void
    {
        if (array_key_exists($key, $this->entries)) {
            unset($this->entries[$key]);
        }
        $this->entries[$key] = $value;
        while (count($this->entries) > $this->capacity) {
            $first = array_key_first($this->entries);
            if ($first === null) {
                break;
            }
            unset($this->entries[$first]);
        }
    }

    public function clear(): void
    {
        foreach ($this->entries as $entry) {
            if (is_object($entry) && method_exists($entry, 'closeCursor')) {
                $entry->closeCursor();
            }
        }
        $this->entries = [];
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
