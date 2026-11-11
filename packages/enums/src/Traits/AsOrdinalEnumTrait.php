<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

/**
 * The cases in their declaration order, as ordinals: the position of a case, the case before and after it, and
 * comparisons by position — for statuses that follow one another, levels, priorities.
 *
 * **The order is the source file's.** Reordering the cases changes every ordinal: store the value of a case, never
 * its ordinal.
 */
trait AsOrdinalEnumTrait
{
    /**
     * The position of the case, from 0.
     */
    public function ordinal(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * The case declared after this one; `null` after the last, or the first when `$cycle` is true.
     */
    public function next(bool $cycle = false): ?self
    {
        $cases = self::cases();

        return $cases[$this->ordinal() + 1] ?? ($cycle ? $cases[0] : null);
    }

    /**
     * The case declared before this one; `null` before the first, or the last when `$cycle` is true.
     */
    public function previous(bool $cycle = false): ?self
    {
        $cases = self::cases();

        return $cases[$this->ordinal() - 1] ?? ($cycle ? $cases[\count($cases) - 1] : null);
    }

    /**
     * Less than 0, 0 or more than 0 as this case is declared before, is, or is declared after the other.
     */
    public function compareTo(self $other): int
    {
        return $this->ordinal() <=> $other->ordinal();
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Whether the case is declared between the two, both included.
     */
    public function isBetween(self $from, self $to): bool
    {
        return !$this->isBefore($from) && !$this->isAfter($to);
    }
}
