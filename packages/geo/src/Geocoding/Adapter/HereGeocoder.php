<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Geocoding & Search API (v7) of HERE, with a key of the HERE platform. Its terms let an application keep an answer
 * thirty days at most, unless it buys the right to keep it. The country comes from `countryInfo`, asked for each place.
 */
final readonly class HereGeocoder implements Geocoder
{
    public const string NAME = 'here';

    public const string GEOCODE_URL = 'https://geocode.search.hereapi.com/v1/geocode';

    public const string REVERSE_URL = 'https://revgeocode.search.hereapi.com/v1/revgeocode';

    public function __construct(
        private HttpClientInterface $client,
        #[SensitiveParameter]
        private string $apiKey,
        private string $geocodeUrl = self::GEOCODE_URL,
        private string $reverseUrl = self::REVERSE_URL,
        private int $limit = 5,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        return $this->ask($this->geocodeUrl, ['q' => $address], $locale);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        return $this->ask($this->reverseUrl, ['at' => $at->toString()], $locale);
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<Place>
     */
    private function ask(string $url, array $query, ?string $locale): array
    {
        $answer = Answer::of($this->client->request('GET', $url, [
            'query' => $query + ['limit' => $this->limit, 'show' => 'countryInfo', 'apiKey' => $this->apiKey] + (null === $locale || '' === $locale ? [] : ['lang' => strtr($locale, '_', '-')]),
        ]), self::NAME);

        return array_map($this->place(...), $answer->list('items'));
    }

    private function place(Answer $answer): Place
    {
        $street = trim(($answer->string('address', 'houseNumber') ?? '').' '.($answer->string('address', 'street') ?? ''));

        return new Place(
            $answer->coordinates('position.lat', 'position.lng'),
            self::NAME,
            $answer->string('address', 'label') ?? $answer->string('title'),
            '' === $street ? null : $street,
            $answer->string('address', 'city'),
            $answer->string('address', 'state'),
            $answer->string('address', 'postalCode'),
            $answer->country($answer->string('countryInfo', 'alpha2')),
        );
    }
}
