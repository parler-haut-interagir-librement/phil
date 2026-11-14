<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

use BackedEnum;

/**
 * The choices of an enum column or filter, value to label, from a list or from a backed enum.
 */
final readonly class Choices
{
    /**
     * @param array<int|string, string>|class-string<BackedEnum> $choices
     *
     * @return array<int|string, string>
     */
    public static function of(array|string $choices): array
    {
        if (\is_array($choices)) {
            return $choices;
        }

        $labels = [];

        foreach ($choices::cases() as $case) {
            $labels[$case->value] = $case->name;
        }

        return $labels;
    }
}
