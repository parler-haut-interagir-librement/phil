<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenCage, a geocoder on OpenStreetMap and other open data, with a key of its dashboard. Its answers may be kept as
 * long as one likes. A point gives one place at most.
 */
final readonly class OpenCageGeocoder implements Geocoder
{
    public const string NAME = 'opencage';

    public const string BASE_URL = 'https://api.opencagedata.com/geocode/v1/json';

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        private string $apiKey,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask(['q' => $address, 'limit' => $this->limit], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask(['q' => $at->toString()], $locale);
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return list<Place>
     */
    private function ask(array $query, ?string $locale): array
    {
        $answer = Answer::of($this->client->request('GET', $this->baseUrl, [
            'query' => $query + ['key' => $this->apiKey, 'no_annotations' => 1] + (null === $locale || '' === $locale ? [] : ['language' => strtr($locale, '_', '-')]),
        ]), self::NAME);

        return array_map($this->place(...), $answer->list('results'));
    }

    private function place(Answer $answer): Place
    {
        $part = static fn (string ...$names): ?string => array_reduce($names, static fn (?string $found, string $name): ?string => $found ?? $answer->string('components', $name));
        $street = trim(($part('house_number') ?? '').' '.($part('road') ?? ''));

        return new Place(
            $answer->coordinates('geometry.lat', 'geometry.lng'),
            self::NAME,
            $answer->string('formatted'),
            '' === $street ? null : $street,
            $part('_normalized_city', 'city', 'town', 'village'),
            $part('state'),
            $part('postcode'),
            $answer->country($part('ISO_3166-1_alpha-2', 'country_code')),
        );
    }
}
