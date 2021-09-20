<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\Exception\IOException;
use Gebler\Doclite\FileSystem\FileSystem;
use Gebler\Doclite\FileSystem\FileSystemInterface;

use Gebler\Doclite\Tests\unit\AbstractFileSystemTest;

class FileSystemTest extends AbstractFileSystemTest
{
    protected function setUp(): void
    {
        $this->fs = new FileSystem();
        $tempDir = sys_get_temp_dir();
        $tempName = uniqid("DOCLITE_FS_");
        $this->tempFile = $tempDir.\DIRECTORY_SEPARATOR.$tempName;
        $this->tempDir = $tempDir.\DIRECTORY_SEPARATOR.uniqid("DOCLITE_FS_");
        $this->separator = \DIRECTORY_SEPARATOR;
    }

    public function tearDown(): void
    {
        @chmod($this->tempFile, 0777);
        @unlink($this->tempFile);
        @chmod($this->tempDir, 0777);
        @rmdir($this->tempDir);
    }

    protected function skipOnWindows()
    {
        if (\PHP_OS_FAMILY == "Windows") {
            $this->markTestSkipped('Windows file ACL not compatible with test');
        }
    }
}
