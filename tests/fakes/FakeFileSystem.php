<?php declare(strict_types=1);
/**
 * FakeFileSystem class. This serves as a verified fake of the FileSystem
 * for use in unit tests.
 */

namespace Gebler\Doclite\Tests\fakes;

use Gebler\Doclite\FileSystem\FileSystemInterface;
use Gebler\Doclite\Exception\IOException;

/**
 * FakeFileSystem
 */
class FakeFileSystem implements FileSystemInterface
{
    private array $files = [];

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
        return [];
    }

    /**
     * Add a file/directory to the file system.
     * @param string $path
     * @param int $attributes Combination of self::ATTR_ constants.
     * @param string $content
     * @return bool
     */
    public function addFile(
        string $path,
        int $attributes = self::ATTR_READABLE | self::ATTR_WRITABLE,
        string $content = ""
    ): bool {
        $this->files[$path] = [
            'content' => $content,
            'attributes' => $attributes,
        ];
        return true;
    }

    /**
     * Check bitmask flags on file.
     * @param string $path
     * @param int $attributes
     * @return bool true if all specified flags are set, e.g.
     *     checkFlags('foo', self::ATTR_READABLE | self::ATTR_DIRECTORY) : true
     *     means 'foo' has at least READABLE and DIRECTORY flags
     */
    protected function checkFlags(string $path, int $attributes): bool
    {
        if (!isset($this->files[$path])) {
            return false;
        }
        return (
            ($this->files[$path]['attributes'] & $attributes) ===
            $attributes
        );
    }


    /**
     * @inheritDoc
     */
    public function absPath(string $path): string
    {
        if (isset($this->files[$path])) {
            return $path;
        }
        throw new IOException(sprintf(
            'Path [%s] does not exist',
            $path
        ));
    }

    /**
     * @inheritDoc
     */
    public function createPath(string $path): string
    {
        if (isset($this->files[$path])) {
            throw new IOException(sprintf(
                'Path [%s] could not be created',
                $path
            ));
        }
        $this->addFile(
            dirname($path),
            self::ATTR_READABLE | self::ATTR_WRITABLE | self::ATTR_DIRECTORY
        );
        $this->addFile(
            $path,
            self::ATTR_READABLE | self::ATTR_WRITABLE
        );

        return $path;
    }

    /**
     * @inheritDoc
     */
    public function isDirectory(string $path): bool
    {
        return $this->checkFlags($path, self::ATTR_DIRECTORY);
    }

    /**
     * @inheritDoc
     */
    public function isFile(string $path): bool
    {
        if (
            isset($this->files[$path]) &&
            !$this->checkFlags($path, self::ATTR_DIRECTORY)
        ) {
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(string $path): bool
    {
        return $this->checkFlags($path, self::ATTR_READABLE);
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(string $path): bool
    {
        return $this->checkFlags($path, self::ATTR_WRITABLE);
    }

    /**
     * Read a file in to a string.
     * @param string $path
     * @return string
     * @throws IOException
     */
    public function read(string $path): string
    {
        if (!$this->isFile($path) || !$this->isReadable($path)) {
            throw new IOException(sprintf(
                'File [%s] cannot be read',
                $path
            ));
        }

        if (empty($this->files[$path]['content'])) {
            return '';
        }

        if ($this->checkFlags($path, self::ATTR_LOCKED)) {
            throw new IOException(sprintf(
                'Failed to open file [%s]',
                $path
            ));
        }

        if ($this->checkFlags($path, self::ATTR_CORRUPTED)) {
            throw new IOException(sprintf(
                'Failed to read file [%s]',
                $path
            ));
        }
        return $this->files[$path]['content'];
    }

    /**
     * @inheritDoc
     */
    public function isSqliteDatabase(string $path): bool
    {
        if (!$this->isFile($path) || !$this->isReadable($path)) {
            throw new IOException(sprintf(
                'File [%s] cannot be read',
                $path
            ));
        }

        if (empty($this->files[$path]['content'])) {
            return true;
        }

        if ($this->checkFlags($path, self::ATTR_LOCKED)) {
            throw new IOException(sprintf(
                'Failed to open file [%s]',
                $path
            ));
        }

        if ($this->checkFlags($path, self::ATTR_CORRUPTED)) {
            throw new IOException(sprintf(
                'Failed to read file [%s]',
                $path
            ));
        }

        if (
            substr($this->files[$path]['content'], 0, 13) !==
            "SQLite format"
        ) {
            return false;
        }

        return true;
    }
}
