<?php

declare(strict_types=1);

namespace Phil\Assignment\Contract;

/**
 * What placing a person at a rank of their choices costs the global optimisation, which makes the total cost of all
 * placements as low as it can. A cost is 0 to `MinCostAssigner::MAX_COST`, and should not fall as the rank grows.
 */
interface RankCost
{
    /**
     * @param positive-int $rank from 1, the most wanted
     */
    public function cost(int $rank): int;
}
