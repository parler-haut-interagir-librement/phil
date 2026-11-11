<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Geocoding API (v6) of Mapbox, with an access token. Its answers are temporary by default — they may not be
 * kept, nor shown without a Mapbox map —; `permanent` asks for answers that may be kept, which Mapbox bills. A point
 * gives one place.
 */
final readonly class MapboxGeocoder implements Geocoder
{
    public const string NAME = 'mapbox';

    public const string BASE_URL = 'https://api.mapbox.com/search/geocode/v6';

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        private string $accessToken,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
        private bool $permanent = false,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask('/forward', ['q' => $address, 'limit' => $this->limit, 'autocomplete' => 'false'], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask('/reverse', ['longitude' => $at->longitude, 'latitude' => $at->latitude], $locale);
    }

    /**
     * @param array<string, string|int|float> $query
     *
     * @return list<Place>
     */
    private function ask(string $path, array $query, ?string $locale): array
    {
        $answer = Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
            'query' => $query
                + ['access_token' => $this->accessToken]
                + ($this->permanent ? ['permanent' => 'true'] : [])
                + (null === $locale || '' === $locale ? [] : ['language' => strtr($locale, '_', '-')]),
        ]), self::NAME);

        return array_map($this->place(...), $answer->list('features'));
    }

    private function place(Answer $answer): Place
    {
        $context = static fn (string|int ...$path): ?string => $answer->string('properties', 'context', ...$path);
        $street = trim(($context('address', 'address_number') ?? '').' '.($context('address', 'street_name') ?? $context('street', 'name') ?? ''));

        return new Place(
            $answer->coordinates('geometry.coordinates.1', 'geometry.coordinates.0'),
            self::NAME,
            $answer->string('properties', 'full_address') ?? $answer->string('properties', 'name'),
            '' === $street ? null : $street,
            $context('place', 'name') ?? $context('locality', 'name'),
            $context('region', 'name'),
            $context('postcode', 'name'),
            $answer->country($context('country', 'country_code')),
        );
    }
}
