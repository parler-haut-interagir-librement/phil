<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;

/**
 * The units sold or used in each period of the same length — usually a day —, oldest first, from 1 to 3,660
 * periods.
 */
final readonly class DemandHistory
{
    public const int MAX_PERIODS = 3_660;

    /** @var non-empty-list<float> */
    public array $periods;

    /**
     * @param list<int|float> $periods
     */
    public function __construct(array $periods)
    {
        if ([] === $periods || \count($periods) > self::MAX_PERIODS) {
            throw InvalidInput::because('demand history', 'must have 1 to 3,660 periods');
        }

        $values = [];

        foreach ($periods as $demand) {
            $values[] = Check::quantity('demand of a period', (float) $demand);
        }

        $this->periods = $values;
    }

    /**
     * The mean demand of a period.
     */
    public function mean(): float
    {
        return array_sum($this->periods) / \count($this->periods);
    }

    /**
     * The sample standard deviation of the demand of a period; 0 for a single period.
     */
    public function standardDeviation(): float
    {
        $count = \count($this->periods);

        if (1 === $count) {
            return 0.0;
        }

        $mean = $this->mean();
        $squares = 0.0;

        foreach ($this->periods as $demand) {
            $squares += ($demand - $mean) ** 2;
        }

        return sqrt($squares / ($count - 1));
    }

    /**
     * The largest demand of a period.
     */
    public function max(): float
    {
        return max($this->periods);
    }
}
