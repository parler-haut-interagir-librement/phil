<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application;

use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * The metric and series sources by their keys, each built only when a widget asks for it.
 */
final readonly class Sources
{
    /**
     * @param ServiceProviderInterface<MetricSource> $metrics
     * @param ServiceProviderInterface<SeriesSource> $series
     */
    public function __construct(
        private ServiceProviderInterface $metrics,
        private ServiceProviderInterface $series,
    ) {
    }

    /**
     * @throws NotFound when no metric source has that key
     */
    public function metric(string $key): MetricSource
    {
        return $this->metrics->has($key) ? $this->metrics->get($key) : throw NotFound::of('metric source', $key);
    }

    /**
     * @throws NotFound when no series source has that key
     */
    public function series(string $key): SeriesSource
    {
        return $this->series->has($key) ? $this->series->get($key) : throw NotFound::of('series source', $key);
    }

    /**
     * The keys of the metric sources, in order.
     *
     * @return list<string>
     */
    public function metricKeys(): array
    {
        return $this->keys($this->metrics);
    }

    /**
     * The keys of the series sources, in order.
     *
     * @return list<string>
     */
    public function seriesKeys(): array
    {
        return $this->keys($this->series);
    }

    /**
     * @param ServiceProviderInterface<object> $provider
     *
     * @return list<string>
     */
    private function keys(ServiceProviderInterface $provider): array
    {
        $keys = array_map(strval(...), array_keys($provider->getProvidedServices()));
        sort($keys);

        return $keys;
    }
}
