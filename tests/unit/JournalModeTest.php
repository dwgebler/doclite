<?php

declare(strict_types=1);

namespace Gebler\Doclite\Tests\unit;

use Gebler\Doclite\Database;
use Gebler\Doclite\JournalMode;
use PHPUnit\Framework\TestCase;

class JournalModeTest extends TestCase
{
    public function testEnumValuesMatchLegacyConstants(): void
    {
        $this->assertSame(Database::MODE_JOURNAL_NONE, JournalMode::None->value);
        $this->assertSame(Database::MODE_JOURNAL_MEMORY, JournalMode::Memory->value);
        $this->assertSame(Database::MODE_JOURNAL_WAL, JournalMode::Wal->value);
        $this->assertSame(Database::MODE_JOURNAL_DELETE, JournalMode::Delete->value);
        $this->assertSame(Database::MODE_JOURNAL_TRUNCATE, JournalMode::Truncate->value);
        $this->assertSame(Database::MODE_JOURNAL_PERSIST, JournalMode::Persist->value);
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(JournalMode::tryFrom('INVALID'));
    }

    public function testAllCasesAreAccessible(): void
    {
        $this->assertSame('OFF', JournalMode::None->value);
        $this->assertSame('MEMORY', JournalMode::Memory->value);
        $this->assertSame('WAL', JournalMode::Wal->value);
        $this->assertSame('DELETE', JournalMode::Delete->value);
        $this->assertSame('TRUNCATE', JournalMode::Truncate->value);
        $this->assertSame('PERSIST', JournalMode::Persist->value);
    }

    public function testFromReturnsCorrectCase(): void
    {
        $this->assertSame(JournalMode::Wal, JournalMode::from('WAL'));
        $this->assertSame(JournalMode::Memory, JournalMode::from('MEMORY'));
    }
}
