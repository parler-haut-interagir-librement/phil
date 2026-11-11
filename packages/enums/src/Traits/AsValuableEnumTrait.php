<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Internal\Cases;

trait AsValuableEnumTrait
{
    /**
     * The primitives of the cases, in declaration order: their values when the enum is backed, their names when it
     * is pure.
     *
     * @return list<string|int>
     */
    public static function values(): array
    {
        return Cases::primitives(static::cases());
    }
}
