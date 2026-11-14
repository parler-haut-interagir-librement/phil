<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

enum EditStatus
{
    case Saved;
    case Invalid;
    case Conflict;
}
