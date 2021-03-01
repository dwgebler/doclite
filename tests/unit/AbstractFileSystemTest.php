<?php

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\FileSystem\FileSystemInterface;

use PHPUnit\Framework\TestCase;

abstract class AbstractFileSystemTest extends TestCase
{
    protected FileSystemInterface $fs;
    protected string $tempFile;
    protected string $tempDir;
    protected string $separator;

    protected function skipOnWindows()
    {
    }

    public function testIsSqliteDatabaseThrowsExceptionOnNonExistentFile()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("File [{$this->tempFile}] cannot be read");
        $this->fs->isSqliteDatabase($this->tempFile);
    }

    public function testIsSqliteDatabaseThrowsExceptionOnUnreadableFile()
    {
        $this->skipOnWindows();
        $this->fs->addFile($this->tempFile, FileSystemInterface::ATTR_WRITABLE);
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("File [{$this->tempFile}] cannot be read");
        $this->fs->isSqliteDatabase($this->tempFile);
    }

    public function testIsSqliteDatabaseReturnsTrueOnEmptyFile()
    {
        $this->fs->addFile($this->tempFile);
        $this->assertTrue($this->fs->isSqliteDatabase($this->tempFile));
    }

    public function testIsSqliteDatabaseReturnsTrueOnSqliteFormatFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE,
            'SQLite format'
        );
        $this->assertTrue($this->fs->isSqliteDatabase($this->tempFile));
    }

    public function testIsSqliteDatabaseReturnsFalseOnNonSqliteFormatFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE,
            'Not SQLite format'
        );
        $this->assertFalse($this->fs->isSqliteDatabase($this->tempFile));
    }


    public function testIsReadableReturnsTrueOnReadableFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE,
        );
        $this->assertTrue($this->fs->isReadable($this->tempFile));
    }

    public function testIsReadableReturnsFalseOnUnreadableFile()
    {
        $this->skipOnWindows();
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_WRITABLE,
        );
        $this->assertFalse($this->fs->isReadable($this->tempFile));
    }

    public function testIsDirectoryReturnsTrueOnDirectory()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_DIRECTORY,
        );
        $this->assertTrue($this->fs->isDirectory($this->tempFile));
    }

    public function testIsDirectoryReturnsFalseOnFile()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_WRITABLE,
        );
        $this->assertFalse($this->fs->isDirectory($this->tempFile));
    }

    public function testAbsPathThrowsExceptionOnNonExistentPath()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Path [{$this->tempFile}] does not exist");
        $this->fs->absPath($this->tempFile);
    }

    public function testAbsPathReturnsAbsolutePath()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE,
        );
        $this->assertSame($this->tempFile, $this->fs->absPath($this->tempFile));
    }

    public function testIsWriteableReturnsTrueOnWriteableFile()
    {
        $this->fs->addFile($this->tempFile);
        $this->assertTrue($this->fs->isWriteable($this->tempFile));
    }

    public function testIsWriteableReturnsFalseOnReadOnlyFile()
    {
        $this->skipOnWindows();
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_READABLE,
        );
        $this->assertFalse($this->fs->isWriteable($this->tempFile));
    }

    public function testIsFileReturnsTrueOnExistingFile()
    {
        $this->fs->addFile($this->tempFile);
        $this->assertTrue($this->fs->isFile($this->tempFile));
    }

    public function testIsFileReturnsFalseOnNonExistingFile()
    {
        $this->assertFalse($this->fs->isFile($this->tempFile));
    }

    public function testIsFileReturnsFalseOnDirectory()
    {
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_DIRECTORY,
        );
        $this->assertFalse($this->fs->isFile($this->tempFile));
    }

    public function testCreatePathThrowsIOExceptionOnErrorCreatingDirectories()
    {
        $this->skipOnWindows();
        $this->fs->addFile(
            $this->tempFile,
            FileSystemInterface::ATTR_DIRECTORY,
        );
        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Path ['.$this->tempFile.'] could not be created');
        $this->fs->createPath($this->tempFile);
    }

    public function testCreatePathReturnsAbsolutePathCreated()
    {
        $this->fs->addFile(
            $this->tempDir,
            FileSystemInterface::ATTR_DIRECTORY
        );
        $this->assertEquals(
            $this->tempDir.$this->separator.'created',
            $this->fs->createPath($this->tempDir.'/created')
        );
    }
}
