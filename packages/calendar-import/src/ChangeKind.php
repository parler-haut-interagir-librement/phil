<?php

declare(strict_types=1);

namespace Phil\CalendarImport;

enum ChangeKind: string
{
    case Added = 'added';
    case Changed = 'changed';
    case Removed = 'removed';
}
