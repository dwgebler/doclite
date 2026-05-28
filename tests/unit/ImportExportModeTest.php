<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Database;
use Gebler\Doclite\ExportMode;
use Gebler\Doclite\ImportMode;
use PHPUnit\Framework\TestCase;

class ImportExportModeTest extends TestCase
{
    public function testImportEnumValuesMatchLegacyConstants(): void
    {
        $this->assertSame(Database::MODE_IMPORT_COLLECTIONS, ImportMode::Collections->value);
        $this->assertSame(Database::MODE_IMPORT_DOCUMENTS, ImportMode::Documents->value);
    }

    public function testExportEnumValuesMatchLegacyConstants(): void
    {
        $this->assertSame(Database::MODE_EXPORT_COLLECTIONS, ExportMode::Collections->value);
        $this->assertSame(Database::MODE_EXPORT_DOCUMENTS, ExportMode::Documents->value);
    }

    public function testImportTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(ImportMode::tryFrom(99));
    }

    public function testExportTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(ExportMode::tryFrom(99));
    }

    public function testImportFromReturnsCorrectCase(): void
    {
        $this->assertSame(ImportMode::Collections, ImportMode::from(0));
        $this->assertSame(ImportMode::Documents, ImportMode::from(1));
    }

    public function testExportFromReturnsCorrectCase(): void
    {
        $this->assertSame(ExportMode::Collections, ExportMode::from(0));
        $this->assertSame(ExportMode::Documents, ExportMode::from(1));
    }
}
