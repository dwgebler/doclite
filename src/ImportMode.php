<?php

declare(strict_types=1);

namespace Gebler\Doclite;

enum ImportMode: int
{
    case Collections = 0;
    case Documents = 1;
}
