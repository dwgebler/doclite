<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\PaginatedResult;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    public function testComputesPageCountAndHasMore(): void
    {
        $r = new PaginatedResult(['a', 'b'], total: 10, page: 1, perPage: 2);
        $this->assertSame(5, $r->pageCount);
        $this->assertTrue($r->hasMore);
    }

    public function testLastPageHasNoMore(): void
    {
        $r = new PaginatedResult(['a'], total: 9, page: 5, perPage: 2);
        $this->assertSame(5, $r->pageCount);
        $this->assertFalse($r->hasMore);
    }

    public function testEmptyResultsZeroPageCount(): void
    {
        $r = new PaginatedResult([], total: 0, page: 1, perPage: 10);
        $this->assertSame(0, $r->pageCount);
        $this->assertFalse($r->hasMore);
    }

    public function testZeroPerPageGuard(): void
    {
        $r = new PaginatedResult([], total: 5, page: 1, perPage: 0);
        $this->assertSame(0, $r->pageCount);
        $this->assertFalse($r->hasMore);
    }
}
