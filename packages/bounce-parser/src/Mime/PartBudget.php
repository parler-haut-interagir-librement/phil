<?php

declare(strict_types=1);

namespace Phil\BounceParser\Mime;

/**
 * The parts left to read in one message: one budget per call to the parser, never shared.
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

    public function available(): bool
    {
        return $this->left > 0;
    }
}
