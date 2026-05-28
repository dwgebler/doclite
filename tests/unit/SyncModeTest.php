<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Database;
use Gebler\Doclite\SyncMode;
use PHPUnit\Framework\TestCase;

class SyncModeTest extends TestCase
{
    public function testEnumValuesMatchLegacyConstants(): void
    {
        $this->assertSame(Database::MODE_SYNC_OFF, SyncMode::Off->value);
        $this->assertSame(Database::MODE_SYNC_NORMAL, SyncMode::Normal->value);
        $this->assertSame(Database::MODE_SYNC_FULL, SyncMode::Full->value);
        $this->assertSame(Database::MODE_SYNC_EXTRA, SyncMode::Extra->value);
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(SyncMode::tryFrom(99));
    }

    public function testAllCasesAreAccessible(): void
    {
        $this->assertSame(0, SyncMode::Off->value);
        $this->assertSame(1, SyncMode::Normal->value);
        $this->assertSame(2, SyncMode::Full->value);
        $this->assertSame(3, SyncMode::Extra->value);
    }

    public function testFromReturnsCorrectCase(): void
    {
        $this->assertSame(SyncMode::Normal, SyncMode::from(1));
        $this->assertSame(SyncMode::Full, SyncMode::from(2));
    }
}
