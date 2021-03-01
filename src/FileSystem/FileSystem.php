<?php

/**
 * FileSystem class.
 */

declare(strict_types=1);

namespace Gebler\Doclite\FileSystem;

use DirectoryIterator;
use Gebler\Doclite\Exception\IOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * FileSystem
 */
class FileSystem implements FileSystemInterface
{
    /**
     * Scan a file or directory for filenames matching an extension
     * and return them.
     *
     * @param string $path Directory path
     * @param string $extension Extension to scan for.
     * @param bool $directoryMode Whether to scan sub directories.
     * @return array Either a list of files, or a dictionary of directories
     *  mapped to a list of files if in directory mode.
     */
    public function scanFiles(
        string $path,
        string $extension,
        bool $directoryMode = false
    ): array {
        if (!$this->isDirectory($path)) {
            return [];
        }
        $files = [];
        if (!$directoryMode) {
            foreach (new DirectoryIterator($path) as $item) {
                if (
                    !$item->isDot() &&
                    $item->isFile() &&
                    $item->getExtension() === $extension
                ) {
                    $files[] = $item->getPathname();
                }
            }
            return $files;
        }
        $directoryIterator = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $iterator->setMaxDepth(1);
        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPath();
            if ($item->isFile() && $item->getExtension() === $extension) {
                $files[$subPath][] = $item->getPathname();
            }
        }
        return $files;
    }

    /**
     * Add a file/directory to the file system.
     * @param string $path
     * @param int $attributes Combination of self::ATTR_ constants.
     *    Will be applied to owner only.
     * @param string $content
     * @return bool
     */
    public function addFile(
        string $path,
        int $attributes = self::ATTR_READABLE | self::ATTR_WRITABLE,
        string $content = ""
    ): bool {
        $bitmask = 0;
        $result = false;

        $isDirectory = (
            ($attributes & self::ATTR_DIRECTORY) === self::ATTR_DIRECTORY
        );

        if (($attributes & self::ATTR_READABLE) === self::ATTR_READABLE) {
            $bitmask |= self::ATTR_READABLE;
        }
        if (($attributes & self::ATTR_WRITABLE) === self::ATTR_WRITABLE) {
            $bitmask |= self::ATTR_WRITABLE;
        }

        $bitmaskOctal = octdec(sprintf('0%d44', $bitmask));

        if ($isDirectory) {
            $result = @mkdir($path, $bitmaskOctal, true);
        } else {
            $result = !(@file_put_contents(
                $path,
                $content,
                \LOCK_EX
            ) === false);
            @chmod($path, $bitmaskOctal);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function absPath(string $path): string
    {
        if (empty($realPath = realpath($path))) {
            throw new IOException(sprintf(
                'Path [%s] does not exist',
                $path
            ));
        }
        return $realPath;
    }

    /**
     * @inheritDoc
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * @inheritDoc
     */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(string $path): bool
    {
        return is_readable($path);
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(string $path): bool
    {
        return is_writable($path);
    }

    /**
     * Read a file in to a string.
     * @param string $path
     * @return string
     * @throws IOException
     */
    public function read(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new IOException(sprintf(
                'File [%s] cannot be read',
                $path
            ));
        }
        $realPath = (string)$this->absPath($path);

        if (($fp = @fopen($realPath, 'rb')) === false) {
            throw new IOException(sprintf(
                'Failed to open file [%s]',
                $realPath
            ));
        }

        try {
            if (flock($fp, \LOCK_SH)) {
                if (($data = fread($fp, filesize($realPath))) === false) {
                    throw new IOException(sprintf(
                        'Failed to read file [%s]',
                        $realPath
                    ));
                }
                return $data;
            }
        } finally {
            flock($fp, \LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @inheritDoc
     */
    public function isSqliteDatabase(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new IOException(sprintf(
                'File [%s] cannot be read',
                $path
            ));
        }

        $realPath = (string)$this->absPath($path);

        // Allow a zero-byte file to count as a DB, since it will
        // generate no complaints from SQLite.
        if (@filesize($realPath) === 0) {
            return true;
        }

        if (($fp = @fopen($realPath, 'rb')) === false) {
            throw new IOException(sprintf(
                'Failed to open file [%s]',
                $realPath
            ));
        }

        try {
            if (flock($fp, \LOCK_SH)) {
                if (($data = fread($fp, 16)) === false) {
                    throw new IOException(sprintf(
                        'Failed to read file [%s]',
                        $realPath
                    ));
                }
                if (substr($data, 0, 13) !== "SQLite format") {
                    return false;
                }
            }
        } finally {
            flock($fp, \LOCK_UN);
            fclose($fp);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function createPath(string $path): string
    {
        $dirName = dirname($path);
        $fileName = basename($path);
        if (!is_dir($dirName)) {
            if (!@mkdir($dirName, 0644, true)) {
                throw new IOException(sprintf(
                    'Path [%s] could not be created',
                    $path
                ));
            }
        }
        return realpath($dirName) . \DIRECTORY_SEPARATOR . $fileName;
    }
}
