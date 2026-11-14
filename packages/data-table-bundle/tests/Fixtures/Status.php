<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Live = 'live';
}
