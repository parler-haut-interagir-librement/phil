<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * Two pure cases whose names give the same snake-case key.
 */
enum Twins
{
    case FooBar;
    case Foo_Bar;
}
