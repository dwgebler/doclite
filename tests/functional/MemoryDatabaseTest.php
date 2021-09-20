<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\MemoryDatabase;

class MemoryDatabaseTest extends AbstractDatabaseTest
{
    protected function setUp(): void
    {
        $this->db = new MemoryDatabase(true);
        parent::setup();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
