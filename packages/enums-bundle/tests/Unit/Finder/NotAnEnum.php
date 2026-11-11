<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Finder;

/**
 * A class, whose method is named `enum` and which names an enum: none of it is an enum declaration.
 */
final class NotAnEnum
{
    public function enum(): string
    {
        return 'enum';
    }

    public function isGhost(mixed $value): bool
    {
        return $value instanceof Ghost;
    }
}
