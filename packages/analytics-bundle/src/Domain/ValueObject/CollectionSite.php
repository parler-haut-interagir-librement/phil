<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * What collection knows about one site, and nothing else: its declared origins, its declared routes, its status,
 * and what it measures beyond page views.
 *
 * This is the shape of the collection projection: it holds no personal information, and collection reads it
 * instead of reading the application that maintains it.
 */
final readonly class CollectionSite
{
    /**
     * @param non-empty-list<Origin> $origins
     */
    private function __construct(
        public SiteId $site,
        public array $origins,
        public DeclaredRoutes $routes,
        public SiteStatus $status,
        public MeasurePlan $plan,
    ) {
    }

    /**
     * @param iterable<Origin> $origins
     */
    public static function of(SiteId $site, iterable $origins, DeclaredRoutes $routes, SiteStatus $status, ?MeasurePlan $plan = null): self
    {
        $list = [];

        foreach ($origins as $origin) {
            foreach ($list as $declared) {
                if ($declared->equals($origin)) {
                    throw InvalidValue::because(\sprintf('The origin %s is declared twice for the site "%s".', $origin, $site));
                }
            }

            $list[] = $origin;
        }

        if ([] === $list) {
            throw InvalidValue::because(\sprintf('The site "%s" declares no origin, so no request can belong to it.', $site));
        }

        return new self($site, $list, $routes, $status, $plan ?? MeasurePlan::of());
    }

    public function declares(Origin $origin): bool
    {
        return array_any($this->origins, static fn (Origin $declared): bool => $declared->equals($origin));
    }
}
