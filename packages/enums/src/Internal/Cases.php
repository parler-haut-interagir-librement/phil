<?php

declare(strict_types=1);

namespace Phil\Enums\Internal;

use BackedEnum;
use InvalidArgumentException;
use UnitEnum;

/**
 * What the traits read from the cases of an enum, written once against `UnitEnum` rather than in each trait: the
 * primitive of a case is its value when the enum is backed and its name when it is pure.
 *
 * @internal
 */
final class Cases
{
    private function __construct()
    {
    }

    public static function primitive(UnitEnum $case): int|string
    {
        return $case instanceof BackedEnum ? $case->value : $case->name;
    }

    /**
     * @param list<UnitEnum> $cases
     *
     * @return list<string>
     */
    public static function names(array $cases): array
    {
        return array_map(static fn (UnitEnum $case): string => $case->name, $cases);
    }

    /**
     * @param list<UnitEnum> $cases
     *
     * @return list<int|string>
     */
    public static function primitives(array $cases): array
    {
        return array_map(self::primitive(...), $cases);
    }

    /**
     * Name to value for a backed enum, the list of names for a pure one.
     *
     * @param list<UnitEnum> $cases
     *
     * @return array<string, int|string>|list<string>
     */
    public static function options(array $cases): array
    {
        if (!($cases[0] ?? null) instanceof BackedEnum) {
            return self::names($cases);
        }

        $options = [];

        foreach ($cases as $case) {
            $options[$case->name] = self::primitive($case);
        }

        return $options;
    }

    /**
     * @param class-string<UnitEnum> $enum
     *
     * @throws InvalidArgumentException when a case is not one of the enum
     */
    public static function assertOf(string $enum, UnitEnum ...$cases): void
    {
        foreach ($cases as $case) {
            if (!$case instanceof $enum) {
                throw new InvalidArgumentException(\sprintf('%s::%s is not a case of enum %s.', $case::class, $case->name, $enum));
            }
        }
    }
}
