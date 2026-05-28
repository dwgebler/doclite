<?php

/**
 * MemoryDatabase class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Connection\DatabaseConnectionInterface;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\FileSystem\FileSystem;
use Gebler\Doclite\FileSystem\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * MemoryDatabase In-memory data storage
 */
final class MemoryDatabase extends Database
{
    /**
     * Database constructor.
     * @param bool $ftsEnabled Whether to enable full text search support (requires FTS5 extension)
     * @param int $timeout Max time in seconds to obtain a lock
     * @param LoggerInterface|null $logger
     * @param DatabaseConnectionInterface|null $dbConnection
     * @param FileSystemInterface|null $fileSystem
     * @throws DatabaseException
     */
    public function __construct(
        bool $ftsEnabled = false,
        int $timeout = 5,
        ?LoggerInterface $logger = null,
        ?DatabaseConnectionInterface $dbConnection = null,
        ?FileSystemInterface $fileSystem = null
    ) {
        $dsn = 'sqlite::memory:';
        $this->conn = $dbConnection ?? new DatabaseConnection($dsn, false, $timeout, $ftsEnabled, $logger);
        $this->fileSystem = $fileSystem ?? new FileSystem();
        $this->ftsEnabled = $ftsEnabled;
    }
}
