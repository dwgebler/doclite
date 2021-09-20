<?php

/**
 * FileSystemInterface class.
 */

declare(strict_types=1);

namespace Gebler\Doclite\FileSystem;

use Gebler\Doclite\Exception\IOException;

/**
 * FileSystemInterface
 */
interface FileSystemInterface
{
    /**
     * These are internal flags, not chmod permissions.
     */
    public const ATTR_DIRECTORY  = 1;
    public const ATTR_READABLE   = 2;
    public const ATTR_WRITABLE   = 4;
    public const ATTR_LOCKED     = 8;
    public const ATTR_CORRUPTED  = 16;

    /**
     * Get the canonicalized path name.
     * @param string $path
     * @throws IOException if the path does not exist.
     * @return string
     */
    public function absPath(string $path): string;
    /**
     * Create the directories for a non-existent file path.
     * @param string $path
     * @throws IOException if the specified path cannot be created
     * @return string absolute path created
     */
    public function createPath(string $path): string;
    /**
     * Check if the given path is an extant directory.
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool;
    /**
     * Check if the given path is an extant file.
     * @param string $path
     * @return bool
     */
    public function isFile(string $path): bool;
    /**
     * Check if the given path is readable.
     * @param string $path
     * @return bool
     */
    public function isReadable(string $path): bool;
    /**
     * Check if the given path is writeable.
     * @param string $path
     * @return bool
     */
    public function isWriteable(string $path): bool;
    /**
     * Check if the given file exists and appears to be a SQLite database.
     * @param string $path
     * @throws IOException if the specified file does not exist or
     *  cannot be read.
     * @return bool
     */
    public function isSqliteDatabase(string $path): bool;
}
