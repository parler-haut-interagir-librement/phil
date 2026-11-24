<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Cost;

/**
 * How the shop's own units are valued as they leave: the application picks one in its configuration.
 */
enum CostingMethod: string
{
    /**
     * One pool per SKU and location, its total value over its units: a unit leaves at the pool's average, and a
     * receipt changes the average.
     */
    case WeightedAverage = 'weighted_average';

    /**
     * One layer per receipt, per SKU and location: units leave from the oldest layer first, at that layer's cost.
     */
    case FirstInFirstOut = 'fifo';
}
