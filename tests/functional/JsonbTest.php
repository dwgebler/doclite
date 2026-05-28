<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\MemoryDatabase;
use PHPUnit\Framework\TestCase;

class JsonbTest extends TestCase
{
    private MemoryDatabase $db;

    protected function setUp(): void
    {
        $probe = new MemoryDatabase();
        if (!$probe->getConnection()->supportsJsonb()) {
            $this->markTestSkipped('SQLite < 3.45 (no JSONB support)');
        }
        $this->db = new MemoryDatabase(useJsonb: true);
    }

    public function testInsertAndReadAreTextJsonOnTheWire(): void
    {
        $col = $this->db->collection('items');
        $col->get()->setValue('n', 1)->save();
        $doc = iterator_to_array($col->findAll())[0];
        $this->assertSame(1, $doc->getValue('n'));
    }

    public function testFindByCriteria(): void
    {
        $col = $this->db->collection('items');
        $col->insertMany([['n' => 1, 'name' => 'a'], ['n' => 2, 'name' => 'b']]);
        $doc = $col->findOneBy(['name' => 'b']);
        $this->assertNotNull($doc);
        $this->assertSame(2, $doc->getValue('n'));
    }

    public function testQueryBuilderSelectsJsonbCollection(): void
    {
        $col = $this->db->collection('items');
        $col->insertMany([['n' => 1], ['n' => 2], ['n' => 3]]);
        $rows = iterator_to_array($col->where('n', '>', 1)->fetch());
        $this->assertCount(2, $rows);
    }

    public function testMetaTableTracksStorage(): void
    {
        $this->db->collection('items'); // creates table
        $rows = $this->db->getConnection()->queryAll('SELECT collection, storage FROM "_doclite_meta"');
        $items = array_filter($rows, fn($r) => $r['collection'] === 'items');
        $this->assertCount(1, $items);
        $this->assertSame('jsonb', reset($items)['storage']);
    }
}
