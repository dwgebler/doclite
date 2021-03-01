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
     * @param DatabaseConnection|null $dbConnection
     * @param FileSystemInterface|null $fileSystem
     * @throws DatabaseException
     */
    public function __construct(?DatabaseConnection $dbConnection = null, ?FileSystemInterface $fileSystem = null)
    {
        $dsn = 'sqlite::memory:';
        $this->conn = $dbConnection ?? new DatabaseConnection($dsn);
        $this->fileSystem = $fileSystem ?? new FileSystem();
    }
}
