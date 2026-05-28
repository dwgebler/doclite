<?php

declare(strict_types=1);

namespace Gebler\Doclite;

enum JournalMode: string
{
    case None = 'OFF';
    case Memory = 'MEMORY';
    case Wal = 'WAL';
    case Delete = 'DELETE';
    case Truncate = 'TRUNCATE';
    case Persist = 'PERSIST';
}
