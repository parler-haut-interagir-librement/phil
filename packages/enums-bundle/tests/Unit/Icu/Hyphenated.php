<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * Values a person writes with a hyphen, which ICU refuses as keys.
 */
enum Hyphenated: string
{
    case InProgress = 'in-progress';
    case Done = 'done';
}
