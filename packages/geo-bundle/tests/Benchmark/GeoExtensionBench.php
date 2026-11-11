<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Benchmark;

use Generator;
use NumberFormatter;
use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use Phil\GeoBundle\Infrastructure\Twig\GeoExtension;

/**
 * A list of places near the visitor formats one distance per place with `|format_distance`, and every call builds an
 * ICU number formatter: the cost of a list of N places grows with N formatter constructions.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"geo"})
 */
final class GeoExtensionBench
{
    private readonly GeoExtension $extension;

    /**
     * @var list<Distance>
     */
    private array $distances = [];

    public function __construct()
    {
        $this->extension = new GeoExtension();
    }

    /**
     * Distances from downtown Montréal to places spread over the province, from a few hundred metres to 800 km.
     *
     * @param array{places: int} $params
     */
    public function setUp(array $params): void
    {
        $from = Coordinates::of(45.5017, -73.5673);

        for ($i = 0; $i < $params['places']; ++$i) {
            $this->distances[] = $from->distanceTo(Coordinates::of(45.5017 + ($i % 70) * 0.1, -73.5673 + ($i % 13) * 0.05));
        }
    }

    /**
     * @ParamProviders("lists")
     */
    public function benchFormatDistance(): void
    {
        foreach ($this->distances as $distance) {
            $this->extension->formatDistance($distance, 'fr_CA');
        }
    }

    /**
     * @ParamProviders("lists")
     */
    public function benchFormatDistanceImperial(): void
    {
        foreach ($this->distances as $distance) {
            $this->extension->formatDistance($distance, 'en_US', 'imperial');
        }
    }

    /**
     * The same list formatted by one shared formatter: the reference that shows what the construction per call costs.
     *
     * @ParamProviders("lists")
     */
    public function benchSharedFormatterReference(): void
    {
        $formatter = new NumberFormatter('fr_CA', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        foreach ($this->distances as $distance) {
            $meters = round($distance->meters);
            $formatter->format($meters < 1_000 ? $meters : $distance->inKilometers());
        }
    }

    /**
     * @return Generator<string, array{places: int}>
     */
    public function lists(): Generator
    {
        yield '10 places' => ['places' => 10];
        yield '100 places' => ['places' => 100];
        yield '1 000 places' => ['places' => 1_000];
    }
}
