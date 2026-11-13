<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * What the check at the return found: the movements that brought pieces back — and wrote off the missing ones when
 * asked —, the items missing, and the items counted beyond what was out.
 */
final readonly class ReturnCheck
{
    /**
     * @param list<Movement>        $movements
     * @param list<Shortfall>       $missing
     * @param array<array-key, int> $surplus   the pieces counted beyond what was out, by item
     */
    public function __construct(
        public array $movements,
        public array $missing,
        public array $surplus,
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->missing;
    }
}
