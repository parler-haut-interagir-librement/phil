<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Query;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';
}
