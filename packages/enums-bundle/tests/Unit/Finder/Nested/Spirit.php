<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Finder\Nested;

/**
 * Found by the finder in a subdirectory; the class below the enum is not an enum.
 */
enum Spirit: string
{
    case Calm = 'calm';
}
