<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Internal\Cases;

trait AsNameableEnumTrait
{
    /**
     * The names of the cases, in declaration order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return Cases::names(static::cases());
    }
}
