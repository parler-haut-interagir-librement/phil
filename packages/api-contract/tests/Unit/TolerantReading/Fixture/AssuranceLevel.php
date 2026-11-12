<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\TolerantReading\Fixture;

enum AssuranceLevel: string
{
    case Basic = 'basic';
    case Strong = 'strong';
}
