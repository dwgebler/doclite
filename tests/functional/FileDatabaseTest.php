<?php

namespace Gebler\Doclite\Tests\functional;

use Gebler\Doclite\FileDatabase;

class FileDatabaseTest extends AbstractDatabaseTest
{
    protected string $tempFile;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir();
        $tempName = uniqid("DOCLITE_FS_");
        $this->tempFile = $this->tempDir.\DIRECTORY_SEPARATOR.$tempName;
        $this->db = new FileDatabase($this->tempFile, false, true);
        parent::setup();
    }

    public function tearDown(): void
    {
        @chmod($this->tempFile, 0777);
        @unlink($this->tempFile);
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function testReadOnlyIsReadOnly()
    {
        $db = new FileDatabase($this->tempFile,true);
        $this->assertTrue($db->isReadOnly());
    }
}
