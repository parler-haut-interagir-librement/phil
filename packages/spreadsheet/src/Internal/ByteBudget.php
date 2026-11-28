<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

/**
 * The bytes a part of an archive may still give once uncompressed, spent by BudgetFilter as they are read.
 *
 * @internal
 */
final class ByteBudget
{
    public bool $exceeded = false;

    public function __construct(public int $remaining)
    {
    }

    public function spend(int $bytes): bool
    {
        $this->remaining -= $bytes;

        if ($this->remaining < 0) {
            $this->exceeded = true;
        }

        return !$this->exceeded;
    }
}
