<?php

declare(strict_types=1);

namespace Gebler\Doclite;

enum SyncMode: int
{
    case Off = 0;
    case Normal = 1;
    case Full = 2;
    case Extra = 3;
}
