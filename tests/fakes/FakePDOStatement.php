<?php
/**
 * FakePDOStatement class.
 */

namespace Gebler\Doclite\Tests\fakes;

/**
 * FakePDOStatement
 */
class FakePDOStatement extends \PDOStatement
{
    private array $result = [];

    public function setResult(array $data)
    {
        $this->result = $data;
    }

    #[\ReturnTypeWillChange]
    public function closeCursor()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function rowCount()
    {
        return 1;
    }

    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = 0, ...$args): array
    {
        return $this->result;
    }

    #[\ReturnTypeWillChange]
    public function fetch(int $mode = 0, int $cor = 0, int $cof = 0)
    {
        return $this->result;
    }

    #[\ReturnTypeWillChange]
    public function fetchColumn($column = 0)
    {
        if (!empty($this->result)) {
            return $this->result[0];
        }
        return false;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function bindValue($param, $value, $type = 1)
    {
        return true;
    }
}