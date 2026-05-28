<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\Collection;
use Gebler\Doclite\Database;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\ExportMode;
use Gebler\Doclite\JournalMode;
use Gebler\Doclite\SyncMode;
use PHPUnit\Framework\TestCase;

abstract class AbstractDatabaseTest extends TestCase
{
    protected Database $db;

    protected function setUp(): void
    {
        $this->db->createTable('test');
    }

    public function testUniqueIndexThrowsExceptionOnDuplicateDocumentFieldValue()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: test.id');
        $this->db->createIndex('test', true, null, 'id');
        $this->db->insert('test', "{\"id\": \"1\"}");
        $this->db->insert('test', "{\"id\": \"1\"}");
    }

    public function testFloatInExecuteDqlQueryReturnsMatchingDocuments()
    {
        $this->db->insert('test', '{"foo":1.145}');
        $query = 'SELECT DISTINCT "test".ROWID, "test".json FROM "test" WHERE ( (json_extract("test".json, \'$.foo\') = ?) ) ORDER BY "test".ROWID LIMIT -1 OFFSET 0;';
        $result = iterator_to_array($this->db->executeDqlQuery($query, [1.145]));
        $this->assertEquals($result[0]['json'], '{"foo":1.145}');
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
            $this->db->createIndex('test', false, null, 'foobar')
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

    public function testCollectionExportStreamsForLargeCollection(): void
    {
        $col = $this->db->collection('big');
        $col->insertMany(array_map(
            fn(int $i) => ['n' => $i, 's' => str_repeat('x', 2048)],
            range(1, 10000)
        ));

        $tmp = sys_get_temp_dir() . '/doclite-streaming-export-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0700, true);

        // memory_reset_peak_usage() requires PHP 8.2+; measure the delta instead.
        // We capture peak before the export, then compare to peak after — the difference
        // is how much headroom the export consumed on top of the test-runner baseline.
        $peakBefore = memory_get_peak_usage(true);
        $this->db->export($tmp, 'json', ExportMode::Collections, ['big']);
        $peakAfter = memory_get_peak_usage(true);
        $exportPeakDelta = $peakAfter - $peakBefore;

        $this->assertFileExists($tmp . '/big.json');
        $contents = file_get_contents($tmp . '/big.json');
        $decoded = json_decode($contents, true);
        $this->assertIsArray($decoded);
        $this->assertCount(10000, $decoded);

        // 5000 docs × ~512 bytes = ~2.5 MB raw payload. With streaming, the peak
        // delta above the pre-export baseline should stay small (a few MB at most).
        // Without streaming the full array (~2.5 MB raw + serializer overhead) is
        // materialized in memory, pushing the delta well above 16 MB.
        // 16 MB delta is a generous bound that catches the buffering regression
        // while comfortably absorbing serializer/test overhead.
        $this->assertLessThan(
            16 * 1024 * 1024,
            $exportPeakDelta,
            sprintf('Export peak memory delta %d bytes (before=%d, after=%d)', $exportPeakDelta, $peakBefore, $peakAfter)
        );

        // Cleanup
        @unlink($tmp . '/big.json');
        @rmdir($tmp);
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

    public function testSetSyncModeAcceptsEnum(): void
    {
        $this->assertTrue($this->db->setSyncMode(SyncMode::Normal));
        $this->assertSame(SyncMode::Normal->value, $this->db->getSyncMode());
    }

    public function testSetJournalModeAcceptsEnum(): void
    {
        $this->assertTrue($this->db->setJournalMode(JournalMode::Memory));
        $this->assertSame(JournalMode::Memory->value, $this->db->getJournalMode());
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

    public function testCreateFullTextIndex()
    {
        $this->db->createFullTextIndex('test', 'abc123', 'foo', 'bar');
        $result = $this->db->scanFtsTables('test');
        $this->assertEquals(['abc123' => ['foo', 'bar']], $result);
    }

    public function testDeleteFullTextIndex()
    {
        $this->db->createFullTextIndex('test', 'abc123', 'foo', 'bar');
        $this->db->deleteFullTextIndex('test', 'abc123');
        $this->assertEmpty($this->db->scanFtsTables('test'));
    }

    public function testScanFtsTablesPreservesFieldNamesContainingTableName(): void
    {
        if (!$this->db->isFtsEnabled()) {
            $this->markTestSkipped('FTS not enabled');
        }
        $col = $this->db->collection('posts');
        // Create an FTS index whose field name contains the table name
        $col->getFullTextIndex('posts_body');

        $tables = $this->db->scanFtsTables('posts');
        $this->assertNotEmpty($tables);
        $allFields = [];
        foreach ($tables as $cols) {
            foreach ($cols as $c) {
                $allFields[] = $c;
            }
        }
        $this->assertContains('posts_body', $allFields);
    }

    public function testUniqueIndexErrorMessageContainsRealFieldNameWithUnderscore(): void
    {
        $collection = $this->db->collection('peoplereal');
        $collection->addUniqueIndex('user_email');

        $doc1 = $collection->get();
        $doc1->setValue('user_email', 'foo@example.com');
        $doc1->save();

        $doc2 = $collection->get();
        $doc2->setValue('user_email', 'foo@example.com');

        try {
            $doc2->save();
            $this->fail('Expected DatabaseException for duplicate unique key');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            $this->assertSame(\Gebler\Doclite\Exception\DatabaseException::ERR_UNIQUE_CONSTRAINT, $e->getCode());
            $this->assertStringContainsString('peoplereal.user_email', $e->getMessage());
        }
    }

    public function testCachedStatementReusableAfterPartialIteration(): void
    {
        $col = $this->db->collection('items');
        foreach (range(1, 5) as $i) {
            $doc = $col->get();
            $doc->setValue('n', $i);
            $doc->save();
        }

        // First call returns a generator; pull one value and abandon
        $gen = $this->db->findAll('items', ['n' => 1]);
        $first = $gen->current();
        $this->assertNotNull($first);
        unset($gen); // generator goes out of scope

        // Second call with same query hash — should work cleanly
        $rows = iterator_to_array($this->db->findAll('items', ['n' => 1]));
        $this->assertCount(1, $rows);
    }

    public function testRegexpMatchesBasicPattern(): void
    {
        $col = $this->db->collection('items');
        $doc = $col->get(); $doc->setValue('name', 'hello world'); $doc->save();
        $rows = iterator_to_array($col->where('name', 'MATCHES', 'hello')->fetch());
        $this->assertCount(1, $rows);
    }

    public function testRegexpReturnsZeroOnMalformedPattern(): void
    {
        $col = $this->db->collection('items');
        $doc = $col->get(); $doc->setValue('name', 'hello'); $doc->save();
        // '(' alone is malformed PCRE — should yield 0 matches, not crash
        $rows = iterator_to_array($col->where('name', 'MATCHES', '(')->fetch());
        $this->assertCount(0, $rows);
    }

    public function testRegexpRespectsUnicode(): void
    {
        $col = $this->db->collection('items');
        $doc = $col->get(); $doc->setValue('name', 'café'); $doc->save();
        $rows = iterator_to_array($col->where('name', 'MATCHES', 'caf.')->fetch());
        $this->assertCount(1, $rows);
    }

    public function testRegexpRejectsControlCharacterInPattern(): void
    {
        $col = $this->db->collection('items');
        $doc = $col->get(); $doc->setValue('name', 'hello'); $doc->save();
        // NUL byte in pattern is rejected; the query should not match
        $rows = iterator_to_array($col->where('name', 'MATCHES', "hello\x00")->fetch());
        $this->assertCount(0, $rows);
    }

    public function testInsertManyInsertsRowsAndReturnsCount(): void
    {
        $col = $this->db->collection('items');
        $count = $col->insertMany([
            ['n' => 1],
            ['n' => 2],
            ['n' => 3],
        ]);
        $this->assertSame(3, $count);
        $rows = iterator_to_array($col->findAll());
        $this->assertCount(3, $rows);
    }

    public function testInsertManyHonorsExistingIdField(): void
    {
        $col = $this->db->collection('items');
        $col->insertMany([
            ['__id' => 'fixed-1', 'n' => 1],
        ]);
        $doc = $col->findOneBy(['__id' => 'fixed-1']);
        $this->assertNotNull($doc);
    }

    public function testInsertManyRollsBackOnFailure(): void
    {
        $col = $this->db->collection('items');
        $col->addUniqueIndex('email');
        try {
            $col->insertMany([
                ['email' => 'a@b.com'],
                ['email' => 'a@b.com'], // duplicate triggers unique-constraint
            ]);
            $this->fail('Expected DatabaseException');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            // both rows should be rolled back
            $this->assertSame(0, $col->count());
        }
    }

    public function testSaveAllWritesAllDocuments(): void
    {
        $col = $this->db->collection('items');
        $documents = [];
        foreach (range(1, 5) as $i) {
            $doc = $col->get();
            $doc->setValue('n', $i);
            $documents[] = $doc;
        }
        $count = $col->saveAll($documents);
        $this->assertSame(5, $count);
        $this->assertSame(5, $col->count());
    }

    public function testSaveAllAcceptsGenerator(): void
    {
        $col = $this->db->collection('items');
        $gen = (function () use ($col) {
            foreach (range(1, 3) as $i) {
                $doc = $col->get();
                $doc->setValue('n', $i);
                yield $doc;
            }
        })();
        $count = $col->saveAll($gen);
        $this->assertSame(3, $count);
    }

    public function testUpsertByInsertsWhenAbsent(): void
    {
        $col = $this->db->collection('users');
        $doc = $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alice']);
        $this->assertInstanceOf(\Gebler\Doclite\Document::class, $doc);
        $this->assertSame('Alice', $doc->getValue('name'));
        $this->assertSame(1, $col->count());
    }

    public function testUpsertByUpdatesWhenPresent(): void
    {
        $col = $this->db->collection('users');
        $first = $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alice']);
        $second = $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alicia']);
        $this->assertSame($first->getId(), $second->getId());
        $this->assertSame('Alicia', $second->getValue('name'));
        $this->assertSame(1, $col->count());
    }

    public function testUpsertByMergesDataIntoExistingFields(): void
    {
        $col = $this->db->collection('users');
        $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alice', 'role' => 'admin']);
        $updated = $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alicia']);
        $this->assertSame('Alicia', $updated->getValue('name'));
        // role is not in the second data array — existing value is preserved
        $this->assertSame('admin', $updated->getValue('role'));
    }

    public function testUpsertByRollsBackOnFailure(): void
    {
        $col = $this->db->collection('users');
        $col->addUniqueIndex('email');
        $col->upsertBy(['email' => 'a@b.com'], ['email' => 'a@b.com', 'name' => 'Alice']);
        // Insert a second user under a different criteria, then upsert that
        // criteria with an email that conflicts with the first — fail.
        $col->upsertBy(['email' => 'b@c.com'], ['email' => 'b@c.com', 'name' => 'Bob']);
        try {
            $col->upsertBy(['email' => 'b@c.com'], ['email' => 'a@b.com', 'name' => 'Bob 2']);
            $this->fail('Expected DatabaseException');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            // Both pre-upsert documents should still exist intact
            $this->assertSame(2, $col->count());
            $this->assertSame('Bob', $col->findOneBy(['email' => 'b@c.com'])->getValue('name'));
        }
    }

    public function testPaginateReturnsFirstPage(): void
    {
        $col = $this->db->collection('items');
        foreach (range(1, 25) as $i) {
            $doc = $col->get();
            $doc->setValue('n', $i);
            $doc->save();
        }
        $page = $col->where('n', '>', 0)->orderBy('n', 'ASC')->paginate(1, 10);
        $this->assertSame(25, $page->total);
        $this->assertSame(3, $page->pageCount);
        $this->assertSame(10, count($page->items));
        $this->assertTrue($page->hasMore);
        $this->assertSame(1, $page->items[0]->getValue('n'));
        $this->assertSame(10, $page->items[9]->getValue('n'));
    }

    public function testPaginateLastPagePartial(): void
    {
        $col = $this->db->collection('items');
        foreach (range(1, 25) as $i) {
            $doc = $col->get();
            $doc->setValue('n', $i);
            $doc->save();
        }
        $page = $col->where('n', '>', 0)->orderBy('n', 'ASC')->paginate(3, 10);
        $this->assertSame(25, $page->total);
        $this->assertSame(5, count($page->items));
        $this->assertFalse($page->hasMore);
        $this->assertSame(21, $page->items[0]->getValue('n'));
    }

    public function testPaginateClampsPageAndPerPageToPositive(): void
    {
        $col = $this->db->collection('items');
        $doc = $col->get();
        $doc->setValue('n', 1);
        $doc->save();
        $page = $col->where('n', '>', 0)->paginate(0, 0);
        $this->assertSame(1, $page->page);
        $this->assertSame(1, $page->perPage);
    }

    public function testSumReturnsTotal(): void
    {
        $col = $this->db->collection('items');
        foreach ([1, 2, 3, 4] as $v) {
            $col->get()->setValue('n', $v)->save();
        }
        $this->assertSame(10.0, $col->where('n', '>', 0)->sum('n'));
    }

    public function testSumReturnsZeroOnEmpty(): void
    {
        $col = $this->db->collection('items');
        $this->assertSame(0.0, $col->where('n', '>', 0)->sum('n'));
    }

    public function testAvgReturnsMean(): void
    {
        $col = $this->db->collection('items');
        foreach ([2, 4, 6] as $v) {
            $col->get()->setValue('n', $v)->save();
        }
        $this->assertSame(4.0, $col->where('n', '>', 0)->avg('n'));
    }

    public function testMinAndMax(): void
    {
        $col = $this->db->collection('items');
        foreach ([5, 1, 9, 3] as $v) {
            $col->get()->setValue('n', $v)->save();
        }
        $this->assertSame(1, $col->where('n', '>', 0)->min('n'));
        $this->assertSame(9, $col->where('n', '>', 0)->max('n'));
    }

    public function testMinReturnsNullOnEmpty(): void
    {
        $col = $this->db->collection('items');
        $this->assertNull($col->where('n', '>', 0)->min('n'));
    }

    public function testGroupByYieldsRowsWithAliases(): void
    {
        $col = $this->db->collection('items');
        foreach ([['c' => 'a', 'v' => 1], ['c' => 'a', 'v' => 2], ['c' => 'b', 'v' => 3]] as $row) {
            $d = $col->get();
            foreach ($row as $k => $v) {
                $d->setValue($k, $v);
            }
            $d->save();
        }
        $rows = iterator_to_array($col->where('v', '>', 0)->groupBy('c', ['sum_v' => 'SUM(v)', 'count' => 'COUNT(*)']));
        // sort by c for deterministic order
        usort($rows, fn($a, $b) => strcmp($a['c'], $b['c']));
        $this->assertEquals([
            ['c' => 'a', 'sum_v' => 3, 'count' => 2],
            ['c' => 'b', 'sum_v' => 3, 'count' => 1],
        ], $rows);
    }

    public function testGroupByRejectsUnsafeAggregateExpression(): void
    {
        $col = $this->db->collection('items');
        $this->expectException(\InvalidArgumentException::class);
        iterator_to_array($col->where('v', '>', 0)->groupBy('c', ['bad' => "(SELECT 1); DROP TABLE x;--"]));
    }

    public function testPartialIndexIsUsedForFilteredQuery(): void
    {
        $col = $this->db->collection('orders');
        $col->insertMany([
            ['status' => 'active',   'total' => 100],
            ['status' => 'archived', 'total' => 200],
            ['status' => 'active',   'total' => 150],
        ]);
        $created = $col->addPartialIndex(['total'], "json_extract(json,'\$.status') = 'active'");
        $this->assertTrue($created);

        // EXPLAIN QUERY PLAN to confirm the index is consulted
        $plan = iterator_to_array($this->db->getConnection()->query(
            "EXPLAIN QUERY PLAN SELECT json FROM orders WHERE json_extract(json,'\$.total') > 0 AND json_extract(json,'\$.status') = 'active'"
        ));
        $detail = implode(' ', array_column($plan, 'detail'));
        $this->assertStringContainsString('idx_orders__total', $detail);
    }

    public function testPartialUniqueIndexAllowsDuplicatesOutsidePredicate(): void
    {
        $col = $this->db->collection('emails');
        $col->addPartialIndex(['email'], "json_extract(json,'\$.status') = 'active'", unique: true);

        $col->insertMany([
            ['email' => 'foo@bar.com', 'status' => 'active'],
            ['email' => 'foo@bar.com', 'status' => 'archived'], // duplicate but excluded by predicate
        ]);
        $this->assertSame(2, $col->count());

        // Now actually violate the partial uniqueness
        try {
            $col->insertMany([['email' => 'foo@bar.com', 'status' => 'active']]);
            $this->fail('Expected DatabaseException');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            $this->assertSame(\Gebler\Doclite\Exception\DatabaseException::ERR_UNIQUE_CONSTRAINT, $e->getCode());
        }
    }

    public function testCollectionSchemaValidatesOnSave(): void
    {
        $col = $this->db->collection('items');
        $col->setSchema(json_encode([
            'type' => 'object',
            'required' => ['n'],
            'properties' => [
                'n' => ['type' => 'integer'],
            ],
        ]));
        $doc = $col->get();
        $doc->setValue('n', 'not-an-int');
        $this->expectException(\Gebler\Doclite\Exception\DatabaseException::class);
        $col->save($doc);
    }

    public function testCollectionSchemaValidatesOnInsertMany(): void
    {
        $col = $this->db->collection('items');
        $col->setSchema(json_encode([
            'type' => 'object',
            'required' => ['n'],
            'properties' => [
                'n' => ['type' => 'integer'],
            ],
        ]));
        $this->expectException(\Gebler\Doclite\Exception\DatabaseException::class);
        $col->insertMany([['n' => 1], ['n' => 'oops']]);
    }

    public function testCollectionSchemaValidatesOnSaveAll(): void
    {
        $col = $this->db->collection('items');
        $col->setSchema(json_encode([
            'type' => 'object',
            'required' => ['n'],
            'properties' => [
                'n' => ['type' => 'integer'],
            ],
        ]));
        $bad = $col->get();
        $bad->setValue('n', 'oops');
        $this->expectException(\Gebler\Doclite\Exception\DatabaseException::class);
        $col->saveAll([$bad]);
    }

    public function testCollectionSchemaPassesValidData(): void
    {
        $col = $this->db->collection('items');
        $col->setSchema(json_encode([
            'type' => 'object',
            'required' => ['n'],
            'properties' => [
                'n' => ['type' => 'integer'],
            ],
        ]));
        $col->insertMany([['n' => 1], ['n' => 2]]);
        $this->assertSame(2, $col->count());
    }

    public function testCollectionSchemaCanBeRemoved(): void
    {
        $col = $this->db->collection('items');
        $col->setSchema(json_encode([
            'type' => 'object',
            'required' => ['n'],
            'properties' => [
                'n' => ['type' => 'integer'],
            ],
        ]));
        $col->removeSchema();
        $col->insertMany([['n' => 'no-schema-no-problem']]);
        $this->assertSame(1, $col->count());
    }

    public function testSaveAllRollsBackOnFailure(): void
    {
        $col = $this->db->collection('items');
        $col->addUniqueIndex('email');

        // Pre-seed two rows so that saveAll's replace() takes the UPDATE path.
        // Then modify one document to violate the unique constraint on the other,
        // which causes an UPDATE-level unique-constraint error mid-batch.
        $alice = $col->get();
        $alice->setValue('email', 'alice@example.com');
        $alice->setValue('name', 'Alice');
        $col->save($alice);

        $bob = $col->get();
        $bob->setValue('email', 'bob@example.com');
        $bob->setValue('name', 'Bob');
        $col->save($bob);

        $this->assertSame(2, $col->count());

        // Now change bob's email to conflict with alice inside a saveAll.
        // The saveAll should roll back the change to alice's name too.
        $alice->setValue('name', 'Alice (updated)');
        $bob->setValue('email', 'alice@example.com'); // duplicate → UPDATE constraint error

        try {
            $col->saveAll([$alice, $bob]);
            $this->fail('Expected DatabaseException for duplicate email');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            // Both rows should be unchanged because the whole batch rolled back.
            $this->assertSame(2, $col->count());
            $this->assertSame('Alice', $col->findOneBy(['email' => 'alice@example.com'])->getValue('name'));
            $this->assertSame('bob@example.com', $col->findOneBy(['email' => 'bob@example.com'])->getValue('email'));
        }
    }

    public function testUpsertByRollsBackInnerOperationOnConflict(): void
    {
        $col = $this->db->collection('items');
        $col->addUniqueIndex('email');
        $col->insertMany([
            ['email' => 'a@b.com', 'name' => 'Alice'],
            ['email' => 'b@b.com', 'name' => 'Bob'],
        ]);
        // Upserting b@b.com with email a@b.com violates the unique constraint
        try {
            $col->upsertBy(['email' => 'b@b.com'], ['email' => 'a@b.com', 'name' => 'NotBob']);
            $this->fail('Expected DatabaseException');
        } catch (\Gebler\Doclite\Exception\DatabaseException $e) {
            $alice = $col->findOneBy(['email' => 'a@b.com']);
            $bob = $col->findOneBy(['email' => 'b@b.com']);
            $this->assertSame('Alice', $alice->getValue('name'));
            $this->assertSame('Bob', $bob->getValue('name'));
        }
    }
}
