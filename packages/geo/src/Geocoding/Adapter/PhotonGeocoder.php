<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Photon, a geocoder on OpenStreetMap data that finds places as one types: free and without a key on its public
 * server, within reasonable use, or on one's own server — a base URL.
 *
 * A server knows a few languages and refuses any other, so a locale is sent only when its language is one of them —
 * those of the public server by default —; otherwise places come in their local names. A place named for what it is
 * — a street, a city, a state — gives that name as the part of its address it is.
 */
final readonly class PhotonGeocoder implements Geocoder
{
    public const string NAME = 'photon';

    public const string BASE_URL = 'https://photon.komoot.io';

    /** @var list<string> */
    public const array LANGUAGES = ['de', 'en', 'fr'];

    /**
     * @param list<string> $languages the languages the server knows
     */
    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
        private array $languages = self::LANGUAGES,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask('/api', ['q' => $address], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask('/reverse', ['lat' => $at->latitude, 'lon' => $at->longitude], $locale);
    }

    /**
     * @param array<string, string|float> $query
     *
     * @return list<Place>
     */
    private function ask(string $path, array $query, ?string $locale): array
    {
        $language = null === $locale ? null : strtolower(explode('_', strtr($locale, '-', '_'))[0]);
        $answer = Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
            'query' => $query + ['limit' => $this->limit] + (\in_array($language, $this->languages, true) ? ['lang' => $language] : []),
        ]), self::NAME);

        return array_map($this->place(...), $answer->list('features'));
    }

    private function place(Answer $answer): Place
    {
        $name = $answer->string('properties', 'name');
        $type = $answer->string('properties', 'type');
        $street = trim(($answer->string('properties', 'housenumber') ?? '').' '.($answer->string('properties', 'street') ?? ''));
        $street = '' === $street ? ('street' === $type ? $name : null) : $street;

        $locality = $answer->string('properties', 'city') ?? ('city' === $type ? $name : null);
        $region = $answer->string('properties', 'state') ?? ('state' === $type ? $name : null);
        $postalCode = $answer->string('properties', 'postcode');
        $label = implode(', ', array_unique(array_filter(
            [$name, $street, $locality, $region, $postalCode, $answer->string('properties', 'country')],
            static fn (?string $part): bool => null !== $part,
        )));

        return new Place(
            $answer->coordinates('geometry.coordinates.1', 'geometry.coordinates.0'),
            self::NAME,
            '' === $label ? null : $label,
            $street,
            $locality,
            $region,
            $postalCode,
            $answer->country($answer->string('properties', 'countrycode')),
        );
    }
}
