<?php

/**
 * FileDatabase class.
 */

declare(strict_types=1);

namespace Gebler\Doclite;

use Gebler\Doclite\Connection\DatabaseConnection;
use Gebler\Doclite\Exception\DatabaseException;
use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\FileSystem\FileSystem;
use Gebler\Doclite\FileSystem\FileSystemInterface;

/**
 * FileDatabase
 */
class FileDatabase extends Database
{
    /**
     * Default filename.
     * @var string
     */
    private const DEFAULT_FILENAME = 'data.db';

    /**
     * Database constructor.
     * @param string $path Path to DB directory or file
     * @param bool $readOnly Open DB in read only mode
     * @param bool $ftsEnabled Whether to enable full text search support (requires FTS5 extension)
     * @param int $timeout Max time in seconds to obtain a lock
     * @param DatabaseConnection|null $dbConnection
     * @param FileSystemInterface|null $fileSystem
     * @throws DatabaseException if DB connection cannot be established
     * @throws IOException if path is not valid and writeable
     */
    public function __construct(
        string $path,
        bool $readOnly = false,
        bool $ftsEnabled = false,
        int $timeout = 1,
        ?DatabaseConnection $dbConnection = null,
        ?FileSystemInterface $fileSystem = null
    ) {
        $this->fileSystem = $fileSystem ?? new FileSystem();
        $validatedPath = $this->validatePath($path);
        $dsn = 'sqlite:' . $validatedPath;
        $this->conn = $dbConnection ?? new DatabaseConnection($dsn, $readOnly, $timeout, $ftsEnabled);
        $this->readOnly = $readOnly;
        $this->ftsEnabled = $ftsEnabled;
        $this->setJournalMode(self::MODE_JOURNAL_WAL);
    }

    /**
     * Validate database path is one of:
     *  - An extant, writeable directory path (default filename will be used)
     *  - An extant, writeable directory path and non-existent filename (will be created).
     *  - A non-existent directory path and non-existent filename (will be created).
     *  - An extant, readable and writeable file path which appears to be a valid SQLite database.
     * @param string $path
     * @return string Fully qualified path
     * @throws IOException if any read/write conditions on the supplied path fail
     */
    private function validatePath(string $path): string
    {
        $realPath = rtrim($path, "/");
        if (strlen($path) < 1) {
            throw new IOException('Path cannot be blank');
        }
        if ($this->fileSystem->isDirectory($realPath)) {
            $realPath = $this->fileSystem->absPath($realPath);
            if (!$this->fileSystem->isWriteable($realPath)) {
                throw new IOException(sprintf('Cannot write to database directory [%s]', $realPath));
            }
            $realPath .= \DIRECTORY_SEPARATOR . self::DEFAULT_FILENAME;
        }

        if ($this->fileSystem->isFile($realPath)) {
            $realPath = $this->fileSystem->absPath($realPath);
            if (!$this->fileSystem->isReadable($realPath)) {
                throw new IOException(sprintf('Database file [%s] exists but is not readable', $realPath));
            }
            if (!$this->fileSystem->isWriteable($realPath)) {
                throw new IOException(sprintf('Database file [%s] exists but is not writeable', $realPath));
            }
            if (!$this->fileSystem->isSqliteDatabase($realPath)) {
                throw new IOException(sprintf('Database file [%s] exists but is not a DocLite database', $realPath));
            }
        } else {
            if (!$this->fileSystem->createPath($realPath)) {
                throw new IOException(sprintf('Database file [%s] could not be created', $realPath));
            }
        }
        return $realPath;
    }
}
