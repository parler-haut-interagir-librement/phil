<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Geocoding API of Google Maps, with a key of the Google Cloud console.
 */
final readonly class GoogleMapsGeocoder implements Geocoder
{
    public const string NAME = 'google_maps';

    public const string BASE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        private string $apiKey,
        private string $baseUrl = self::BASE_URL,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask(['address' => $address], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask(['latlng' => $at->toString()], $locale);
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<Place>
     */
    private function ask(array $query, ?string $locale): array
    {
        $answer = Answer::of($this->client->request('GET', $this->baseUrl, [
            'query' => $query + ['key' => $this->apiKey] + (null === $locale ? [] : ['language' => $locale]),
        ]), self::NAME);
        $status = $answer->string('status');

        if ('ZERO_RESULTS' === $status) {
            return [];
        }

        if ('OK' !== $status) {
            throw GeocodingFailed::because(self::NAME, \sprintf('it answered %s: %s', $status ?? 'no status', $answer->string('error_message') ?? 'no message.'));
        }

        return array_map($this->place(...), $answer->list('results'));
    }

    private function place(Answer $answer): Place
    {
        $parts = [];

        foreach ($answer->list('address_components') as $component) {
            for ($index = 0; null !== ($type = $component->string('types', $index)); ++$index) {
                $parts[$type] ??= $component;
            }
        }

        $part = static fn (string $type, string $name = 'long_name'): ?string => ($parts[$type] ?? null)?->string($name);
        $street = trim(($part('street_number') ?? '').' '.($part('route') ?? ''));

        return new Place(
            $answer->coordinates('geometry.location.lat', 'geometry.location.lng'),
            self::NAME,
            $answer->string('formatted_address'),
            '' === $street ? null : $street,
            $part('locality') ?? $part('postal_town'),
            $part('administrative_area_level_1'),
            $part('postal_code'),
            $answer->country($part('country', 'short_name')),
        );
    }
}
