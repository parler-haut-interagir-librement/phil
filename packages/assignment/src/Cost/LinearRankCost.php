<?php

declare(strict_types=1);

namespace Phil\Assignment\Cost;

use Override;
use Phil\Assignment\Contract\RankCost;

/**
 * Each rank costs one more than the one before: 0 for the first choice, 1 for the second. The optimisation then makes
 * the sum of ranks as small as it can, and may give one person their fifth choice to give four others their first.
 */
final readonly class LinearRankCost implements RankCost
{
    #[Override]
    public function cost(int $rank): int
    {
        return $rank - 1;
    }
}
