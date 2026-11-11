<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Internal\Cases;

trait AsSelectableEnumTrait
{
    /**
     * The cases as choices: each value by name when the enum is backed, the list of names when it is pure.
     *
     * @return array<string, string|int>|list<string>
     */
    public static function options(): array
    {
        return Cases::options(static::cases());
    }
}
