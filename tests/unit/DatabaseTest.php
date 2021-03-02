<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Database;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\FileDatabase;
use Gebler\Doclite\MemoryDatabase;
use Gebler\Doclite\Tests\fakes\FakeFileSystem;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private $conn;
    private $fs;
    private $db;
    private $readDb;

    public function setUp(): void
    {
        $this->conn = $this->createMock(DatabaseConnection::class);
        $this->fs = new FakeFileSystem();
        $this->db = new MemoryDatabase($this->conn, $this->fs);
        $this->readDb = $db = new FileDatabase(
            '/foo/bar', true, $this->conn, $this->fs);
    }

    public function testGetSyncModeReturnsIntegerMode()
    {
        $this->conn->method('valueQuery')->willReturn('1');
        $this->assertSame(1, $this->db->getSyncMode());
    }

    public function testSetSyncModeReturnsFalseInvalidMode()
    {
        $this->assertFalse($this->db->setSyncMode(99));
    }

    public function testSetSyncModeReturnsDbResultValidSyncMode()
    {
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(0, 1);
        $this->assertTrue($this->db->setSyncMode(Database::MODE_SYNC_FULL));
        $this->assertFalse($this->db->setSyncMode(Database::MODE_SYNC_EXTRA));
    }

    public function testSetJournalModeReturnsFalseInvalidMode()
    {
        $this->assertFalse($this->db->setJournalMode(99));
    }

    public function testSetJournalModeReturnsDbResultValidSyncMode()
    {
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(0, 1);
        $this->assertTrue($this->db->setJournalMode(Database::MODE_JOURNAL_NONE));
        $this->assertFalse($this->db->setJournalMode(Database::MODE_JOURNAL_PERSIST));
    }

    public function testGetJournalModeReturnsUppercasedMode()
    {
        $this->conn->method('valueQuery')->willReturn('wal');
        $this->assertEquals('WAL', $this->db->getJournalMode());
    }

    public function testCollectionReturnsNamedCollection()
    {
        $collection = $this->db->collection('foobar');
        $this->assertEquals('foobar', $collection->getName());
    }

    public function testBeginTransactionExceptionOnReadOnly()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->beginTransaction('foo');
    }

    public function testBeginTransactionExceptionOnAlreadyInTransaction()
    {
        $this->conn->method('beginTransaction')
            ->willReturn(true);
        $this->db->beginTransaction('foo');
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_COLLECTION_IN_TRANSACTION);
        $this->db->beginTransaction('bar');
    }

    public function testBeginTransactionReturnsSuccessFlagFromConn()
    {
        $this->conn->method('beginTransaction')
            ->willReturnOnConsecutiveCalls(true, false);
        $this->assertTrue($this->db->beginTransaction('foo'));
        $this->assertFalse($this->db->beginTransaction('foo'));
    }

    public function testCommitExceptionOnReadOnly()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->commit('foo');
    }

    public function testCommitExceptionOnAlreadyInTransaction()
    {
        $this->conn->method('beginTransaction')
            ->willReturn(true);
        $this->db->beginTransaction('foo');
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_COLLECTION_IN_TRANSACTION);
        $this->db->commit('bar');
    }

    public function testCommitReturnsSuccessFlagFromConn()
    {
        $this->conn->method('beginTransaction')
            ->willReturn(true);
        $this->db->beginTransaction('foo');
        $this->conn->method('commit')
            ->willReturnOnConsecutiveCalls(true, false);
        $this->assertTrue($this->db->commit('foo'));
        $this->assertFalse($this->db->commit('foo'));
    }

    public function testRollbackExceptionOnReadOnly()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->rollback('foo');
    }

    public function testRollbackExceptionOnAlreadyInTransaction()
    {
        $this->conn->method('beginTransaction')
            ->willReturn(true);
        $this->db->beginTransaction('foo');
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_COLLECTION_IN_TRANSACTION);
        $this->db->rollback('bar');
    }

    public function testRollbackReturnsSuccessFlagFromConn()
    {
        $this->conn->method('beginTransaction')
            ->willReturn(true);
        $this->db->beginTransaction('foo');
        $this->conn->method('rollback')
            ->willReturnOnConsecutiveCalls(true, false);
        $this->assertTrue($this->db->rollback('foo'));
        $this->assertFalse($this->db->rollback('foo'));
    }

    public function testReplaceExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->replace('foo', 'bar', 'baz');
    }

    public function testReplaceReturnFalseOnInvalidTableName()
    {
        $this->assertFalse($this->db->replace('Not Valid!', 'bar', 'baz'));
    }

    public function testReplaceReturnTrueOnInitialRowsUpdatedOneOrZero()
    {
        $this->conn->method('executePrepared')->willReturnOnConsecutiveCalls(
            0, 1
        );
        $this->assertTrue($this->db->replace('foo', 'bar', 'baz'));
    }

    public function testReplaceReturnFalseOnInitialRowsUpdatedZeroAndZero()
    {
        $this->conn->method('executePrepared')->willReturnOnConsecutiveCalls(
            0, 0
        );
        $this->assertFalse($this->db->replace('foo', 'bar', 'baz'));
    }

    public function testReplaceExceptionOnMultipleRowsUpdated()
    {
        $this->conn->method('executePrepared')->willReturn(2);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_ID_CONFLICT);
        $this->db->replace('foo', 'bar', 'baz');
    }

    public function testDeleteReturnFalseOnInvalidTableName()
    {
        $this->assertFalse($this->db->delete('sqlite_baz', 'baz'));
    }

    public function testDeleteExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->delete('foobar', 'baz');
    }

    public function testDeleteReturnsConnectionSuccessFlag()
    {
        $this->conn->method('executePrepared')->willReturnOnConsecutiveCalls(
            1, 0
        );
        $this->assertTrue($this->db->delete('foobar', 'baz'));
        $this->assertFalse($this->db->delete('foobar', 'baz'));
    }

    public function testInsertReturnFalseOnInvalidTableName()
    {
        $this->assertFalse($this->db->insert('123NotValid', 'baz'));
    }

    public function testInsertExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->insert('foobar', 'baz');
    }

    public function testInsertReturnsConnectionSuccessFlag()
    {
        $this->conn->method('executePrepared')->willReturnOnConsecutiveCalls(
            1, 0
        );
        $this->assertTrue($this->db->insert('foobar', 'baz'));
        $this->assertFalse($this->db->insert('foobar', 'baz'));
    }

    public function testFindAllExceptionOnInvalidTableName()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_INVALID_COLLECTION);
        $this->db->findAll('123NotValid', []);
    }

    public function testFindAllReturnsJsonColumnOfResultRows()
    {
        $this->conn->method('query')->willReturn([
            ['column1' => 'whatever', 'json' => '{"__id": "12345"}', 'column2' => 'abcdef'],
            ['column1' => 'whatever', 'json' => '{"__id": "67890"}', 'column2' => 'abcdef'],
        ]);
        $expected = ['{"__id": "12345"}', '{"__id": "67890"}'];
        $actual = $this->db->findAll('foobar', []);
        $this->assertSame($expected, $actual);
    }

    public function testFindAllReturnsEmptyArrayOnNoResults()
    {
        $this->conn->method('query')->willReturn([]);
        $actual = $this->db->findAll('foobar', []);
        $this->assertSame([], $actual);
    }

    public function testFindReturnsEmptyStringOnEmptyCriteria()
    {
        $this->assertSame('', $this->db->find('foobar', []));
    }

    public function testFindReturnsConnResultOnNonEmptyCriteria()
    {
        $this->conn->method('valueQuery')->wilLReturn('foo');
        $this->assertSame('foo', $this->db->find('foobar', ['baz' => true]));
    }

    public function testGetByIdExceptionOnInvalidTableName()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_INVALID_COLLECTION);
        $this->db->getById('Not$Valid', 'baz');
    }

    public function testGetByIdReturnsConnResult()
    {
        $this->conn->method('valueQuery')->wilLReturn('foo');
        $this->assertSame('foo', $this->db->getById('foobar', 'baz'));
    }

    public function testTableExistsReturnsBooleanOfConnResultPresent()
    {
        $this->conn->method('query')->willReturnOnConsecutiveCalls(
            ['field' => 'value'], []);
        $this->assertTrue($this->db->tableExists('foo'));
        $this->assertFalse($this->db->tableExists('bar'));
    }

    public function testCreateTableExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->createTable('foo');
    }

    public function testCreateTableReturnsFalseOnInvalidTableName()
    {
        $this->assertFalse($this->db->createTable('Table**Name'));
    }

    public function testCreateTableReturnsTrueOnConnResultEqualsZero()
    {
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(
            0, 1);
        $this->assertTrue($this->db->createTable('foo'));
        $this->assertFalse($this->db->createTable('bar'));
    }

    public function testCreateCacheTableExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->createCacheTable('foo');
    }

    public function testCreateCacheTableReturnsFalseOnInvalidCacheTableName()
    {
        $this->assertFalse($this->db->createCacheTable('CacheTable**Name'));
    }

    public function testCreateCacheTableReturnsTrueOnConnResultEqualsZero()
    {
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(
            0, 0, 1, 1);
        $this->assertTrue($this->db->createCacheTable('foo'));
        $this->assertFalse($this->db->createCacheTable('bar'));
    }

    public function testGetCacheReturnEmptyStringOnInvalidTableName()
    {
        $this->assertSame('', $this->db->getCache('Not&Valid', '', '', new \DateTimeImmutable()));
    }

    public function testGetCacheReturnsConnResult()
    {
        $this->conn->method('valueQuery')->wilLReturn('bar');
        $this->assertSame('bar', $this->db->getCache('foobar', 'find', '12345', new \DateTimeImmutable()));
    }

    public function testSetCacheExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->setCache('foo', '', '', '', new \DateTimeImmutable());
    }

    public function testSetCacheReturnsFalseOnInvalidCacheTableName()
    {
        $this->assertFalse($this->db->setCache('CacheTable**Name', '', '', '', new \DateTimeImmutable()));
    }

    public function testSetCacheReturnsBooleanConnResultEqualsOne()
    {
        $this->conn->method('executePrepared')->willReturnOnConsecutiveCalls(
            1, 0);
        $this->assertTrue($this->db->setCache('foo', '', '', '', new \DateTimeImmutable()));
        $this->assertFalse($this->db->setCache('bar', '', '', '', new \DateTimeImmutable()));
    }

    public function testFlushTableExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->flushTable('foo');
    }

    public function testFlushTableReturnsFalseOnInvalidCacheTableName()
    {
        $this->assertFalse($this->db->flushTable('CacheTable**Name'));
    }

    public function testFlushTableReturnsBooleanConnResultNotEqualsZero()
    {
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(
            1, 0);
        $this->assertTrue($this->db->flushTable('foo'));
        $this->assertFalse($this->db->flushTable('bar'));
    }

    public function testCreateIndexExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->createIndex('foo');
    }

    public function testCreateIndexReturnsFalseOnInvalidCacheTableName()
    {
        $this->assertFalse($this->db->createIndex('CacheTable**Name'));
    }


    public function testCreateIndexReturnsFalseOnZeroLengthFieldName()
    {
        $this->assertFalse($this->db->createIndex('foo', ''));
    }

    public function testCreateIndexReturnsFalseOnFieldNameLongerThan64Chars()
    {
        $tooLong = 'this_field_name_is_far_too_long_to_be_accepted_as_a_valid_index_field_name';
        $this->assertFalse($this->db->createIndex('foo', $tooLong));
    }

    public function testCreateIndexReturnsFalseOnInvalidFieldName()
    {
        $this->assertFalse($this->db->createIndex('foo', 'Not! Valid field'));
    }

    public function testCreateIndexReturnsTrueOnExistingIndex()
    {
        $this->conn->method('valueQuery')->willReturn('1');
        $this->assertTrue($this->db->createIndex('foo', 'bar'));
    }

    public function testCreateIndexReturnsBooleanSuccessFlagFromConn()
    {
        $this->conn->method('valueQuery')->willReturn('');
        $this->conn->method('exec')->willReturnOnConsecutiveCalls(0, 1);
        $this->assertTrue($this->db->createIndex('foo', 'bar'));
        $this->assertFalse($this->db->createIndex('foo', 'bar'));
    }

    public function testGetVersionReturnsSemverString()
    {
        $semverPattern = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'.
        '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';
        $this->assertMatchesRegularExpression($semverPattern, $this->db->getVersion());
    }

    public function testIsReadOnlyReturnsReadOnlyMode()
    {
        $this->assertFalse($this->db->isReadOnly());
        $this->assertTrue($this->readDb->isReadOnly());
    }

    public function testOptimizeThrowsExceptionOnReadOnlyMode()
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_READ_ONLY_MODE);
        $this->readDb->optimize();
    }
}

