<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Application;

use DateTimeZone;
use InvalidArgumentException;
use Phil\Almanac\Place;

/**
 * The places of `phil_almanac.places`, by name: where sunrise, sunset and the twilights are computed for.
 *
 * @phpstan-type PlaceConfig array{latitude: float, longitude: float, timezone: string}
 */
final readonly class Places
{
    /**
     * @param array<string, PlaceConfig> $places
     */
    public function __construct(
        private array $places,
    ) {
    }

    /**
     * @throws InvalidArgumentException for a name the configuration does not have
     */
    public function get(string $name): Place
    {
        $place = $this->places[$name] ?? throw new InvalidArgumentException(\sprintf('phil_almanac.places has no place "%s"; it has: %s.', mb_substr($name, 0, 100), [] === $this->places ? 'none' : implode(', ', array_keys($this->places))));

        return new Place($place['latitude'], $place['longitude'], new DateTimeZone($place['timezone']));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->places);
    }
}
