<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\MemoryDatabase;

class MemoryDatabaseTest extends AbstractDatabaseTest
{
    protected function setUp(): void
    {
        $this->db = new MemoryDatabase();
        parent::setup();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
