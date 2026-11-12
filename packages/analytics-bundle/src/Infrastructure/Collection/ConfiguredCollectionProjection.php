<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Collection;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CollectionProjection;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Measure\ClosedList;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredDimension;
use Phil\AnalyticsBundle\Domain\Measure\Goal;
use Phil\AnalyticsBundle\Domain\Measure\MeasureEvent;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\AnalyticsBundle\Domain\ValueObject\CollectionSite;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoute;
use Phil\AnalyticsBundle\Domain\ValueObject\DeclaredRoutes;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The sites declared under `phil_analytics.sites`: a projection for an application whose sites are known when it is
 * deployed. An application that declares its sites at run time replaces this adapter with its own projection.
 *
 * The configuration is turned into value objects once, when the service is built, and is immutable afterwards: a
 * long-running worker keeps no state from one request to the next.
 *
 * @phpstan-type GoalConfig array{route: ?string, event: ?string, value: ?string}
 * @phpstan-type SiteConfig array{origins: list<string>, routes: array<array-key, string>, status: string, goals: array<array-key, GoalConfig>, forms: list<string>, click_names: list<string>, clicks?: string, downloads?: ?list<string>, dimensions?: array<array-key, list<string>>, server_events?: list<string>}
 */
final readonly class ConfiguredCollectionProjection implements CollectionProjection
{
    /**
     * @var array<string, CollectionSite> by normalized origin, so that a lookup costs the same for one site or a thousand
     */
    private array $byOrigin;

    /**
     * @var array<string, CollectionSite> by site reference
     */
    private array $byReference;

    /**
     * @param array<array-key, SiteConfig> $sites by site reference
     */
    public function __construct(array $sites)
    {
        $byOrigin = [];
        $byReference = [];

        foreach ($sites as $reference => $config) {
            $site = $this->declared((string) $reference, $config);
            $byReference[$site->site->value] = $site;

            foreach ($site->origins as $origin) {
                if (isset($byOrigin[$origin->toString()])) {
                    throw InvalidValue::because(\sprintf('The origin "%s" is declared by two sites.', $origin->toString()));
                }

                $byOrigin[$origin->toString()] = $site;
            }
        }

        $this->byOrigin = $byOrigin;
        $this->byReference = $byReference;
    }

    #[Override]
    public function siteDeclaring(Origin $origin): ?CollectionSite
    {
        return $this->byOrigin[$origin->toString()] ?? null;
    }

    #[Override]
    public function site(SiteId $site): ?CollectionSite
    {
        return $this->byReference[$site->value] ?? null;
    }

    /**
     * @param SiteConfig $config
     */
    private function declared(string $reference, array $config): CollectionSite
    {
        $routes = [];

        foreach ($config['routes'] as $name => $pattern) {
            $routes[] = DeclaredRoute::of((string) $name, $pattern);
        }

        $goals = [];

        foreach ($config['goals'] as $name => $goal) {
            $goals[] = $this->goal((string) $name, $goal);
        }

        $dimensions = [];

        foreach ($config['dimensions'] ?? [] as $name => $values) {
            $dimensions[] = DeclaredDimension::of((string) $name, ClosedList::of($values));
        }

        $downloads = $config['downloads'] ?? null;

        return CollectionSite::of(
            SiteId::fromString($reference),
            array_map(Origin::fromString(...), $config['origins']),
            [] === $routes ? DeclaredRoutes::none() : DeclaredRoutes::of($routes),
            SiteStatus::from($config['status']),
            MeasurePlan::of(
                clicks: ClickTracking::from($config['clicks'] ?? ClickTracking::Aggregate->value),
                clickNames: ClosedList::of($config['click_names']),
                forms: ClosedList::of($config['forms']),
                downloads: null === $downloads ? null : ClosedList::of($downloads),
                goals: $goals,
                dimensions: $dimensions,
                serverEvents: ClosedList::of($config['server_events'] ?? []),
            ),
        );
    }

    /**
     * @param GoalConfig $goal
     */
    private function goal(string $name, array $goal): Goal
    {
        if (null !== $goal['route']) {
            return Goal::onRoute($name, $goal['route']);
        }

        $event = MeasureEvent::tryFrom($goal['event'] ?? '');

        if (null === $event || null === $goal['value']) {
            throw InvalidValue::because(\sprintf('The goal "%s" names a declared route, or a measured event and its value.', $name));
        }

        return Goal::onEvent($name, $event, $goal['value']);
    }
}
