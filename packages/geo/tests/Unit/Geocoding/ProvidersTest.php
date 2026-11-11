<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding;

use Closure;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Geocoding\Adapter\AdressesQuebecGeocoder;
use Phil\Geo\Geocoding\Adapter\AzureMapsGeocoder;
use Phil\Geo\Geocoding\Adapter\HereGeocoder;
use Phil\Geo\Geocoding\Adapter\MapboxGeocoder;
use Phil\Geo\Geocoding\Adapter\OpenCageGeocoder;
use Phil\Geo\Geocoding\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * OpenCage, HERE, Mapbox, Azure Maps and Adresses Québec: what each is asked, and how its answer becomes places.
 */
#[CoversClass(OpenCageGeocoder::class)]
#[CoversClass(HereGeocoder::class)]
#[CoversClass(MapboxGeocoder::class)]
#[CoversClass(AzureMapsGeocoder::class)]
#[CoversClass(AdressesQuebecGeocoder::class)]
final class ProvidersTest extends TestCase
{
    /** @var list<array{string, string, array<string, string>}> */
    private array $requests = [];

    public function testOpenCage(): void
    {
        $client = $this->client([
            new JsonMockResponse(['results' => [
                ['formatted' => '4545 Avenue Pierre-De Coubertin, Montréal QC H1V 3N7, Canada', 'geometry' => ['lat' => 45.5578, 'lng' => -73.5516], 'components' => ['house_number' => '4545', 'road' => 'Avenue Pierre-De Coubertin', '_normalized_city' => 'Montréal', 'city' => 'Ville', 'state' => 'Québec', 'postcode' => 'H1V 3N7', 'country_code' => 'ca', 'ISO_3166-1_alpha-2' => 'CA']],
                ['geometry' => ['lat' => 46, 'lng' => -71], 'components' => ['road' => 'Rang 2', 'town' => 'Saint-Ours', 'country_code' => 'ca']],
                ['geometry' => ['lat' => 47, 'lng' => -70], 'components' => ['village' => 'Tadoussac']],
                ['geometry' => ['lat' => 48, 'lng' => -69], 'components' => ['city' => 'Rimouski', 'town' => 'Autre']],
            ]]),
            new JsonMockResponse(['results' => []]),
        ]);
        $opencage = new OpenCageGeocoder($client, 'secret', 'https://oc.example.com/json', 3);

        self::assertSame([
            ['45.5578,-73.5516', 'opencage', '4545 Avenue Pierre-De Coubertin, Montréal QC H1V 3N7, Canada', '4545 Avenue Pierre-De Coubertin', 'Montréal', 'Québec', 'H1V 3N7', 'CA'],
            ['46,-71', 'opencage', null, 'Rang 2', 'Saint-Ours', null, null, 'CA'],
            ['47,-70', 'opencage', null, null, 'Tadoussac', null, null, null],
            ['48,-69', 'opencage', null, null, 'Rimouski', null, null, null],
        ], array_map($this->shape(...), $opencage->geocode('4545 Pierre-De Coubertin', 'fr_CA')));
        self::assertSame('https://oc.example.com/json?q=4545%20Pierre-De%20Coubertin&limit=3&key=secret&no_annotations=1&language=fr-CA', $this->requests[0][1]);
        self::assertSame([], $opencage->reverse(Coordinates::of(45.5, -73.5), ''));
        self::assertSame('https://oc.example.com/json?q=45.5%2C-73.5&key=secret&no_annotations=1', $this->requests[1][1]);

        new OpenCageGeocoder($this->client([new JsonMockResponse([])]), 'k')->geocode('a');
        self::assertSame('https://api.opencagedata.com/geocode/v1/json?q=a&limit=5&key=k&no_annotations=1', $this->requests[2][1]);
        self::assertSame(['opencage', 'https://api.opencagedata.com/geocode/v1/json'], [OpenCageGeocoder::NAME, OpenCageGeocoder::BASE_URL]);
    }

    public function testHere(): void
    {
        $client = $this->client([
            new JsonMockResponse(['items' => [
                ['title' => 'Titre', 'position' => ['lat' => 45.5, 'lng' => -73.6], 'address' => ['label' => '1 Rue A, Montréal, QC H2X 1Y4, Canada', 'houseNumber' => '1', 'street' => 'Rue A', 'city' => 'Montréal', 'state' => 'Québec', 'postalCode' => 'H2X 1Y4', 'countryCode' => 'CAN'], 'countryInfo' => ['alpha2' => 'CA', 'alpha3' => 'CAN']],
                ['title' => 'Montréal, QC, Canada', 'position' => ['lat' => 45.5, 'lng' => -73.5], 'address' => ['city' => 'Montréal']],
            ]]),
            new JsonMockResponse(['items' => []]),
        ]);
        $here = new HereGeocoder($client, 'secret', 'https://g.example.com/geocode', 'https://r.example.com/revgeocode', 2);

        self::assertSame([
            ['45.5,-73.6', 'here', '1 Rue A, Montréal, QC H2X 1Y4, Canada', '1 Rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'],
            ['45.5,-73.5', 'here', 'Montréal, QC, Canada', null, 'Montréal', null, null, null],
        ], array_map($this->shape(...), $here->geocode('1 rue A', 'fr_CA')));
        self::assertSame('https://g.example.com/geocode?q=1%20rue%20A&limit=2&show=countryInfo&apiKey=secret&lang=fr-CA', $this->requests[0][1]);
        self::assertSame([], $here->reverse(Coordinates::of(45.5, -73.5)));
        self::assertSame('https://r.example.com/revgeocode?at=45.5%2C-73.5&limit=2&show=countryInfo&apiKey=secret', $this->requests[1][1]);

        new HereGeocoder($this->client([new JsonMockResponse([]), new JsonMockResponse([])]), 'k')->geocode('a', '');
        new HereGeocoder($this->client([new JsonMockResponse([])]), 'k')->reverse(Coordinates::of(1, 2));
        self::assertSame([
            'https://geocode.search.hereapi.com/v1/geocode?q=a&limit=5&show=countryInfo&apiKey=k',
            'https://revgeocode.search.hereapi.com/v1/revgeocode?at=1%2C2&limit=5&show=countryInfo&apiKey=k',
        ], [$this->requests[2][1], $this->requests[3][1]]);
        self::assertSame(['here', 'https://geocode.search.hereapi.com/v1/geocode', 'https://revgeocode.search.hereapi.com/v1/revgeocode'], [HereGeocoder::NAME, HereGeocoder::GEOCODE_URL, HereGeocoder::REVERSE_URL]);
    }

    public function testMapbox(): void
    {
        $client = $this->client([
            new JsonMockResponse(['type' => 'FeatureCollection', 'features' => [
                ['geometry' => ['coordinates' => [-73.6, 45.5]], 'properties' => ['name' => '1 Rue A', 'full_address' => '1 Rue A, Montréal, Québec H2X 1Y4, Canada', 'context' => [
                    'address' => ['address_number' => '1', 'street_name' => 'Rue A'],
                    'street' => ['name' => 'Autre'],
                    'postcode' => ['name' => 'H2X 1Y4'],
                    'place' => ['name' => 'Montréal'],
                    'locality' => ['name' => 'Ville-Marie'],
                    'region' => ['name' => 'Québec', 'region_code' => 'QC'],
                    'country' => ['name' => 'Canada', 'country_code' => 'CA'],
                ]]],
                ['geometry' => ['coordinates' => [-73.5, 45.4]], 'properties' => ['name' => 'Rue B', 'context' => ['street' => ['name' => 'Rue B'], 'locality' => ['name' => 'Verdun']]]],
                ['geometry' => ['coordinates' => [-73.5, 45.4]], 'properties' => []],
            ]]),
            new JsonMockResponse(['features' => []]),
        ]);
        $mapbox = new MapboxGeocoder($client, 'secret', 'https://mb.example.com/v6/', 3, true);

        self::assertSame([
            ['45.5,-73.6', 'mapbox', '1 Rue A, Montréal, Québec H2X 1Y4, Canada', '1 Rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'],
            ['45.4,-73.5', 'mapbox', 'Rue B', 'Rue B', 'Verdun', null, null, null],
            ['45.4,-73.5', 'mapbox', null, null, null, null, null, null],
        ], array_map($this->shape(...), $mapbox->geocode('1 rue A', 'fr_CA')));
        self::assertSame('https://mb.example.com/v6/forward?q=1%20rue%20A&limit=3&autocomplete=false&access_token=secret&permanent=true&language=fr-CA', $this->requests[0][1]);
        self::assertSame([], $mapbox->reverse(Coordinates::of(45.5, -73.6), ''));
        self::assertSame('https://mb.example.com/v6/reverse?longitude=-73.6&latitude=45.5&access_token=secret&permanent=true', $this->requests[1][1]);

        new MapboxGeocoder($this->client([new JsonMockResponse([])]), 't')->reverse(Coordinates::of(1, 2), 'en');
        self::assertSame('https://api.mapbox.com/search/geocode/v6/reverse?longitude=2&latitude=1&access_token=t&language=en', $this->requests[2][1]);
        new MapboxGeocoder($this->client([new JsonMockResponse([])]), 't')->geocode('a');
        self::assertSame('https://api.mapbox.com/search/geocode/v6/forward?q=a&limit=5&autocomplete=false&access_token=t', $this->requests[3][1]);
        self::assertSame(['mapbox', 'https://api.mapbox.com/search/geocode/v6'], [MapboxGeocoder::NAME, MapboxGeocoder::BASE_URL]);
    }

    public function testAzureMaps(): void
    {
        $client = $this->client([
            new JsonMockResponse(['type' => 'FeatureCollection', 'features' => [
                ['geometry' => ['coordinates' => [-73.6, 45.5]], 'properties' => ['address' => [
                    'formattedAddress' => '1 Rue A, Montréal, QC H2X 1Y4',
                    'addressLine' => '1 Rue A',
                    'streetNumber' => '1',
                    'streetName' => 'Rue A',
                    'locality' => 'Montréal',
                    'postalCode' => 'H2X 1Y4',
                    'adminDistricts' => [['name' => 'Québec', 'shortName' => 'QC'], ['name' => 'Montréal (région)']],
                    'countryRegion' => ['name' => 'Canada', 'ISO' => 'CA'],
                ]]],
                ['geometry' => ['coordinates' => [-73.5, 45.4]], 'properties' => ['address' => ['addressLine' => 'Chemin du Roy']]],
                ['geometry' => ['coordinates' => [-73.5, 45.4]], 'properties' => []],
            ]]),
            new JsonMockResponse(['features' => []]),
        ]);
        $azure = new AzureMapsGeocoder($client, 'secret', 'https://az.example.com/', 3, '2025-01-01');

        self::assertSame([
            ['45.5,-73.6', 'azure_maps', '1 Rue A, Montréal, QC H2X 1Y4', '1 Rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'],
            ['45.4,-73.5', 'azure_maps', null, 'Chemin du Roy', null, null, null, null],
            ['45.4,-73.5', 'azure_maps', null, null, null, null, null, null],
        ], array_map($this->shape(...), $azure->geocode('1 rue A', 'fr_CA')));
        self::assertSame(['https://az.example.com/geocode?api-version=2025-01-01&query=1%20rue%20A&top=3&subscription-key=secret', 'fr-CA'], [$this->requests[0][1], $this->requests[0][2]['accept-language'] ?? null]);
        self::assertSame([], $azure->reverse(Coordinates::of(45.5, -73.6), ''));
        self::assertSame(['https://az.example.com/reverseGeocode?api-version=2025-01-01&coordinates=-73.6%2C45.5&subscription-key=secret', null], [$this->requests[1][1], $this->requests[1][2]['accept-language'] ?? null]);

        new AzureMapsGeocoder($this->client([new JsonMockResponse([])]), 'k')->geocode('a');
        self::assertSame(['https://atlas.microsoft.com/geocode?api-version=2026-01-01&query=a&top=5&subscription-key=k', null], [$this->requests[2][1], $this->requests[2][2]['accept-language'] ?? null]);
        self::assertSame(['azure_maps', 'https://atlas.microsoft.com', '2026-01-01'], [AzureMapsGeocoder::NAME, AzureMapsGeocoder::BASE_URL, AzureMapsGeocoder::API_VERSION]);
    }

    /**
     * Its candidates come unsorted, street matches with empty parts; a point without an address is an error, read as
     * no place.
     */
    public function testAdressesQuebec(): void
    {
        $client = $this->client([
            new JsonMockResponse(['spatialReference' => ['wkid' => 4326], 'candidates' => [
                ['address' => 'Rue des Parlementaires, Québec', 'location' => ['x' => -71.21, 'y' => 46.81], 'score' => 94.67, 'attributes' => ['Loc_name' => 'RQA_Rue', 'Num' => '', 'ZIP' => '', 'Odonyme' => '', 'City' => 'Québec']],
                ['address' => '1045 Rue des Parlementaires, Québec, G1A1A3', 'location' => ['x' => -71.2142, 'y' => 46.8085], 'score' => 100, 'attributes' => ['Loc_name' => 'RQA_Adresse', 'Num' => 1045, 'SufNum' => 'A', 'Odonyme' => 'Rue des Parlementaires', 'City' => 'Québec', 'ZIP' => 'g1a1a3']],
                ['address' => 'Autre', 'location' => ['x' => -71, 'y' => 46], 'attributes' => ['ZIP' => '12345']],
                ['address' => 'Avant', 'location' => ['x' => -71, 'y' => 46], 'attributes' => ['ZIP' => 'XG1A1A3']],
                ['address' => 'Après', 'location' => ['x' => -71, 'y' => 46], 'score' => 1, 'attributes' => ['ZIP' => 'G1A1A3X']],
            ]]),
            new JsonMockResponse(['address' => ['Match_addr' => '1045 Rue des Parlementaires, Québec, G1A1A3', 'Num' => '1045', 'Odonyme' => 'Rue des Parlementaires', 'City' => 'Québec', 'ZIP' => 'G1A 1A3'], 'location' => ['x' => -71.2142, 'y' => 46.8085, 'spatialReference' => ['wkid' => 4326]]]),
            new JsonMockResponse(['error' => ['code' => 400, 'message' => 'Unable to complete operation.', 'details' => []]]),
            new JsonMockResponse(['candidates' => []]),
        ]);
        $quebec = new AdressesQuebecGeocoder($client, 'https://aq.example.com/GeocodeServer/', 3);

        self::assertSame([
            ['46.8085,-71.2142', 'adresses_quebec', '1045 Rue des Parlementaires, Québec, G1A1A3', '1045A Rue des Parlementaires', 'Québec', 'Québec', 'G1A 1A3', 'CA'],
            ['46.81,-71.21', 'adresses_quebec', 'Rue des Parlementaires, Québec', null, 'Québec', 'Québec', null, 'CA'],
            ['46,-71', 'adresses_quebec', 'Après', null, null, 'Québec', 'G1A1A3X', 'CA'],
            ['46,-71', 'adresses_quebec', 'Autre', null, null, 'Québec', '12345', 'CA'],
            ['46,-71', 'adresses_quebec', 'Avant', null, null, 'Québec', 'XG1A1A3', 'CA'],
        ], array_map($this->shape(...), $quebec->geocode('1045 des Parlementaires', 'en')));
        self::assertSame('https://aq.example.com/GeocodeServer/findAddressCandidates?SingleLine=1045%20des%20Parlementaires&maxLocations=3&outFields=*&outSR=4326&f=json', $this->requests[0][1]);
        self::assertSame([['46.8085,-71.2142', 'adresses_quebec', '1045 Rue des Parlementaires, Québec, G1A1A3', '1045 Rue des Parlementaires', 'Québec', 'Québec', 'G1A 1A3', 'CA']], array_map($this->shape(...), $quebec->reverse(Coordinates::of(46.8085, -71.2142))));
        self::assertSame('https://aq.example.com/GeocodeServer/reverseGeocode?location=-71.2142%2C46.8085&outSR=4326&f=json', $this->requests[1][1]);
        self::assertSame([], $quebec->reverse(Coordinates::of(48.85, 2.35)));
        self::assertSame([], $quebec->geocode('nulle part'));

        new AdressesQuebecGeocoder($this->client([new JsonMockResponse([])]))->geocode('a');
        self::assertSame('https://servicescarto.mrnf.gouv.qc.ca/pes/rest/services/Territoire/Adresse_Geocodage/GeocodeServer/findAddressCandidates?SingleLine=a&maxLocations=5&outFields=*&outSR=4326&f=json', $this->requests[4][1]);
        self::assertSame(['adresses_quebec', 'https://servicescarto.mrnf.gouv.qc.ca/pes/rest/services/Territoire/Adresse_Geocodage/GeocodeServer'], [AdressesQuebecGeocoder::NAME, AdressesQuebecGeocoder::BASE_URL]);
    }

    public function testFailuresAreGeocodingFailures(): void
    {
        foreach ([
            [fn (): array => new OpenCageGeocoder($this->client([new JsonMockResponse(['status' => ['code' => 401, 'message' => 'invalid API key']], ['http_code' => 401])]), 'k')->geocode('a'), 'The geocoding provider "opencage" failed: it answered HTTP 401.'],
            [fn (): array => new OpenCageGeocoder($this->client([new JsonMockResponse(['results' => [['geometry' => ['lat' => 1]]]])]), 'k')->geocode('a'), 'The geocoding provider "opencage" failed: it answered a place without coordinates.'],
            [fn (): array => new HereGeocoder($this->client([new JsonMockResponse(['error' => 'Unauthorized'], ['http_code' => 401])]), 'k')->geocode('a'), 'The geocoding provider "here" failed: it answered HTTP 401.'],
            [fn (): array => new MapboxGeocoder($this->client([new JsonMockResponse(['message' => 'Not Authorized - Invalid Token'], ['http_code' => 401])]), 't')->geocode('a'), 'The geocoding provider "mapbox" failed: it answered HTTP 401.'],
            [fn (): array => new AzureMapsGeocoder($this->client([new JsonMockResponse(['error' => ['code' => 'InvalidKey']], ['http_code' => 401])]), 'k')->reverse(Coordinates::of(0, 0)), 'The geocoding provider "azure_maps" failed: it answered HTTP 401.'],
            [fn (): array => new AdressesQuebecGeocoder($this->client([new JsonMockResponse(['error' => ['code' => 400, 'message' => 'Unable to complete operation.']])]))->geocode('a'), 'The geocoding provider "adresses_quebec" failed: Unable to complete operation.'],
            [fn (): array => new AdressesQuebecGeocoder($this->client([new MockResponse('down', ['http_code' => 503])]))->reverse(Coordinates::of(0, 0)), 'The geocoding provider "adresses_quebec" failed: it answered HTTP 503.'],
        ] as [$ask, $message]) {
            $this->assertFails($ask, $message);
        }
    }

    /**
     * @param Closure(): mixed $ask
     * @param non-empty-string $message
     */
    private function assertFails(Closure $ask, string $message): void
    {
        try {
            $ask();
            self::fail($message);
        } catch (GeocodingFailed $geocodingFailed) {
            self::assertSame($message, $geocodingFailed->getMessage());
        }
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = [];

            foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $header) {
                if (\is_string($header) && str_contains($header, ': ')) {
                    [$name, $value] = explode(': ', $header, 2);
                    $headers[strtolower($name)] = $value;
                }
            }

            $this->requests[] = [$method, $url, $headers];

            return array_shift($responses) ?? new JsonMockResponse([]);
        });
    }

    /**
     * @return list<?string>
     */
    private function shape(Place $place): array
    {
        return [$place->coordinates->toString(), $place->provider, $place->label, $place->street, $place->locality, $place->region, $place->postalCode, $place->countryCode];
    }
}
