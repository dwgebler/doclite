<?php

/**
 * FakePDO class. Adds the sqliteCreateFunction method to the PHPUnit PDO mock.
 */

namespace Gebler\Doclite\Tests\fakes;

/**
 * FakePDO
 */
class FakePDO extends \PDO
{
    private $error = false;
    private $stmt;
    private $isInTransaction = false;
    private $lastQuery = '';

    public function __construct(FakePDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    #[\ReturnTypeWillChange]
    public function inTransaction()
    {
        return $this->isInTransaction;
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)
    {
        if ($this->error) {
            throw new \PDOException();
        }
        return $this->stmt;
    }

    #[\ReturnTypeWillChange]
    public function exec(string $statement)
    {
        if ($this->error) {
            throw new \PDOException();
        }
        return 1;
    }

    #[\ReturnTypeWillChange]
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $statement, array $driver_options = [])
    {
        $this->lastQuery = $statement;
        if ($this->error) {
            throw new \PDOException();
        }
        return $this->stmt;
    }

    #[\ReturnTypeWillChange]
    public function beginTransaction()
    {
        if (!$this->isInTransaction) {
            $this->isInTransaction = true;
            return true;
        }
        return false;
    }

    #[\ReturnTypeWillChange]
    public function commit()
    {
        if ($this->isInTransaction) {
            $this->isInTransaction = false;
            return true;
        }
        return false;
    }

    #[\ReturnTypeWillChange]
    public function rollBack()
    {
        if ($this->isInTransaction) {
            $this->isInTransaction = false;
            return true;
        }
        return false;
    }

    public function setError(bool $error)
    {
        $this->error = $error;
    }

    public function sqliteCreateFunction($function_name, $callback, $num_args = -1, $flags = 0)
    {
        if ($this->error) {
            throw new \PDOException();
        }
    }
}