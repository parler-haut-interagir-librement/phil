<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Azure Maps, its Get Geocoding and Get Reverse Geocoding, with a subscription key of the Azure account. The locale is
 * the `Accept-Language` header; a point gives the most precise place there.
 */
final readonly class AzureMapsGeocoder implements Geocoder
{
    public const string NAME = 'azure_maps';

    public const string BASE_URL = 'https://atlas.microsoft.com';

    public const string API_VERSION = '2026-01-01';

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        private string $subscriptionKey,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
        private string $apiVersion = self::API_VERSION,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask('/geocode', ['query' => $address, 'top' => $this->limit], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask('/reverseGeocode', ['coordinates' => $at->longitude.','.$at->latitude], $locale);
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return list<Place>
     */
    private function ask(string $path, array $query, ?string $locale): array
    {
        $answer = Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
            'query' => ['api-version' => $this->apiVersion] + $query + ['subscription-key' => $this->subscriptionKey],
            'headers' => null === $locale || '' === $locale ? [] : ['Accept-Language' => strtr($locale, '_', '-')],
        ]), self::NAME);

        return array_map($this->place(...), $answer->list('features'));
    }

    private function place(Answer $answer): Place
    {
        $address = static fn (string|int ...$path): ?string => $answer->string('properties', 'address', ...$path);
        $street = trim(($address('streetNumber') ?? '').' '.($address('streetName') ?? ''));

        return new Place(
            $answer->coordinates('geometry.coordinates.1', 'geometry.coordinates.0'),
            self::NAME,
            $address('formattedAddress'),
            '' === $street ? $address('addressLine') : $street,
            $address('locality'),
            $address('adminDistricts', 0, 'name'),
            $address('postalCode'),
            $answer->country($address('countryRegion', 'ISO')),
        );
    }
}
