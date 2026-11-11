<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The geolocation service of Natural Resources Canada: free, without a key, for Canada only — street addresses,
 * intersections, postal codes and the place names of the Canadian Geographical Names Database —, in English or in
 * French.
 *
 * It finds places from words and never from a point: `reverse()` finds nothing, so that a chain asks the next
 * geocoder. A place is labelled as the service titles it; it gives no address parts but a postal code when that is
 * what it found.
 */
final readonly class NrcanGeocoder implements Geocoder
{
    public const string NAME = 'nrcan';

    public const string BASE_URL = 'https://www.geolocator.api.geo.ca/geolocation';

    private const string POSTAL_CODE = 'ca.gc.nrcan.geoloc.data.model.PostalCode';

    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        $language = null !== $locale && str_starts_with(strtolower($locale), 'fr') ? 'fr' : 'en';
        $answer = Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').'/'.$language.'/locate', ['query' => ['q' => $address]]), self::NAME);
        $message = $answer->string('message');

        if (null !== $message) {
            throw GeocodingFailed::because(self::NAME, $message);
        }

        return array_map($this->place(...), \array_slice($answer->list(), 0, $this->limit));
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return [];
    }

    private function place(Answer $answer): Place
    {
        $title = $answer->string('title');

        return new Place(
            $answer->coordinates('geometry.coordinates.1', 'geometry.coordinates.0'),
            self::NAME,
            $title,
            null,
            null,
            null,
            self::POSTAL_CODE === $answer->string('type') ? $title : null,
            'CA',
        );
    }
}
