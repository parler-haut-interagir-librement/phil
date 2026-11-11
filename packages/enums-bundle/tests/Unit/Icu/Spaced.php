<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * A value with a space, which ICU refuses in a key.
 */
enum Spaced: string
{
    case Open = 'a b';
}
