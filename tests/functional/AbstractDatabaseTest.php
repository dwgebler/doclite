<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Database;
use Gebler\Doclite\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

abstract class AbstractDatabaseTest extends TestCase
{
    protected Database $db;

    public function setUp(): void
    {
        $this->db->createTable('test');
    }

    public function testFind()
    {
        $this->db->insert('test', '{"foo": "baz"}');
        $this->db->insert('test', '{"foo": "bar"}');
        $this->assertEquals(
            '{"foo":"bar"}',
            $this->db->find('test', ['foo' => 'bar'])
        );
    }

    public function testSetJournalMode()
    {
        $this->assertTrue(
            $this->db->setJournalMode(Database::MODE_JOURNAL_MEMORY));
    }

    public function testCreateTable()
    {
        $this->assertFalse($this->db->tableExists('foobar'));
        $this->db->createTable('foobar');
        $this->assertTrue($this->db->tableExists('foobar'));
    }

    public function testSetSyncMode()
    {
        $this->assertTrue(
            $this->db->setSyncMode(Database::MODE_SYNC_FULL));
    }

    public function testCreateIndex()
    {
        $this->assertTrue(
            $this->db->createIndex('test', 'foobar')
        );
    }

    public function testReplace()
    {
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'bop'
        ]);
        $this->db->replace('test', '12345', $json);
        $data = $this->db->find('test', [Database::ID_FIELD => '12345']);
        $this->assertEquals($json, $data);
    }

    public function testExecuteDmlQuery()
    {
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $result = $this->db->executeDmlQuery(
            "DELETE FROM \"test\" WHERE ROWID = ?", [1]);
        $this->assertSame(1, $result);
    }

    public function testInsert()
    {
        $this->assertTrue($this->db->insert('test', '{"foo":"bar"}'));
    }

    public function testGetById()
    {
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $result = $this->db->getById('test', '12345');
        $this->assertEquals($json, $result);
    }

    public function testDelete()
    {
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $result = $this->db->getById('test', '12345');
        $this->assertEquals($json, $result);
        $this->db->delete('test', '12345');
        $result = $this->db->getById('test', '12345');
        $this->assertEmpty($result);
    }

    public function testCreateCacheTable()
    {
        $this->assertTrue($this->db->createCacheTable('test_cache'));
    }

    public function testRollback()
    {
        $this->db->beginTransaction('test');
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $this->db->rollback('test');
        $result = $this->db->getById('test', '12345');
        $this->assertEmpty($result);
    }

    public function testTableExists()
    {
        $this->assertTrue($this->db->tableExists('test'));
        $this->assertFalse($this->db->tableExists('foobar'));
    }

    public function testSetCache()
    {
        $expiry = new \DateTimeImmutable('now +10 seconds');
        $this->db->createCacheTable('test_cache');
        $this->assertTrue($this->db->setCache(
            'test_cache', 'find', '12345', '12345', '{"foo":"bar"}', $expiry));
    }

    public function testCacheAutoPruneRemovesExpiredCaches()
    {
        $this->db->enableCacheAutoPrune();
        $expiryPast = new \DateTimeImmutable('now -10 seconds');
        $this->db->createCacheTable('test_cache');
        $this->db->setCache('test_cache', 'find', '12345', '12345', '{"foo":"bar"}', $expiryPast);
        $expiryLater = new \DateTimeImmutable('now +10 seconds');
        $this->db->setCache('test_cache', 'find', '12345', '23456', '{"bar":"baz"}', $expiryLater);
        $cache = iterator_to_array($this->db->getCache('test_cache','find', '12345', null));
        $this->assertEquals(['{"bar":"baz"}'], $cache);
    }

    public function testImport()
    {
        $dataDir = __DIR__.'/../data';
        $dataFile = __DIR__.'/../data/user.json';
        $data = json_decode(file_get_contents($dataFile), true);
        $this->db->import($dataDir, 'json', Database::MODE_IMPORT_COLLECTIONS);
        $actual = iterator_to_array($this->db->findAll('user', []));
        $this->assertEquals(
            [$data[0], $data[1]],
            [json_decode($actual[0],true), json_decode($actual[1],true)]
        );
    }

    public function testGetJournalMode()
    {
        $this->db->setJournalMode(Database::MODE_JOURNAL_MEMORY);
        $this->assertSame(
            Database::MODE_JOURNAL_MEMORY, $this->db->getJournalMode());
    }

    public function testExport()
    {
        $data = [
            [Database::ID_FIELD => '12345', 'foobar' => 'baz'],
            [Database::ID_FIELD => '56789', 'foobar' => 'bop'],
        ];
        $this->db->insert('test', json_encode($data[0]));
        $this->db->insert('test', json_encode($data[1]));
        $tempDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid("DOCLITE_FS_");
        @mkdir($tempDir);
        $this->db->export($tempDir, 'json',
            Database::MODE_IMPORT_COLLECTIONS, []);
        $fileName = $tempDir.\DIRECTORY_SEPARATOR."test.json";
        $this->assertFileExists($fileName);
        $this->assertEquals(json_encode($data), file_get_contents($fileName));
        @unlink($fileName);
        @rmdir($tempDir);
    }

    public function testExecuteDqlQuery()
    {
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $query= 'SELECT json FROM "test" WHERE ROWID=?';
        $this->assertEquals(
            [
                ['json' => $json],
            ],
            iterator_to_array($this->db->executeDqlQuery($query, [1]))
        );
    }

    public function testGetCache()
    {
        $expiry = new \DateTimeImmutable('now +10 seconds');
        $this->db->createCacheTable('test_cache');
        $this->db->setCache(
            'test_cache', 'find', '12345', '12345','{"foo":"bar"}', $expiry);
        $this->assertEquals(
            ['{"foo":"bar"}'],
            iterator_to_array($this->db->getCache(
                'test_cache', 'find', '12345', new \DateTimeImmutable()))
        );
    }

    public function testGetSyncMode()
    {
        $this->db->setSyncMode(Database::MODE_SYNC_FULL);
        $this->assertSame(
            Database::MODE_SYNC_FULL, $this->db->getSyncMode());
    }

    public function testCommit()
    {
        $this->db->beginTransaction('test');
        $json = json_encode([
            Database::ID_FIELD => '12345',
            'foobar' => 'baz'
        ]);
        $this->db->insert('test', $json);
        $this->db->commit('test');
        $result = $this->db->getById('test', '12345');
        $this->assertEquals($json, $result);
    }

    public function testFlushTable()
    {
        $this->db->insert('test', '{"foo":"bar"}');
        $this->db->insert('test', '{"bar":"baz"}');
        $this->db->flushTable('test');
        $this->assertEmpty(iterator_to_array($this->db->findAll('test', [])));
    }

    public function testFindAll()
    {
        $this->db->insert('test', '{"foo":"bar"}');
        $this->db->insert('test', '{"bar":"baz"}');
        $expectedAll = ['{"foo":"bar"}', '{"bar":"baz"}'];
        $this->assertEquals(
            ['{"bar":"baz"}'],
            iterator_to_array($this->db->findAll('test', ['bar' => 'baz']))
        );
        $this->assertEquals($expectedAll, iterator_to_array($this->db->findAll('test', [])));
    }

    public function testIsReadOnly()
    {
        $this->assertFalse($this->db->isReadOnly());
    }

    public function testCollection()
    {
        $collection = $this->db->collection("new_test");
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertTrue($this->db->tableExists('new_test'));
    }
}
