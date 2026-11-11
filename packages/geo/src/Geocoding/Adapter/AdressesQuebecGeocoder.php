<?php

declare(strict_types=1);

namespace Phil\Geo\Geocoding\Adapter;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Geocoder;
use Phil\Geo\Geocoding\Place;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Adresses Québec, the geocoding service of the Gouvernement du Québec on its address register: free, without a key,
 * for Québec only, its data under CC BY 4.0. An ArcGIS geocode server, asked for WGS 84 points.
 *
 * Its places are in Québec, Canada; the service gives no language, region or country, so every place has `Québec` and
 * `CA`. Its candidates come in no order: they are sorted by score, the best first, those without one last; a postal
 * code comes as `A1A 1A1`. A point where it finds no address
 * is an error in its answer, read as no place.
 */
final readonly class AdressesQuebecGeocoder implements Geocoder
{
    public const string NAME = 'adresses_quebec';

    public const string BASE_URL = 'https://servicescarto.mrnf.gouv.qc.ca/pes/rest/services/Territoire/Adresse_Geocodage/GeocodeServer';

    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl = self::BASE_URL,
        private int $limit = 5,
    ) {
    }

    public function geocode(string $address, ?string $locale = null): array
    {
        $answer = $this->ask('/findAddressCandidates', ['SingleLine' => $address, 'maxLocations' => $this->limit, 'outFields' => '*']);

        $error = $answer->string('error', 'message');

        if (null !== $error) {
            throw GeocodingFailed::because(self::NAME, $error);
        }

        $candidates = $answer->list('candidates');
        usort($candidates, static fn (Answer $one, Answer $other): int => $other->float('score') <=> $one->float('score'));

        return array_map(fn (Answer $candidate): Place => $this->place($candidate, $candidate->string('address'), 'attributes'), $candidates);
    }

    public function reverse(Coordinates $at, ?string $locale = null): array
    {
        $answer = $this->ask('/reverseGeocode', ['location' => $at->longitude.','.$at->latitude]);

        return null === $answer->string('error', 'message') ? [$this->place($answer, $answer->string('address', 'Match_addr'), 'address')] : [];
    }

    /**
     * @param array<string, string|int> $query
     */
    private function ask(string $path, array $query): Answer
    {
        return Answer::of($this->client->request('GET', rtrim($this->baseUrl, '/').$path, [
            'query' => $query + ['outSR' => 4326, 'f' => 'json'],
        ]), self::NAME);
    }

    private function place(Answer $answer, ?string $label, string $parts): Place
    {
        $street = trim(($answer->string($parts, 'Num') ?? '').($answer->string($parts, 'SufNum') ?? '').' '.($answer->string($parts, 'Odonyme') ?? ''));
        $postalCode = $answer->string($parts, 'ZIP');

        return new Place(
            $answer->coordinates('location.y', 'location.x'),
            self::NAME,
            $label,
            '' === $street ? null : $street,
            $answer->string($parts, 'City'),
            'Québec',
            null === $postalCode ? null : (1 === preg_match('/^([A-Z]\d[A-Z]) ?(\d[A-Z]\d)$/', strtoupper($postalCode), $match) ? $match[1].' '.$match[2] : $postalCode),
            'CA',
        );
    }
}
