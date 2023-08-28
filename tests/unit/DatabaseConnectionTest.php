<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\Tests\fakes\FakePDO;
use Gebler\Doclite\Tests\fakes\FakePDOStatement;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DatabaseConnectionTest extends TestCase
{
    private $pdo;
    private $stmt;
    private $conn;
    private $log;
    private $logs = [
        'warning' => [],
        'debug' => [],
        'error' => [],
    ];

    protected function setUp(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('These tests only run on PHP 8 due to fake PDO implementation');
        }
        $this->stmt = new FakePDOStatement();
        $this->pdo = new FakePDO($this->stmt);
        $this->log = $this->createMock(LoggerInterface::class);
        $this->log->method('debug')->willReturnCallback(function ($message, $params) {
            $this->logs['debug'][] = ['message' => $message, 'params' => $params];
        });
        $this->log->method('warning')->willReturnCallback(function ($message, $params) {
            $this->logs['warning'][] = ['message' => $message, 'params' => $params];
        });
        $this->log->method('error')->willReturnCallback(function ($message, $params) {
            $this->logs['error'][] = ['message' => $message, 'params' => $params];
        });
        $this->stmt->setResult(['ENABLE_JSON1', 'ENABLE_FTS5']);
        $this->conn = new DatabaseConnection('sqlite::memory:', false, 1, true, $this->log, $this->pdo);
        $this->stmt->setResult([]);
    }

    public function testEnableQueryLogWritesToLog(): void
    {
        $this->conn->enableQueryLogging();
        $this->stmt->setResult([]);
        iterator_to_array($this->conn->query('SELECT * FROM test WHERE foo = ?', 'bar'));
        $this->assertNotEmpty($this->logs['debug']);
        $this->assertEquals('Query: SELECT * FROM test WHERE foo = ?', $this->logs['debug'][0]['message']);
        $this->assertEquals(['bar'], $this->logs['debug'][0]['params']);
    }

    public function testInitExceptionOnErrorCreatingFunction()
    {
        $this->pdo->setError(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_CONNECTION);
        $conn = new DatabaseConnection('sqlite::memory:', false, 1, true, $this->log, $this->pdo);
    }

    public function testInitExceptionOnMissingJsonExtension()
    {
        $this->stmt->setResult(["3.37.0"]);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_NO_JSON1);
        $conn = new DatabaseConnection('sqlite::memory:', false, 1, true, $this->log, $this->pdo);
    }

    public function testInitExceptionOnMissingFtsExtension()
    {
        $this->stmt->setResult(['ENABLE_JSON1']);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_NO_FTS5);
        $conn = new DatabaseConnection('sqlite::memory:', false, 1, true, $this->log, $this->pdo);
    }

    public function testBeginTransactionTrueOnBeginTransaction()
    {
        $this->assertTrue($this->conn->beginTransaction());
    }

    public function testBeginTransactionFalseOnInTransaction()
    {
        $this->conn->beginTransaction();
        $this->assertFalse($this->conn->beginTransaction());
    }

    public function testCommitTrueOnCommit()
    {
        $this->conn->beginTransaction();
        $this->assertTrue($this->conn->commit());
    }

    public function testCommitFalseOnNotInTransaction()
    {
        $this->assertFalse($this->conn->commit());
    }

    public function testRollbackTrueOnCommit()
    {
        $this->conn->beginTransaction();
        $this->assertTrue($this->conn->rollback());
    }

    public function testRollbackFalseOnNotInTransaction()
    {
        $this->assertFalse($this->conn->rollback());
    }

    public function testValueQueryExceptionOnError()
    {
        $this->pdo->setError(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_QUERY);
        $this->conn->valueQuery('SELECT * FROM foo');
    }

    public function testValueQueryReturnsSingleResult()
    {
        $this->stmt->setResult(['abc']);
        $result = $this->conn->valueQuery('SELECT foo FROM bar');
        $this->assertSame('abc', $result);
    }

    public function testReplaceFloatPlaceholderInQueryWithCastRealJsonWrap()
    {
        $this->pdo->setError(true);
        try {
            $this->conn->valueQuery('SELECT foo FROM bar WHERE ? = ?', 'baz', 1.25);
        } catch (DatabaseException $e) {
            $query = $this->pdo->getLastQuery();
            $this->assertSame('SELECT foo FROM bar WHERE ? = cast(json(?) as real)', $query);
            return;
        }
        $this->fail('Expected DatabaseException did not occur');
    }

    public function testReplaceBoolPlaceholderInQueryWithJsonWrap()
    {
        $this->pdo->setError(true);
        try {
            $this->conn->valueQuery('SELECT foo FROM bar WHERE ? = ?', 'baz', true);
        } catch (DatabaseException $e) {
            $query = $this->pdo->getLastQuery();
            $this->assertSame('SELECT foo FROM bar WHERE ? = json(?)', $query);
            return;
        }
        $this->fail('Expected DatabaseException did not occur');
    }

    public function testExecExceptionOnError()
    {
        $this->pdo->setError(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_QUERY);
        $this->conn->exec('DELETE FROM foo');
    }

    public function testExecReturnsAffectedRows()
    {
        $this->stmt->setResult(['abc']);
        $result = $this->conn->exec('DELETE FROM bar');
        $this->assertSame(1, $result);
    }

    public function testExecutePreparedExceptionOnError()
    {
        $this->pdo->setError(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_QUERY);
        $this->conn->executePrepared('SELECT * FROM foo');
    }

    public function testExecutePreparedReturnsAffectedRows()
    {
        $this->stmt->setResult(['abc', 'def']);
        $result = $this->conn->executePrepared('SELECT foo FROM bar');
        $this->assertSame(1, $result);
    }

    public function testQueryExceptionOnError()
    {
        $this->pdo->setError(true);
        $this->expectException(DatabaseException::class);
        $this->expectExceptionCode(DatabaseException::ERR_QUERY);
        iterator_to_array($this->conn->query('SELECT * FROM foo'));
    }

    public function testQueryReturnsResultsArray()
    {
        $this->stmt->setResult(['abc', 'def']);
        $result = $this->conn->queryAll('SELECT foo FROM bar');
        $this->assertSame(['abc', 'def'], $result);
    }
}
