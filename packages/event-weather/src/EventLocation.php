<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use Phil\EventWeather\Exception\InvalidArgument;
use Phil\Geo\Coordinates;

/**
 * Where an event takes place: its coordinates, and the ISO 3166-1 country its address is in, which chooses the
 * provider — the application knows it from the address; the library never guesses it from the coordinates.
 */
final readonly class EventLocation
{
    public ?string $country;

    /**
     * @param ?string $country two letters, `CA`, in any case
     *
     * @throws InvalidArgument when the country is not two letters
     */
    public function __construct(
        public Coordinates $coordinates,
        ?string $country = null,
    ) {
        if (null !== $country && 1 !== preg_match('/^[A-Za-z]{2}$/D', $country)) {
            throw new InvalidArgument('The country is an ISO 3166-1 code of two letters, such as "CA".');
        }

        $this->country = null === $country ? null : strtoupper($country);
    }

    public function isIn(string $country): bool
    {
        return $this->country === strtoupper($country);
    }
}
