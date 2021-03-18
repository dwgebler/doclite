<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\Tests\Fakes\FakeFileSystem;
use Gebler\Doclite\FileSystem\FileSystemInterface;

class FakeFileSystemTest extends AbstractFileSystemTest
{
    protected function setUp(): void
    {
        $this->fs = new FakeFileSystem();
        $this->tempFile = '/foo/bar';
        $this->tempDir = '/foo/dir';
        $this->separator = '/';
    }

    /**
     * Can't reliably test these cases on the real file system.
     */

    public function testIsSqliteDatabaseThrowsExceptionOnErrorOpeningFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE | FileSystemInterface::ATTR_LOCKED,
            'SQLite format'
        );
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Failed to open file [{$this->tempFile}]");
        $this->fs->isSqliteDatabase($this->tempFile);
    }

    public function testIsSqliteDatabaseThrowsExceptionOnErrorReadingFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE | FileSystemInterface::ATTR_CORRUPTED,
            'SQLite format'
        );
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Failed to read file [{$this->tempFile}]");
        $this->fs->isSqliteDatabase($this->tempFile);
    }
}
