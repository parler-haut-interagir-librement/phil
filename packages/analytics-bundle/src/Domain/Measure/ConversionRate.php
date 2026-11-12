<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Goals reached per visit, both taken from aggregate counters: no visit is followed, so one visit that reaches a goal
 * twice counts twice and the rate can exceed one.
 */
final readonly class ConversionRate
{
    private function __construct(
        public int $conversions,
        public int $visits,
        public int $basisPoints,
    ) {
    }

    /**
     * The rate, or `null` when either count is under the threshold: a rate over small numbers gives them away.
     */
    public static function of(int $conversions, int $visits, SmallNumberThreshold $threshold): ?self
    {
        if ($conversions < 0 || $visits < 0) {
            throw InvalidValue::because('Conversions and visits are never negative.');
        }

        if (!$threshold->isMetBy($conversions) || !$threshold->isMetBy($visits)) {
            return null;
        }

        return new self($conversions, $visits, intdiv($conversions * 10_000, $visits));
    }
}
