<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

/**
 * The parts left to read in one message: one budget per call to the reader, never shared.
 *
 * @internal
 */
final class PartBudget
{
    public function __construct(
        private int $left,
    ) {
    }

    public function take(): void
    {
        --$this->left;
    }

    public function left(): int
    {
        return max(0, $this->left);
    }

    public function available(): bool
    {
        return $this->left > 0;
    }
}
