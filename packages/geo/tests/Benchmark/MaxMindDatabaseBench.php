<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Benchmark;

use Generator;
use Phil\Geo\Geocoding\MaxMind\Database;
use Phil\Geo\Tests\Unit\Geocoding\MaxMind\MmdbOffset;
use Phil\Geo\Tests\Unit\Geocoding\MaxMind\MmdbWriter;

/**
 * Locating a visitor by IP runs on the request path: the lookup walks the search tree with one `fseek`/`fread` per
 * node, 24 to 32 for IPv4 once the IPv4 subtree is found and up to 128 for IPv6, then decodes the record of a city.
 * Under PHP-FPM the `Database` is built again on every request, so the cold lookup (metadata and IPv4 subtree) counts.
 *
 * The databases are written once with the tests' `MmdbWriter` into the temporary directory and reused: GeoLite2-City
 * like records, shared between networks as the real file shares them, for 256 or 65 536 IPv4 /24 networks and as many
 * IPv6 /48 networks.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"geo", "maxmind"})
 */
final class MaxMindDatabaseBench
{
    private const array CITIES = [
        ['Montréal', 'Montreal', 'QC', 'Quebec', 45.5017, -73.5673, 'America/Toronto', 'H2X', 6_077_243],
        ['Québec', 'Quebec City', 'QC', 'Quebec', 46.8139, -71.208, 'America/Toronto', 'G1R', 6_325_494],
        ['Toronto', 'Toronto', 'ON', 'Ontario', 43.6532, -79.3832, 'America/Toronto', 'M5H', 6_167_865],
        ['Vancouver', 'Vancouver', 'BC', 'British Columbia', 49.2827, -123.1207, 'America/Vancouver', 'V6B', 6_173_331],
    ];

    private string $path = '';

    private Database $database;

    public function __construct()
    {
        $this->database = new Database(self::database(256));
    }

    /**
     * @param array{networks: int, ip: string} $params
     */
    public function setUp(array $params): void
    {
        $this->path = $this->database($params['networks']);
        $this->database = new Database($this->path);
        $this->database->find($params['ip']);
    }

    /**
     * A lookup on a database already open: the tree walk and the record decoding.
     *
     * @ParamProviders({"databases", "addresses"})
     *
     * @param array{networks: int, ip: string} $params
     */
    public function benchFind(array $params): void
    {
        $this->database->find($params['ip']);
    }

    /**
     * A lookup on a database opened for this request: the file is opened, the metadata read and decoded, and the
     * IPv4 subtree found again before the walk.
     *
     * @ParamProviders({"databases", "addresses"})
     *
     * @Revs(20)
     *
     * @param array{networks: int, ip: string} $params
     */
    public function benchColdFind(array $params): void
    {
        new Database($this->path)->find($params['ip']);
    }

    /**
     * @return Generator<string, array{networks: int}>
     */
    public function databases(): Generator
    {
        yield '256 networks' => ['networks' => 256];
        yield '65 536 networks' => ['networks' => 65_536];
    }

    /**
     * @return Generator<string, array{ip: string}>
     */
    public function addresses(): Generator
    {
        yield 'IPv4 found' => ['ip' => '10.0.0.77'];
        yield 'IPv4 not found' => ['ip' => '192.0.2.1'];
        yield 'IPv6 found' => ['ip' => '2a02:0:0:ab::1'];
    }

    /**
     * The database of that many networks, written on the first run only: building the tree in PHP takes seconds.
     */
    private function database(int $networks): string
    {
        $path = \sprintf('%s/phil-geo-bench-v1-%d.mmdb', sys_get_temp_dir(), $networks);

        if (is_file($path)) {
            return $path;
        }

        $writer = new MmdbWriter();
        $records = array_map(static fn (array $city): MmdbOffset => new MmdbOffset($writer->add(self::record(...$city))), self::CITIES);
        $tree = [];

        // Contiguous networks from 10.0.0.0 and 2a02::, as a provider's allocations are.
        for ($i = 0; $i < $networks; ++$i) {
            $tree[\sprintf('10.%d.%d.0/24', ($i >> 8) & 0xFF, $i & 0xFF)] = $records[$i % 4];
            $tree[\sprintf('2a02:0:%x::/48', $i)] = $records[($i + 1) % 4];
        }

        $writer->write($path, $tree);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private static function record(string $french, string $english, string $province, string $provinceName, float $latitude, float $longitude, string $zone, string $postal, int $geonameId): array
    {
        $country = ['geoname_id' => 6_251_999, 'iso_code' => 'CA', 'names' => ['de' => 'Kanada', 'en' => 'Canada', 'es' => 'Canadá', 'fr' => 'Canada', 'ja' => 'カナダ', 'pt-BR' => 'Canadá', 'ru' => 'Канада', 'zh-CN' => '加拿大']];

        return [
            'city' => ['geoname_id' => $geonameId, 'names' => ['de' => $english, 'en' => $english, 'es' => $english, 'fr' => $french, 'ja' => $english, 'pt-BR' => $english, 'ru' => $english, 'zh-CN' => $english]],
            'continent' => ['code' => 'NA', 'geoname_id' => 6_255_149, 'names' => ['de' => 'Nordamerika', 'en' => 'North America', 'es' => 'Norteamérica', 'fr' => 'Amérique du Nord', 'ja' => '北アメリカ', 'pt-BR' => 'América do Norte', 'ru' => 'Северная Америка', 'zh-CN' => '北美洲']],
            'country' => $country,
            'location' => ['accuracy_radius' => 20, 'latitude' => $latitude, 'longitude' => $longitude, 'time_zone' => $zone],
            'postal' => ['code' => $postal],
            'registered_country' => $country,
            'subdivisions' => [['geoname_id' => 6_115_047, 'iso_code' => $province, 'names' => ['en' => $provinceName, 'fr' => $provinceName]]],
        ];
    }
}
