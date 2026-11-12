<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Alert;

use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Application\Contract\TrafficAlertNotifier;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlert;
use Phil\AnalyticsBundle\Domain\Alert\TrafficAlertRule;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The visits of the day before, for each watched site, compared with the days before it. Run once a day: the day
 * checked is the last whole UTC day, and a site whose day stands out is handed to the notifier.
 *
 * It reads the counters and writes nothing; the work is bounded by the watched sites times the baseline days.
 */
final readonly class TrafficAlertRun
{
    public const int MIN_BASELINE_DAYS = 7;

    public const int MAX_BASELINE_DAYS = 366;

    /**
     * @var list<SiteId>
     */
    private array $sites;

    /**
     * @param iterable<string> $sites        the references of the watched sites
     * @param int              $baselineDays the days before the checked one its usual day is the mean of
     */
    public function __construct(
        private CounterStore $store,
        private TrafficAlertRule $rule,
        private TrafficAlertNotifier $notifier,
        iterable $sites,
        private int $baselineDays = 28,
    ) {
        if ($baselineDays < self::MIN_BASELINE_DAYS || $baselineDays > self::MAX_BASELINE_DAYS) {
            throw InvalidValue::because(\sprintf('The usual day is the mean of %d to %d days; %d was given.', self::MIN_BASELINE_DAYS, self::MAX_BASELINE_DAYS, $baselineDays));
        }

        $list = [];

        foreach ($sites as $site) {
            $list[] = SiteId::fromString($site);
        }

        $this->sites = $list;
    }

    /**
     * @return list<TrafficAlert> the alerts sent
     */
    public function checkFor(UtcDay $today): array
    {
        $alerts = [];

        foreach ($this->sites as $site) {
            $day = UtcDay::of($today->start()->modify(\sprintf('-%d days', $this->baselineDays + 1)));
            $previous = [];

            for ($i = 0; $i < $this->baselineDays; ++$i) {
                $previous[] = $this->visitsOn($site, $day);
                $day = $day->next();
            }

            $alert = $this->rule->evaluate($site, $day, $this->visitsOn($site, $day), $previous);

            if ($alert instanceof TrafficAlert) {
                $this->notifier->notify($alert);
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    private function visitsOn(SiteId $site, UtcDay $day): int
    {
        return array_sum($this->store->counts($site, MeasureNames::VISITS, 'source', $day, $day));
    }
}
