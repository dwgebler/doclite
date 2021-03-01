<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\MemoryDatabase;

class MemoryDatabaseTest extends AbstractDatabaseTest
{
    public function setUp(): void
    {
        $this->db = new MemoryDatabase();
        parent::setup();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
