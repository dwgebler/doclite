<?php

/**
 * DatabaseException class.
 */

declare(strict_types=1);

namespace Gebler\Doclite\Exception;

/**
 * DatabaseException
 */
class DatabaseException extends \Exception
{
    public const ERR_CONNECTION = 0;
    public const ERR_QUERY = 1;
    public const ERR_INVALID_COLLECTION = 2;
    public const ERR_CLASS_NOT_FOUND = 3;
    public const ERR_MISSING_ID_FIELD = 4;
    public const ERR_ID_CONFLICT = 5;
    public const ERR_INVALID_UUID = 6;
    public const ERR_INVALID_ID_FIELD = 7;
    public const ERR_NO_SQLITE = 8;
    public const ERR_NO_JSON1 = 9;
    public const ERR_INVALID_FIND_CRITERIA = 10;
    public const ERR_COLLECTION_IN_TRANSACTION = 11;
    public const ERR_READ_ONLY_MODE = 12;
    public const ERR_INVALID_JSON_SCHEMA = 13;
    public const ERR_INVALID_DATA = 14;
    public const ERR_MAPPING_DATA = 15;
    public const ERR_IMPORT_DATA = 16;
    public const ERR_IN_TRANSACTION = 17;
    public const ERR_INVALID_TABLE = 18;
    public const ERR_NO_FTS5 = 19;

    private string $query;
    private array $params;

    public function __construct(
        string $message,
        int $code = 0,
        \Throwable $e = null,
        string $query = "",
        array $params = []
    ) {
        parent::__construct($message, $code, $e);
        $this->query = $query;
        $this->params = $params;
    }

    /**
     * Get query
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Get params
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
