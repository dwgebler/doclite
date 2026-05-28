<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit\Cache;

use Gebler\Doclite\Cache\CollectionCacheAdapter;
use Gebler\Doclite\MemoryDatabase;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class CollectionCacheAdapterTest extends TestCase
{
    private MemoryDatabase $db;
    private CollectionCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->db = new MemoryDatabase();
        $this->cache = new CollectionCacheAdapter($this->db);
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('default', $this->cache->get('absent', 'default'));
    }

    public function testSetAndGet(): void
    {
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertSame('bar', $this->cache->get('foo'));
    }

    public function testRoundTripsArraysAndObjects(): void
    {
        $value = ['n' => 1, 'nested' => ['a', 'b']];
        $this->cache->set('arr', $value);
        $this->assertSame($value, $this->cache->get('arr'));
    }

    public function testHasReportsPresence(): void
    {
        $this->assertFalse($this->cache->has('foo'));
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->has('foo'));
    }

    public function testDelete(): void
    {
        $this->cache->set('foo', 'bar');
        $this->assertTrue($this->cache->delete('foo'));
        $this->assertFalse($this->cache->has('foo'));
    }

    public function testClear(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
    }

    public function testTtlAsIntExpires(): void
    {
        $this->cache->set('k', 'v', -1);
        $this->assertFalse($this->cache->has('k'));
    }

    public function testTtlAsDateInterval(): void
    {
        $this->cache->set('k', 'v', new \DateInterval('PT3600S'));
        $this->assertSame('v', $this->cache->get('k'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $result = $this->cache->getMultiple(['a', 'b', 'missing'], 'fallback');
        $items = is_array($result) ? $result : iterator_to_array($result);
        $this->assertSame(['a' => 1, 'b' => 2, 'missing' => 'fallback'], $items);
    }

    public function testSetMultipleAndDeleteMultiple(): void
    {
        $this->cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(1, $this->cache->get('a'));
        $this->cache->deleteMultiple(['a', 'b']);
        $this->assertFalse($this->cache->has('a'));
        $this->assertFalse($this->cache->has('b'));
        $this->assertSame(3, $this->cache->get('c'));
    }

    public function testRejectsInvalidKeyCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->set('bad key with spaces', 1);
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache->get('');
    }
}
