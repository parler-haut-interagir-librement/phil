<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Reading;

use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\Reading\SuppressedCounts;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What a dashboard reads: the counters of one metric and one dimension over a range of UTC days, for one site or
 * summed over several, always under the small-number rule and always within the reader's {@see ReaderScope}.
 *
 * **One service for every site.** A site's administrators read their own sites; whoever runs the platform the sites
 * share reads them all, and the sum across them. A site outside the scope reads as `null`, the answer for a site that
 * does not exist, which a controller turns into a `404`.
 *
 * The exact count of each outbound link is not read here: only its author and the administrators see it, through
 * {@see \Phil\AnalyticsBundle\Domain\Reading\LinkCountReader}. The work is bounded by {@see MAX_DAYS} days and
 * {@see ReaderScope::MAX_SITES} sites, one store query per site.
 */
final readonly class CounterReader
{
    /**
     * The longest range read at once: the 13 months of daily counters the retention keeps by default, and a little more.
     */
    public const int MAX_DAYS = 400;

    public function __construct(
        private CounterStore $store,
        private SmallNumberSuppression $suppression,
    ) {
    }

    /**
     * @throws InvalidValue when the range is reversed or longer than {@see MAX_DAYS} days, or the metric is the count
     *                      of each outbound link
     */
    public function read(ReaderScope $scope, SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): ?SuppressedCounts
    {
        $this->assertReadable($metric, $from, $to);

        if (!$scope->mayRead($site)) {
            return null;
        }

        return $this->suppression->apply($this->store->counts($site, $metric, $dimension, $from, $to));
    }

    /**
     * The counters of the given sites summed per dimension value, the small-number rule applied to the sums. A site the
     * scope does not cover is left out, as is a site given twice.
     *
     * @param iterable<SiteId> $sites
     *
     * @throws InvalidValue when the range is reversed or longer than {@see MAX_DAYS} days, the metric is the count of
     *                      each outbound link, or more than {@see ReaderScope::MAX_SITES} sites are given
     */
    public function readAcross(ReaderScope $scope, iterable $sites, string $metric, string $dimension, UtcDay $from, UtcDay $to): SuppressedCounts
    {
        $this->assertReadable($metric, $from, $to);

        $read = [];
        $sums = [];

        foreach ($sites as $site) {
            if (isset($read[$site->value]) || !$scope->mayRead($site)) {
                continue;
            }

            if (ReaderScope::MAX_SITES === \count($read)) {
                throw InvalidValue::because(\sprintf('At most %d sites are read at once.', ReaderScope::MAX_SITES));
            }

            $read[$site->value] = $site;

            foreach ($this->store->counts($site, $metric, $dimension, $from, $to) as $value => $count) {
                $sums[$value] = ($sums[$value] ?? 0) + $count;
            }
        }

        return $this->suppression->apply($sums);
    }

    private function assertReadable(string $metric, UtcDay $from, UtcDay $to): void
    {
        if (MeasureNames::OUTBOUND_LINK === $metric) {
            throw InvalidValue::because('The count of each outbound link is read by its author and the administrators only, through LinkCountReader.');
        }

        if ($to->isBefore($from)) {
            throw InvalidValue::because(\sprintf('The range from %s to %s is reversed.', $from->value, $to->value));
        }

        if ($from->start()->diff($to->start())->days >= self::MAX_DAYS) {
            throw InvalidValue::because(\sprintf('At most %d days are read at once.', self::MAX_DAYS));
        }
    }
}
