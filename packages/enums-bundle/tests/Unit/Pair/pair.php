<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Pair;

/*
 * Two enums in one file no autoloader maps: they exist once the test has loaded the file.
 */

enum Left
{
    case One;
}

enum Right
{
    case One;
}
