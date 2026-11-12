<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Alert;

use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Whether a day's count stands out from the days before it. A site whose usual day is under the minimum raises no
 * alert: on a handful of visits, every day looks like a spike.
 */
final readonly class TrafficAlertRule
{
    public function __construct(
        private int $spikePercent = 300,
        private int $dipPercent = 30,
        private int $minimumBaseline = 20,
    ) {
        if ($spikePercent <= 100) {
            throw InvalidValue::because(\sprintf('A spike is above 100 %% of the usual day; %d was given.', $spikePercent));
        }

        if ($dipPercent < 1 || $dipPercent >= 100) {
            throw InvalidValue::because(\sprintf('A dip is between 1 and 99 %% of the usual day; %d was given.', $dipPercent));
        }

        if ($minimumBaseline < 1) {
            throw InvalidValue::because(\sprintf('The minimum usual day is at least 1; %d was given.', $minimumBaseline));
        }
    }

    /**
     * @param list<int> $previousDays the same counter on the days before, the longer the steadier
     */
    public function evaluate(SiteId $site, UtcDay $day, int $count, array $previousDays): ?TrafficAlert
    {
        if ([] === $previousDays) {
            return null;
        }

        $baseline = intdiv(array_sum($previousDays), \count($previousDays));

        if ($baseline < $this->minimumBaseline) {
            return null;
        }

        return match (true) {
            $count * 100 >= $baseline * $this->spikePercent => new TrafficAlert($site, $day, TrafficAlertKind::Spike, $count, $baseline),
            $count * 100 <= $baseline * $this->dipPercent => new TrafficAlert($site, $day, TrafficAlertKind::Dip, $count, $baseline),
            default => null,
        };
    }
}
