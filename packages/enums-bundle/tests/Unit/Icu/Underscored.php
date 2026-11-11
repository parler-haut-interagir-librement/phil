<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * Values ICU takes as keys.
 */
enum Underscored: string
{
    case InProgress = 'in_progress';
    case Done = 'done';
}
