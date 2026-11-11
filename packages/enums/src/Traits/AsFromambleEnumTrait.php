<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use UnitEnum;
use ValueError;

/**
 * A case found by its name: `fromName()` and `tryFromName()` on every enum, and — on a pure enum, which has no
 * `from()` of its own — `from()` and `tryFrom()` as the same lookup. A backed enum keeps PHP's `from()` and
 * `tryFrom()`, which read its values.
 */
trait AsFromambleEnumTrait
{
    /**
     * The case of a pure enum with this name.
     *
     * @throws ValueError when no case has it
     */
    public static function from(int|string $case): static
    {
        return static::fromName((string) $case);
    }

    /**
     * The case of a pure enum with this name, or `null`.
     */
    public static function tryFrom(int|string $case): ?static
    {
        return static::tryFromName((string) $case);
    }

    /**
     * The case with this name.
     *
     * @throws ValueError when no case has it
     */
    public static function fromName(string $case): static
    {
        return static::tryFromName($case) ?? throw new ValueError(\sprintf('"%s" is not a valid name for enum %s', $case, self::class));
    }

    /**
     * The case with this name, or `null`; names are compared exactly, letter case included.
     */
    public static function tryFromName(string $case): ?static
    {
        return array_find(static::cases(), static fn (UnitEnum $candidate): bool => $candidate->name === $case);
    }
}
