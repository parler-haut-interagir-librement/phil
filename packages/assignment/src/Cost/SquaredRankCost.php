<?php

declare(strict_types=1);

namespace Phil\Assignment\Cost;

use Override;
use Phil\Assignment\Contract\RankCost;

/**
 * A rank costs the square of its distance from the first choice: 0, 1, 4, 9. A bad placement weighs more than several
 * small ones, so the optimisation spreads the disappointment instead of piling it on a few people.
 */
final readonly class SquaredRankCost implements RankCost
{
    #[Override]
    public function cost(int $rank): int
    {
        return ($rank - 1) ** 2;
    }
}
