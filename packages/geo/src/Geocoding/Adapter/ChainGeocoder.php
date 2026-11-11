<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Closure;
use InvalidArgumentException;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;

/**
 * Asks geocoders in turn, and gives the places of the first that finds some. A geocoder that fails is passed over;
 * when every one fails, the last failure is thrown, and when none finds anything, there is no place.
 */
final readonly class ChainGeocoder implements Geocoder
{
    /**
     * @param list<Geocoder> $geocoders
     */
    public function __construct(
        private array $geocoders,
    ) {
        if ([] === $geocoders) {
            throw new InvalidArgumentException('A chain of geocoders holds one at least.');
        }
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->first(static fn (Geocoder $geocoder): array => $geocoder->geocode($address, $locale));
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->first(static fn (Geocoder $geocoder): array => $geocoder->reverse($at, $locale));
    }

    /**
     * @param Closure(Geocoder):list<Place> $ask
     *
     * @return list<Place>
     */
    private function first(Closure $ask): array
    {
        $failure = null;
        $answered = false;

        foreach ($this->geocoders as $geocoder) {
            try {
                $places = $ask($geocoder);
            } catch (GeocodingFailed $failed) {
                $failure = $failed;

                continue;
            }

            if ([] !== $places) {
                return $places;
            }

            $answered = true;
        }

        if (!$answered && $failure instanceof GeocodingFailed) {
            throw $failure;
        }

        return [];
    }
}
