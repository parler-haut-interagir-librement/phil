<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Twig;

use Locale;
use NumberFormatter;
use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use Phil\Geo\SchemaOrg;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Distances in templates, and the attributes the `distance-sort` Stimulus controller sorts places by — in the
 * browser, so that the visitor's position never reaches the server.
 */
final class GeoExtension
{
    public const string CONTROLLER = 'phil--geo-bundle--distance-sort';

    /** The locales whose formatters are kept at most: past them, they are built again, so that locales taken from requests cannot grow the memory of a worker without end. */
    private const int KEPT = 64;

    /** @var array<string, NumberFormatter> by locale, built once each */
    private array $formatters = [];

    #[AsTwigFunction(name: 'geo_distance')]
    public function distance(?Coordinates $from, ?Coordinates $to): ?Distance
    {
        return $from instanceof Coordinates && $to instanceof Coordinates ? $from->distanceTo($to) : null;
    }

    /**
     * `850 m` and `12.4 km`, or `0.5 mi` with `imperial`, in the locale's digits.
     */
    #[AsTwigFilter(name: 'format_distance')]
    public function formatDistance(?Distance $distance, ?string $locale = null, string $system = 'metric'): string
    {
        if (!$distance instanceof Distance) {
            return '';
        }

        $formatter = $this->formatter($locale ?? Locale::getDefault());

        if ('imperial' === $system) {
            return $formatter->format($distance->inMiles()).' mi';
        }

        // Rounded before the unit is chosen: 999.6 m is 1 km, never 1 000 m.
        $meters = round($distance->meters);

        if ($meters < 1_000) {
            return $formatter->format($meters).' m';
        }

        return $formatter->format($distance->inKilometers()).' km';
    }

    private function formatter(string $locale): NumberFormatter
    {
        if (!isset($this->formatters[$locale])) {
            if (\count($this->formatters) >= self::KEPT) {
                $this->formatters = [];
            }

            $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);
            $this->formatters[$locale] = $formatter;
        }

        return $this->formatters[$locale];
    }

    /**
     * The attributes of a place the controller sorts: its target name and its position, to fewer decimals when the
     * exact one is not to be published.
     */
    #[AsTwigFunction(name: 'geo_attributes', isSafe: ['html'])]
    public function attributes(?Coordinates $position, int $decimals = 7): string
    {
        $target = \sprintf('data-%s-target="item"', self::CONTROLLER);

        if (!$position instanceof Coordinates) {
            return $target;
        }

        $shown = $position->blurred($decimals);

        return \sprintf('%s data-latitude="%s" data-longitude="%s"', $target, ...explode(',', $shown->toString()));
    }

    /**
     * The coordinates as schema.org `GeoCoordinates`, to embed in a `Place`; `null` without coordinates.
     *
     * @return array{'@type': 'GeoCoordinates', latitude: float, longitude: float}|null
     */
    #[AsTwigFunction(name: 'geo_schema')]
    public function schema(?Coordinates $position): ?array
    {
        return $position instanceof Coordinates ? SchemaOrg::of($position) : null;
    }
}
