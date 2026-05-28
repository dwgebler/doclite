<?php

declare(strict_types=1);

namespace Gebler\Doclite\Cache;

use DateInterval;
use DateTimeImmutable;
use Gebler\Doclite\DatabaseInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * PSR-16 CacheInterface adapter backed by a DocLite cache table.
 *
 * Construction creates the underlying table if it doesn't exist. The table
 * name defaults to `doclite_psr16` and can be overridden per-instance.
 */
final class CollectionCacheAdapter implements CacheInterface
{
    private const CACHE_TYPE = '__psr16';

    /**
     * Sentinel value used by has() to distinguish a missing key from a stored null.
     */
    private const MISS_SENTINEL = '__doclite_psr16_miss__';

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $cacheTableName = 'doclite_psr16',
        private readonly int $defaultTtlSeconds = 3600,
    ) {
        if (!$db->tableExists($cacheTableName)) {
            $db->createCacheTable($cacheTableName);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        $rows = $this->db->getCache(
            $this->cacheTableName,
            self::CACHE_TYPE,
            $key,
            new DateTimeImmutable()
        );
        foreach ($rows as $row) {
            if ($row === null || $row === '') {
                return $default;
            }
            $decoded = @unserialize($row);
            return ($decoded === false && $row !== serialize(false)) ? $default : $decoded;
        }
        return $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        $expiry = $this->resolveExpiry($ttl);
        return $this->db->setCache(
            $this->cacheTableName,
            self::CACHE_TYPE,
            $key,
            $key,
            serialize($value),
            $expiry
        );
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        $this->db->executeDmlQuery(
            sprintf('DELETE FROM "%s" WHERE "type" = ? AND "key" = ?', $this->cacheTableName),
            [self::CACHE_TYPE, $key]
        );
        return true;
    }

    public function clear(): bool
    {
        $this->db->executeDmlQuery(
            sprintf('DELETE FROM "%s" WHERE "type" = ?', $this->cacheTableName),
            [self::CACHE_TYPE]
        );
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get((string) $key, $default);
        }
        return $out;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            if (!$this->delete((string) $key)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function has(string $key): bool
    {
        return $this->get($key, self::MISS_SENTINEL) !== self::MISS_SENTINEL;
    }

    /**
     * @throws PsrInvalidArgumentException
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new class ('Cache key cannot be empty') extends \InvalidArgumentException implements PsrInvalidArgumentException {
            };
        }
        if (preg_match('/[{}()\\/\\\\@:\\s]/', $key) === 1) {
            $message = sprintf('Cache key "%s" contains reserved characters', $key);
            throw new class ($message) extends \InvalidArgumentException implements PsrInvalidArgumentException {
            };
        }
        if (strlen($key) > 64) {
            $message = sprintf('Cache key "%s" exceeds 64 characters', $key);
            throw new class ($message) extends \InvalidArgumentException implements PsrInvalidArgumentException {
            };
        }
    }

    private function resolveExpiry(null|int|DateInterval $ttl): ?DateTimeImmutable
    {
        if ($ttl === null) {
            return $this->defaultTtlSeconds > 0
                ? new DateTimeImmutable(sprintf('+%d seconds', $this->defaultTtlSeconds))
                : null;
        }
        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl);
        }
        // int: seconds from now; negative means already expired
        return new DateTimeImmutable(sprintf('%+d seconds', $ttl));
    }
}
