<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nominatim, the geocoder of OpenStreetMap: free, one request a second at most on the public server, and a user
 * agent that names the site and how to reach it, as its usage policy asks. Another server — one's own — is a base URL.
 */
final readonly class NominatimGeocoder implements Geocoder
{
    public const string NAME = 'nominatim';

    public const string BASE_URL = 'https://nominatim.openstreetmap.org';

    public function __construct(
        private HttpClientInterface $client,
        private string $userAgent,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return array_map($this->place(...), $this->ask('/search', ['q' => $address, 'limit' => $this->limit], $locale)->list());
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        $answer = $this->ask('/reverse', ['lat' => $at->latitude, 'lon' => $at->longitude], $locale);

        return null === $answer->string('error') ? [$this->place($answer)] : [];
    }

    /**
     * @param array<string, string|int|float> $query
     */
    private function ask(string $path, array $query, ?string $locale): Answer
    {
        return Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
            'query' => $query + ['format' => 'jsonv2', 'addressdetails' => 1] + (null === $locale ? [] : ['accept-language' => $locale]),
            'headers' => ['User-Agent' => $this->userAgent],
        ]), self::NAME);
    }

    private function place(Answer $answer): Place
    {
        $street = trim(($answer->string('address', 'house_number') ?? '').' '.($answer->string('address', 'road') ?? ''));
        $locality = null;

        foreach (['city', 'town', 'village', 'hamlet', 'municipality'] as $kind) {
            $locality ??= $answer->string('address', $kind);
        }

        return new Place(
            $answer->coordinates('lat', 'lon'),
            self::NAME,
            $answer->string('display_name'),
            '' === $street ? null : $street,
            $locality,
            $answer->string('address', 'state'),
            $answer->string('address', 'postcode'),
            $answer->country($answer->string('address', 'country_code')),
        );
    }
}
