<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use InvalidArgumentException;

/**
 * What two raters did on the other notes they both rated: how many, and on how many they gave the same verdict.
 */
final readonly class PairHistory
{
    private function __construct(
        public int $shared,
        public int $agreed,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the counts are negative or the agreements above the notes shared
     */
    public static function of(int $shared, int $agreed): self
    {
        if ($agreed < 0 || $shared < $agreed) {
            throw new InvalidArgumentException(\sprintf('Two raters agree on 0 to %d of the notes they share, not %d.', max(0, $shared), $agreed));
        }

        return new self($shared, $agreed);
    }

    /**
     * Whether they agreed on less than the share `$numerator / $denominator` of their notes, compared in integers.
     */
    public function agreesLessThan(int $numerator, int $denominator): bool
    {
        return $this->agreed * $denominator < $numerator * $this->shared;
    }
}
