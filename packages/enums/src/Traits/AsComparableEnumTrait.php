<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

/**
 * Comparisons of a case with another value, by identity: a case equals itself and nothing else — not its value, not
 * its name, not a case of another enum.
 */
trait AsComparableEnumTrait
{
    /**
     * Whether the value is this very case.
     */
    public function is(mixed $enum): bool
    {
        return $this === $enum;
    }

    /**
     * Whether the value is anything but this case.
     */
    public function isNot(mixed $enum): bool
    {
        return $this !== $enum;
    }

    /**
     * Whether this case is among the values; an iterator is read only until the case is found.
     *
     * @param iterable<array-key, mixed> $targets
     */
    public function in(iterable $targets): bool
    {
        foreach ($targets as $candidate) {
            if ($candidate === $this) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this case is absent from the values.
     *
     * @param iterable<array-key, mixed> $targets
     */
    public function notIn(iterable $targets): bool
    {
        return !$this->in($targets);
    }

    /**
     * Whether the value is one of the enum's cases.
     */
    public static function has(mixed $target): bool
    {
        return \in_array($target, self::cases(), true);
    }

    /**
     * Whether the value is none of the enum's cases.
     */
    public static function doesntHave(mixed $target): bool
    {
        return !self::has($target);
    }

    /**
     * Whether this case is in the list.
     *
     * @param array<self> $enums
     */
    public function equalsOneOf(array $enums): bool
    {
        return \in_array($this, $enums, true);
    }

    /**
     * Whether this case is not in the list.
     *
     * @param array<self> $enums
     */
    public function notEqualsOneOf(array $enums): bool
    {
        return !$this->equalsOneOf($enums);
    }
}
