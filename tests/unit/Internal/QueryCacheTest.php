<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit\Internal;

use Gebler\Doclite\Internal\QueryCache;
use PHPUnit\Framework\TestCase;

class QueryCacheTest extends TestCase
{
    public function testReturnsNullForMissingKey(): void
    {
        $cache = new QueryCache(4);
        $this->assertNull($cache->get('absent'));
    }

    public function testStoresAndReturnsValue(): void
    {
        $cache = new QueryCache(4);
        $cache->put('a', 'A');
        $this->assertSame('A', $cache->get('a'));
    }

    public function testEvictsLeastRecentlyUsedAtCapacity(): void
    {
        $cache = new QueryCache(2);
        $cache->put('a', 'A');
        $cache->put('b', 'B');
        $cache->get('a'); // touch A → MRU
        $cache->put('c', 'C'); // should evict B
        $this->assertSame('A', $cache->get('a'));
        $this->assertNull($cache->get('b'));
        $this->assertSame('C', $cache->get('c'));
    }

    public function testPutOverwriteMovesToMru(): void
    {
        $cache = new QueryCache(2);
        $cache->put('a', 'A');
        $cache->put('b', 'B');
        $cache->put('a', 'A2'); // overwrite + bump
        $cache->put('c', 'C'); // evicts B (LRU)
        $this->assertSame('A2', $cache->get('a'));
        $this->assertSame('C', $cache->get('c'));
        $this->assertNull($cache->get('b'));
    }

    public function testClearClosesCursorsAndDropsEntries(): void
    {
        $cache = new QueryCache(2);
        $closed = false;
        $fakeStmt = new class ($closed) {
            public function __construct(public bool &$flag) {}
            public function closeCursor(): void
            {
                $this->flag = true;
            }
        };
        $cache->put('a', $fakeStmt);
        $cache->clear();
        $this->assertTrue($closed);
        $this->assertNull($cache->get('a'));
    }

    public function testRejectsZeroOrNegativeCapacity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new QueryCache(0);
    }
}
