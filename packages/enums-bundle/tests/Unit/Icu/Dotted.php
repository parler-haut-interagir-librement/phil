<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * A version as value, whose dot ICU refuses in a key.
 */
enum Dotted: string
{
    case First = 'v1.2';
}
