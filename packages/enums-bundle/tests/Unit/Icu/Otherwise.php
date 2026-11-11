<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Icu;

/**
 * A value that is ICU's own `other` branch.
 */
enum Otherwise: string
{
    case Known = 'known';
    case Other = 'other';
}
