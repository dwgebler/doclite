<?php

/**
 * MemoryDatabase class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\FileSystem\FileSystem;
use Gebler\Doclite\FileSystem\FileSystemInterface;

/**
 * MemoryDatabase In-memory data storage
 */
class MemoryDatabase extends Database
{
    /**
     * Database constructor.
     * @param bool $ftsEnabled Whether to enable full text search support (requires FTS5 extension)
     * @param int $timeout Max time in seconds to obtain a lock
     * @param DatabaseConnection|null $dbConnection
     * @param FileSystemInterface|null $fileSystem
     * @throws DatabaseException
     */
    public function __construct(
        bool $ftsEnabled = false,
        int $timeout = 1,
        ?DatabaseConnection $dbConnection = null,
        ?FileSystemInterface $fileSystem = null
    ) {
        $dsn = 'sqlite::memory:';
        $this->conn = $dbConnection ?? new DatabaseConnection($dsn, false, $timeout, $ftsEnabled);
        $this->fileSystem = $fileSystem ?? new FileSystem();
        $this->ftsEnabled = $ftsEnabled;
    }
}
