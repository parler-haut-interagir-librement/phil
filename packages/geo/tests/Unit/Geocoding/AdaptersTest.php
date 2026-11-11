<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding;

use Closure;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\InvalidPlace;
use Phil\Geo\Geocoding\Adapter\Answer;
use Phil\Geo\Geocoding\Adapter\GoogleMapsGeocoder;
use Phil\Geo\Geocoding\Adapter\IpstackLocator;
use Phil\Geo\Geocoding\Adapter\NominatimGeocoder;
use Phil\Geo\Geocoding\Adapter\NrcanGeocoder;
use Phil\Geo\Geocoding\Adapter\PhotonGeocoder;
use Phil\Geo\Geocoding\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

#[CoversClass(NominatimGeocoder::class)]
#[CoversClass(GoogleMapsGeocoder::class)]
#[CoversClass(IpstackLocator::class)]
#[CoversClass(NrcanGeocoder::class)]
#[CoversClass(PhotonGeocoder::class)]
#[CoversClass(Answer::class)]
final class AdaptersTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $requests = [];

    public function testNominatimFindsAndReverses(): void
    {
        $client = $this->client([
            new JsonMockResponse([
                ['lat' => '45.5088', 'lon' => '-73.5878', 'display_name' => '1 Rue A, Montréal', 'address' => ['house_number' => '1', 'road' => 'Rue A', 'city' => 'Montréal', 'state' => 'Québec', 'postcode' => 'H2X 1Y4', 'country_code' => 'ca']],
                ['lat' => '46.81', 'lon' => '-71.2', 'display_name' => '  ', 'address' => ['town' => 'Lévis', 'postcode' => 75001, 'house_number' => 2.5]],
                ['lat' => 1, 'lon' => 2, 'address' => ['village' => 'V', 'road' => 'Route 1']],
                ['lat' => 1, 'lon' => 2, 'address' => ['hamlet' => 'H', 'house_number' => '9']],
                ['lat' => 1, 'lon' => 2, 'address' => ['municipality' => 'M', 'city' => '  ']],
                'not a place',
            ]),
            new JsonMockResponse(['lat' => '45.5', 'lon' => '-73.5', 'display_name' => ' Ici ', 'address' => []]),
            new JsonMockResponse(['error' => 'Unable to geocode']),
        ]);
        $nominatim = new NominatimGeocoder($client, 'Site (contact@example.com)', 'https://geo.example.com/', 3);

        $places = $nominatim->geocode('1 rue A, Montréal', 'fr');
        self::assertSame([['45.5088,-73.5878', 'nominatim', '1 Rue A, Montréal', '1 Rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'], ['46.81,-71.2', 'nominatim', null, '2.5', 'Lévis', null, '75001', null]], array_map($this->shape(...), \array_slice($places, 0, 2)));
        self::assertSame([['Route 1', 'V'], ['9', 'H'], [null, 'M']], array_map(static fn (Place $place): array => [$place->street, $place->locality], \array_slice($places, 2)));
        self::assertSame(['GET', 'https://geo.example.com/search?q=1%20rue%20A%2C%20Montr%C3%A9al&limit=3&format=jsonv2&addressdetails=1&accept-language=fr', 'Site (contact@example.com)'], [$this->requests[0][0], $this->requests[0][1], $this->requests[0][2]['user-agent']]);

        self::assertSame([['45.5,-73.5', 'nominatim', 'Ici', null, null, null, null, null]], array_map($this->shape(...), $nominatim->reverse(Coordinates::of(45.5, -73.5))));
        self::assertSame('https://geo.example.com/reverse?lat=45.5&lon=-73.5&format=jsonv2&addressdetails=1', $this->requests[1][1]);
        self::assertSame([], $nominatim->reverse(Coordinates::of(0, 0), 'en'));
        self::assertStringEndsWith('&accept-language=en', $this->requests[2][1]);
        self::assertSame(['nominatim', 'https://nominatim.openstreetmap.org'], [NominatimGeocoder::NAME, NominatimGeocoder::BASE_URL]);
    }

    /**
     * Natural Resources Canada answers a list, its points as longitude then latitude, in English or in French.
     */
    public function testNrcanFindsCanadianPlaces(): void
    {
        $client = $this->client([
            new JsonMockResponse([
                ['title' => '4545 Avenue Pierre-De Coubertin, Montréal, Quebec', 'qualifier' => 'INTERPOLATED_POSITION', 'type' => 'ca.gc.nrcan.geoloc.data.model.Street', 'geometry' => ['type' => 'Point', 'coordinates' => [-73.5482, 45.5588]]],
                ['title' => 'H1V', 'type' => 'ca.gc.nrcan.geoloc.data.model.PostalCode', 'geometry' => ['coordinates' => [-73.5407, 45.5599]]],
                ['title' => 'Montréal, Québec (Ville)', 'type' => 'ca.gc.nrcan.geoloc.data.model.Geoname', 'geometry' => ['coordinates' => [-73.6, 45.5]]],
                ['title' => 'Too many', 'geometry' => ['coordinates' => [-73.6, 45.5]]],
            ]),
            new JsonMockResponse([['geometry' => ['coordinates' => [-71.2, 46.8]]]]),
            new JsonMockResponse([]),
            new JsonMockResponse([]),
        ]);
        $nrcan = new NrcanGeocoder($client, 'https://geo.example.com/geolocation/', 3);

        self::assertSame([
            ['45.5588,-73.5482', 'nrcan', '4545 Avenue Pierre-De Coubertin, Montréal, Quebec', null, null, null, null, 'CA'],
            ['45.5599,-73.5407', 'nrcan', 'H1V', null, null, null, 'H1V', 'CA'],
            ['45.5,-73.6', 'nrcan', 'Montréal, Québec (Ville)', null, null, null, null, 'CA'],
        ], array_map($this->shape(...), $nrcan->geocode('4545 avenue Pierre-De Coubertin', 'fr_CA')));
        self::assertSame(['GET', 'https://geo.example.com/geolocation/fr/locate?q=4545%20avenue%20Pierre-De%20Coubertin'], [$this->requests[0][0], $this->requests[0][1]]);
        self::assertSame([['46.8,-71.2', 'nrcan', null, null, null, null, null, 'CA']], array_map($this->shape(...), $nrcan->geocode('Québec', 'en')));
        self::assertSame('https://geo.example.com/geolocation/en/locate?q=Qu%C3%A9bec', $this->requests[1][1]);
        $nrcan->geocode('a', 'FR');
        self::assertStringContainsString('/fr/locate', $this->requests[2][1]);
        $nrcan->geocode('a');
        self::assertStringContainsString('/en/locate', $this->requests[3][1]);
        self::assertSame([], $nrcan->reverse(Coordinates::of(45.5, -73.5), 'fr'));
        self::assertCount(4, $this->requests);
        self::assertSame(['nrcan', 'https://www.geolocator.api.geo.ca/geolocation'], [NrcanGeocoder::NAME, NrcanGeocoder::BASE_URL]);
    }

    public function testNrcanSendsTheDefaults(): void
    {
        $places = new NrcanGeocoder($this->client([new JsonMockResponse(array_fill(0, 7, ['geometry' => ['coordinates' => [0, 0]]]))]))->geocode('a');

        self::assertCount(5, $places);
        self::assertSame('https://www.geolocator.api.geo.ca/geolocation/en/locate?q=a', $this->requests[0][1]);
    }

    /**
     * Photon answers GeoJSON, its points as longitude then latitude; a place named for what it is gives that name as
     * the part of its address it is.
     */
    public function testPhotonFindsAndReverses(): void
    {
        $client = $this->client([
            new JsonMockResponse(['type' => 'FeatureCollection', 'features' => [
                ['type' => 'Feature', 'properties' => ['type' => 'house', 'housenumber' => '4545', 'name' => 'Stade olympique', 'street' => 'Avenue Pierre-De Coubertin', 'city' => 'Montréal', 'state' => 'Québec', 'country' => 'Canada', 'postcode' => 'H1V 3N7', 'countrycode' => 'CA'], 'geometry' => ['type' => 'Point', 'coordinates' => [-73.5516458, 45.5577984]]],
                ['properties' => ['type' => 'street', 'name' => 'Rue Sherbrooke Est', 'city' => 'Montréal', 'countrycode' => 'ca'], 'geometry' => ['coordinates' => [-73.55, 45.55]]],
                ['properties' => ['type' => 'city', 'name' => 'Montréal', 'state' => 'Québec', 'country' => 'Canada'], 'geometry' => ['coordinates' => [-73.6, 45.5]]],
                ['properties' => ['type' => 'state', 'name' => 'Québec', 'country' => 'Canada'], 'geometry' => ['coordinates' => [-71.2, 52.0]]],
                ['properties' => ['type' => 'house', 'street' => 'Rue A', 'name' => 'Rue A'], 'geometry' => ['coordinates' => [1, 2]]],
                ['properties' => ['type' => 'other', 'name' => 'Montréal'], 'geometry' => ['coordinates' => [1, 2]]],
                ['properties' => ['housenumber' => '9'], 'geometry' => ['coordinates' => [1, 2]]],
                ['properties' => ['type' => 'city', 'name' => 'Sainte-Rose', 'city' => 'Laval', 'state' => 'Québec'], 'geometry' => ['coordinates' => [1, 2]]],
                ['properties' => ['type' => 'state', 'name' => 'Nunavik', 'state' => 'Québec'], 'geometry' => ['coordinates' => [1, 2]]],
                'not a place',
            ]]),
            new JsonMockResponse(['features' => [['properties' => [], 'geometry' => ['coordinates' => [-73.548, 45.5587]]]]]),
            new JsonMockResponse(['features' => []]),
            new JsonMockResponse([]),
            new JsonMockResponse([]),
        ]);
        $photon = new PhotonGeocoder($client, 'https://photon.example.com/', 3, ['fr', 'it']);

        self::assertSame([
            ['45.5577984,-73.5516458', 'photon', 'Stade olympique, 4545 Avenue Pierre-De Coubertin, Montréal, Québec, H1V 3N7, Canada', '4545 Avenue Pierre-De Coubertin', 'Montréal', 'Québec', 'H1V 3N7', 'CA'],
            ['45.55,-73.55', 'photon', 'Rue Sherbrooke Est, Montréal', 'Rue Sherbrooke Est', 'Montréal', null, null, 'CA'],
            ['45.5,-73.6', 'photon', 'Montréal, Québec, Canada', null, 'Montréal', 'Québec', null, null],
            ['52,-71.2', 'photon', 'Québec, Canada', null, null, 'Québec', null, null],
            ['2,1', 'photon', 'Rue A', 'Rue A', null, null, null, null],
            ['2,1', 'photon', 'Montréal', null, null, null, null, null],
            ['2,1', 'photon', '9', '9', null, null, null, null],
            ['2,1', 'photon', 'Sainte-Rose, Laval, Québec', null, 'Laval', 'Québec', null, null],
            ['2,1', 'photon', 'Nunavik, Québec', null, null, 'Québec', null, null],
        ], array_map($this->shape(...), $photon->geocode('4545 avenue Pierre-De Coubertin', 'fr-CA')));
        self::assertSame(['GET', 'https://photon.example.com/api?q=4545%20avenue%20Pierre-De%20Coubertin&limit=3&lang=fr'], [$this->requests[0][0], $this->requests[0][1]]);
        self::assertSame([['45.5587,-73.548', 'photon', null, null, null, null, null, null]], array_map($this->shape(...), $photon->reverse(Coordinates::of(45.5587, -73.548), 'IT_it')));
        self::assertSame('https://photon.example.com/reverse?lat=45.5587&lon=-73.548&limit=3&lang=it', $this->requests[1][1]);
        self::assertSame([], $photon->reverse(Coordinates::of(0, 0), 'de'));
        self::assertSame('https://photon.example.com/reverse?lat=0&lon=0&limit=3', $this->requests[2][1]);
        self::assertSame([], $photon->geocode('a', ''));
        self::assertSame('https://photon.example.com/api?q=a&limit=3', $this->requests[3][1]);
        self::assertSame([], $photon->geocode('a'));
        self::assertSame('https://photon.example.com/api?q=a&limit=3', $this->requests[4][1]);
        self::assertSame(['photon', 'https://photon.komoot.io', ['de', 'en', 'fr']], [PhotonGeocoder::NAME, PhotonGeocoder::BASE_URL, PhotonGeocoder::LANGUAGES]);
    }

    public function testPhotonSendsTheDefaults(): void
    {
        new PhotonGeocoder($this->client([new JsonMockResponse([])]))->geocode('a', 'de_CH');
        new PhotonGeocoder($this->client([new JsonMockResponse([])]))->geocode('a', 'en');
        new PhotonGeocoder($this->client([new JsonMockResponse([])]))->geocode('a', 'it');

        self::assertSame([
            'https://photon.komoot.io/api?q=a&limit=5&lang=de',
            'https://photon.komoot.io/api?q=a&limit=5&lang=en',
            'https://photon.komoot.io/api?q=a&limit=5',
        ], array_column($this->requests, 1));
    }

    public function testNominatimSendsTheDefaults(): void
    {
        new NominatimGeocoder($this->client([new JsonMockResponse([])]), 'Site')->geocode('a');

        self::assertSame('https://nominatim.openstreetmap.org/search?q=a&limit=5&format=jsonv2&addressdetails=1', $this->requests[0][1]);
    }

    public function testGoogleMapsFindsAndReverses(): void
    {
        $client = $this->client([
            new JsonMockResponse(['status' => 'OK', 'results' => [[
                'formatted_address' => '1 Rue A, Montréal, QC H2X 1Y4, Canada',
                'geometry' => ['location' => ['lat' => 45.5088, 'lng' => -73.5878]],
                'address_components' => [
                    ['long_name' => '1', 'short_name' => '1', 'types' => ['street_number']],
                    ['long_name' => 'Rue A', 'short_name' => 'Rue A', 'types' => ['route']],
                    ['long_name' => 'Montréal', 'short_name' => 'Montréal', 'types' => ['locality', 'political']],
                    ['long_name' => 'Ville-Marie', 'short_name' => 'VM', 'types' => ['locality']],
                    ['long_name' => 'Autre', 'short_name' => 'A', 'types' => ['postal_town']],
                    ['long_name' => 'Québec', 'short_name' => 'QC', 'types' => ['administrative_area_level_1', 'political']],
                    ['long_name' => 'H2X 1Y4', 'short_name' => 'H2X 1Y4', 'types' => ['postal_code']],
                    ['long_name' => 'Canada', 'short_name' => 'CA', 'types' => ['country', 'political']],
                ],
            ], [
                'geometry' => ['location' => ['lat' => 51.5, 'lng' => 0]],
                'address_components' => [['long_name' => 'London', 'types' => ['postal_town']], ['long_name' => 'Rd', 'types' => ['route']]],
            ]]]),
            new JsonMockResponse(['status' => 'ZERO_RESULTS', 'results' => []]),
        ]);
        $google = new GoogleMapsGeocoder($client, 'key', 'https://maps.example.com/geocode');

        self::assertSame([
            ['45.5088,-73.5878', 'google_maps', '1 Rue A, Montréal, QC H2X 1Y4, Canada', '1 Rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'],
            ['51.5,0', 'google_maps', null, 'Rd', 'London', null, null, null],
        ], array_map($this->shape(...), $google->geocode('1 rue A', 'fr')));
        self::assertSame('https://maps.example.com/geocode?address=1%20rue%20A&key=key&language=fr', $this->requests[0][1]);
        self::assertSame([], $google->reverse(Coordinates::of(45.5, -73.5)));
        self::assertSame('https://maps.example.com/geocode?latlng=45.5%2C-73.5&key=key', $this->requests[1][1]);
        self::assertSame(['google_maps', 'https://maps.googleapis.com/maps/api/geocode/json'], [GoogleMapsGeocoder::NAME, GoogleMapsGeocoder::BASE_URL]);
    }

    public function testIpstackLocates(): void
    {
        $client = $this->client([
            new JsonMockResponse(['latitude' => 45.5, 'longitude' => -73.5, 'city' => 'Montréal', 'region_name' => 'Québec', 'country_name' => 'Canada', 'zip' => 'H2X', 'country_code' => 'CA']),
            new JsonMockResponse(['latitude' => 45.5, 'longitude' => -73.5]),
            new JsonMockResponse(['latitude' => null, 'longitude' => null]),
        ]);
        $ipstack = new IpstackLocator($client, 'secret', 'https://ip.example.com/');

        self::assertSame(['45.5,-73.5', 'ipstack', 'Montréal, Québec, Canada', null, 'Montréal', 'Québec', 'H2X', 'CA'], $this->shape($ipstack->locate('8.8.8.8') ?? self::fail('No place.')));
        self::assertSame('https://ip.example.com/8.8.8.8?access_key=secret', $this->requests[0][1]);
        self::assertNull(($ipstack->locate('2001:db8::1') ?? self::fail('No place.'))->label);
        self::assertNull($ipstack->locate('127.0.0.1'));
        self::assertSame(['ipstack', 'https://api.ipstack.com'], [IpstackLocator::NAME, IpstackLocator::BASE_URL]);
        new IpstackLocator($this->client([new JsonMockResponse([])]), 'k')->locate('1.1.1.1');
        self::assertSame('https://api.ipstack.com/1.1.1.1?access_key=k', $this->requests[3][1]);

        $this->expectException(InvalidPlace::class);
        $ipstack->locate('1.1.1.1/../x');
    }

    public function testFailuresAreGeocodingFailures(): void
    {
        foreach ([
            [fn (): array => new GoogleMapsGeocoder($this->client([new JsonMockResponse(['status' => 'REQUEST_DENIED', 'error_message' => 'The key is invalid.'])]), 'k')->geocode('a'), 'The geocoding provider "google_maps" failed: it answered REQUEST_DENIED: The key is invalid.'],
            [fn (): array => new GoogleMapsGeocoder($this->client([new JsonMockResponse([])]), 'k')->geocode('a'), 'The geocoding provider "google_maps" failed: it answered no status: no message.'],
            [fn (): ?Place => new IpstackLocator($this->client([new JsonMockResponse(['success' => false, 'error' => ['info' => 'Invalid access key.']])]), 'k')->locate('1.1.1.1'), 'The geocoding provider "ipstack" failed: Invalid access key.'],
            [fn (): array => new NominatimGeocoder($this->client([new JsonMockResponse([['lon' => '1']])]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: it answered a place without coordinates.'],
            [fn (): array => new NrcanGeocoder($this->client([new JsonMockResponse(['message' => 'Internal server error'])]))->geocode('a'), 'The geocoding provider "nrcan" failed: Internal server error'],
            [fn (): array => new NrcanGeocoder($this->client([new JsonMockResponse(['message' => 'Internal server error'], ['http_code' => 500])]))->geocode('a'), 'The geocoding provider "nrcan" failed: it answered HTTP 500.'],
            [fn (): array => new NrcanGeocoder($this->client([new JsonMockResponse([['geometry' => ['coordinates' => [-73.5]]]])]))->geocode('a'), 'The geocoding provider "nrcan" failed: it answered a place without coordinates.'],
            [fn (): array => new NominatimGeocoder($this->client([new JsonMockResponse([['lat' => '1']])]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: it answered a place without coordinates.'],
            [fn (): array => new PhotonGeocoder($this->client([new JsonMockResponse(['message' => 'q parameter is required'], ['http_code' => 400])]))->geocode(''), 'The geocoding provider "photon" failed: it answered HTTP 400.'],
            [fn (): array => new PhotonGeocoder($this->client([new JsonMockResponse(['features' => [['geometry' => ['coordinates' => [-73.5]]]]])]))->reverse(Coordinates::of(0, 0)), 'The geocoding provider "photon" failed: it answered a place without coordinates.'],
            [fn (): array => new NominatimGeocoder($this->client([new JsonMockResponse([['lat' => '91', 'lon' => '0']])]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: A latitude is -90 to 90 degrees; 91 is not.'],
            [fn (): array => new NominatimGeocoder($this->client([new MockResponse('down', ['http_code' => 503])]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: it answered HTTP 503.'],
            [fn (): array => new NominatimGeocoder($this->client([new MockResponse('<html>')]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: it answered something other than JSON.'],
            [fn (): array => new NominatimGeocoder($this->client([new MockResponse('', ['error' => 'unreachable'])]), 'ua')->geocode('a'), 'The geocoding provider "nominatim" failed: it could not be reached.'],
        ] as [$ask, $message]) {
            $this->assertFails($ask, $message);
        }
    }

    /**
     * A failure of another HTTP client than Symfony's, of no kind the contracts name, is still a geocoding failure.
     */
    public function testAnUnknownFailureOfTheClientIsAnUnreadableAnswer(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('toArray')->willThrowException(new class('Something broke at https://maps.example.com/?key=SECRETKEY123') extends RuntimeException implements ExceptionInterface {});

        $this->assertFails(static fn (): Answer => Answer::of($response, 'custom'), 'The geocoding provider "custom" failed: its answer could not be read.');
    }

    public function testACountryIsTwoLettersAndNothingElse(): void
    {
        $answer = Answer::of(new MockHttpClient([new JsonMockResponse([])])->request('GET', 'https://maps.example.com/'), 'custom');

        self::assertSame(['ca', null], [$answer->country('ca'), $answer->country(null)]);
        $this->assertFails(static fn (): ?string => $answer->country("CA\n"), "The geocoding provider \"custom\" failed: it answered \"CA\n\" as a country, which is no ISO 3166-1 alpha-2 code.");
    }

    public function testAFailureNamesNeitherTheKeyNorWhatWasAsked(): void
    {
        foreach ([
            new MockResponse('{"error": "denied"}', ['http_code' => 403]),
            new MockResponse('<html>'),
            new MockResponse('', ['error' => 'Could not resolve host for "https://maps.example.com/?address=1%20rue%20Secr%C3%A8te&key=SECRETKEY123"']),
        ] as $response) {
            try {
                new GoogleMapsGeocoder($this->client([$response]), 'SECRETKEY123')->geocode('1 rue Secrète, Montréal');
                self::fail('No failure.');
            } catch (GeocodingFailed $geocodingFailed) {
                for ($failure = $geocodingFailed; $failure instanceof Throwable; $failure = $failure->getPrevious()) {
                    self::assertStringNotContainsString('SECRETKEY123', $failure->getMessage());
                    self::assertStringNotContainsString('Secr', $failure->getMessage());
                }

                self::assertNull($geocodingFailed->getPrevious());
            }
        }

        try {
            new IpstackLocator($this->client([new MockResponse('', ['http_code' => 401])]), 'SECRETKEY123')->locate('203.0.113.7');
            self::fail('No failure.');
        } catch (GeocodingFailed $geocodingFailed) {
            self::assertSame('The geocoding provider "ipstack" failed: it answered HTTP 401.', $geocodingFailed->getMessage());
            self::assertNull($geocodingFailed->getPrevious());
        }
    }

    public function testACountryThatIsNotTwoLettersIsAFailure(): void
    {
        $this->assertFails(
            fn (): array => new NominatimGeocoder($this->client([new JsonMockResponse([['lat' => '1', 'lon' => '2', 'address' => ['country_code' => 'CAN']]])]), 'ua')->geocode('a'),
            'The geocoding provider "nominatim" failed: it answered "CAN" as a country, which is no ISO 3166-1 alpha-2 code.',
        );
        $this->assertFails(
            fn (): ?Place => new IpstackLocator($this->client([new JsonMockResponse(['latitude' => 1, 'longitude' => 2, 'country_code' => '12'])]), 'k')->locate('1.1.1.1'),
            'The geocoding provider "ipstack" failed: it answered "12" as a country, which is no ISO 3166-1 alpha-2 code.',
        );
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
