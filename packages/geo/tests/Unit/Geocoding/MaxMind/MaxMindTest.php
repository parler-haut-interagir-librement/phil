<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding\MaxMind;

use Closure;
use Override;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\InvalidDatabase;
use Phil\Geo\Exception\InvalidPlace;
use Phil\Geo\Geocoding\Adapter\MaxMindLocator;
use Phil\Geo\Geocoding\MaxMind\Database;
use Phil\Geo\Geocoding\MaxMind\Decoder;
use Phil\Geo\Geocoding\MaxMind\FileBytes;
use Phil\Geo\Geocoding\MaxMind\Metadata;
use Phil\Geo\Geocoding\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Database::class)]
#[CoversClass(Decoder::class)]
#[CoversClass(FileBytes::class)]
#[CoversClass(Metadata::class)]
#[CoversClass(MaxMindLocator::class)]
#[CoversClass(InvalidDatabase::class)]
final class MaxMindTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-geo-mmdb-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            chmod($file, 0o644);
            unlink($file);
        }

        rmdir($this->directory);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function shapes(): iterable
    {
        foreach ([24, 28, 32] as $size) {
            yield 'IPv6, '.$size.' bits' => [6, $size];
            yield 'IPv4, '.$size.' bits' => [4, $size];
        }
    }

    #[DataProvider('shapes')]
    public function testAnAddressIsFoundInItsNetwork(int $ipVersion, int $recordSize): void
    {
        $networks = ['81.2.69.0/24' => $this->london(), '81.2.70.0/23' => ['country' => ['iso_code' => 'GB']], '192.0.0.0/2' => ['high' => true]];

        if (6 === $ipVersion) {
            $networks['2001:db8::/32'] = ['documentation' => true];
        }

        $database = new Database($this->write($networks, $ipVersion, $recordSize));

        self::assertSame($this->london(), $database->find('81.2.69.142'));
        self::assertSame($this->london(), $database->find('81.2.69.0'));
        self::assertSame(['country' => ['iso_code' => 'GB']], $database->find('81.2.71.255'));
        self::assertNull($database->find('10.0.0.1'));
        self::assertNull($database->find('81.2.68.255'));
        self::assertSame(['high' => true], $database->find('200.1.1.1'));
        self::assertSame(6 === $ipVersion ? ['documentation' => true] : null, $database->find('2001:db8::1'));
        self::assertNull($database->find('2002::1'));
        self::assertSame(['high' => true], $database->find('255.255.255.255'));
        self::assertSame([$recordSize, $ipVersion], [$database->metadata()->recordSize, $database->metadata()->ipVersion]);
    }

    public function testTheMetadataDescribesTheDatabase(): void
    {
        $metadata = new Database($this->write(['81.2.69.0/24' => $this->london()], 6, 28, ['languages' => ['en', 3, 'fr']]))->metadata();

        self::assertSame(['GeoLite2-City', ['en', 'fr'], '2025-11-04T00:00:00+00:00'], [$metadata->databaseType, $metadata->languages, $metadata->builtAt->format('c')]);
        self::assertGreaterThan(96, $metadata->nodeCount);
        self::assertSame([], new Database($this->write([], 4, 24, ['languages' => 'en'], 'none'))->metadata()->languages);
    }

    /**
     * Every type of the data section, in each of its sizes, and pointers of the four sizes.
     */
    public function testEveryValueIsDecoded(): void
    {
        $writer = new MmdbWriter();
        $short = $writer->add('short');
        $writer->add(str_repeat('p', 2_031));
        $high = $writer->add('high');
        $writer->add('xyz');
        $middle = $writer->add('middle');
        $writer->add(str_repeat('p', 530_000));
        $far = $writer->add('far');
        self::assertSame([2_040, 2_049], [$high, $middle]);
        self::assertGreaterThan(526_336, $far);
        $values = [
            'empty' => '',
            'size 29' => str_repeat('a', 29),
            'size 284' => str_repeat('b', 284),
            'size 285' => str_repeat('c', 285),
            'size 65820' => str_repeat('d', 65_820),
            'size 65821' => str_repeat('e', 65_821),
            'double' => -12.5,
            'float' => new MmdbRaw(MmdbWriter::control(15, 4).pack('G', 1.5)),
            'bytes' => new MmdbRaw(MmdbWriter::control(4, 3)."\x00\xFFa"),
            'uint16' => new MmdbRaw(MmdbWriter::control(5, 2)."\x01\x02"),
            'uint16 empty' => new MmdbRaw(MmdbWriter::control(5, 0)),
            'uint32' => 4_294_967_295,
            'int32' => -2,
            'int32 smallest' => -2_147_483_648,
            'int32 short' => new MmdbRaw(MmdbWriter::control(8, 2)."\xFF\xFF"),
            'int32 largest' => new MmdbRaw(MmdbWriter::control(8, 4)."\x7F\xFF\xFF\xFF"),
            'uint64' => 9_007_199_254_740_993,
            'uint64 largest int' => new MmdbRaw(MmdbWriter::control(9, 8)."\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF"),
            'uint64 large' => new MmdbRaw(MmdbWriter::control(9, 8)."\x80\x00\x00\x00\x00\x00\x00\x01"),
            'uint128' => new MmdbRaw(MmdbWriter::control(10, 16).str_repeat("\x00", 8)."\x00\x00\x00\x00\x00\x00\x01\x00"),
            'uint128 large' => new MmdbRaw(MmdbWriter::control(10, 16)."\x01".str_repeat("\x00", 15)),
            'true' => true,
            'false' => false,
            'list' => [1, 'two', [3]],
            'map' => ['a' => ['b' => 'c']],
            'pointer 0' => MmdbWriter::pointer($short, 0),
            'pointer 1' => MmdbWriter::pointer($middle, 1),
            'pointer 2' => MmdbWriter::pointer($far, 2),
            'pointer 3' => MmdbWriter::pointer($short, 3),
            'pointer high' => new MmdbRaw("\x27\xF8"),
        ];
        $writer->write($path = $this->directory.'/types.mmdb', ['81.2.69.0/24' => $values]);
        $record = new Database($path)->find('81.2.69.1');

        self::assertIsArray($record);
        self::assertSame(array_keys($values), array_keys($record));
        self::assertSame([0, 29, 284, 285, 65_820, 65_821], array_map(static fn (mixed $text): int => \is_string($text) ? \strlen($text) : -1, [$record['empty'], $record['size 29'], $record['size 284'], $record['size 285'], $record['size 65820'], $record['size 65821']]));
        self::assertSame([-12.5, 1.5, "\x00\xFFa", 258, 0, 4_294_967_295], [$record['double'], $record['float'], $record['bytes'], $record['uint16'], $record['uint16 empty'], $record['uint32']]);
        self::assertSame([-2, -2_147_483_648, 65_535, 2_147_483_647], [$record['int32'], $record['int32 smallest'], $record['int32 short'], $record['int32 largest']]);
        self::assertSame([9_007_199_254_740_993, \PHP_INT_MAX, '0x8000000000000001', 256, '0x01000000000000000000000000000000'], [$record['uint64'], $record['uint64 largest int'], $record['uint64 large'], $record['uint128'], $record['uint128 large']]);
        self::assertSame([true, false, [1, 'two', [3]], ['a' => ['b' => 'c']]], [$record['true'], $record['false'], $record['list'], $record['map']]);
        self::assertSame(['short', 'middle', 'far', 'short'], [$record['pointer 0'], $record['pointer 1'], $record['pointer 2'], $record['pointer 3']]);
        self::assertSame('high', $record['pointer high']);
    }

    public function testAnAddressIsAnIpAddress(): void
    {
        $database = new Database($this->write(['81.2.69.0/24' => $this->london()]));

        foreach (['81.2.69', 'localhost', '', '::g'] as $ip) {
            foreach ([static fn (): mixed => $database->find($ip), static fn (): ?Place => new MaxMindLocator($database)->locate($ip)] as $look) {
                try {
                    $look();
                    self::fail($ip);
                } catch (InvalidPlace $invalidPlace) {
                    self::assertSame(\sprintf('"%s" is not an IP address.', $ip), $invalidPlace->getMessage());
                }
            }
        }
    }

    /**
     * @return iterable<string, array{Closure(MmdbWriter, string): void, string}>
     */
    public static function brokenDatabases(): iterable
    {
        $metadata = static fn (array $change): Closure => static function (MmdbWriter $writer, string $path) use ($change): void {
            $writer->write($path, ['81.2.69.0/24' => ['a' => 1]], 6, 28, $change);
        };
        $record = static fn (MmdbRaw $value): Closure => static function (MmdbWriter $writer, string $path) use ($value): void {
            $writer->write($path, ['81.2.69.0/24' => $value]);
        };

        yield 'no metadata' => [self::file(str_repeat("\0", 200)), 'it has no metadata.'];
        yield 'version 1' => [$metadata(['binary_format_major_version' => 1]), 'its format is not version 2.'];
        yield 'no node' => [$metadata(['node_count' => 0]), 'its search tree has no node.'];
        yield 'nodes not counted' => [$metadata(['node_count' => '5']), 'its search tree has no node.'];
        yield 'records of 16 bits' => [$metadata(['record_size' => 16]), 'its records are not 24, 28 or 32 bits.'];
        yield 'IPv5' => [$metadata(['ip_version' => 5]), 'it holds neither IPv4 nor IPv6 addresses.'];
        yield 'no type' => [$metadata(['database_type' => '']), 'it does not name its type.'];
        yield 'type not a string' => [$metadata(['database_type' => 1]), 'it does not name its type.'];
        yield 'no build date' => [$metadata(['build_epoch' => -1]), 'it does not say when it was built.'];
        yield 'build date not a number' => [$metadata(['build_epoch' => 'today']), 'it does not say when it was built.'];
        yield 'metadata not a map' => [self::file(MmdbWriter::MARKER.MmdbWriter::encode('map')), 'its format is not version 2.'];
        yield 'unknown type' => [$record(new MmdbRaw("\x00\x06")), 'it holds a value of unknown type 13.'];
        yield 'container type' => [$record(new MmdbRaw("\x00\x05")), 'it holds a value of unknown type 12.'];
        yield 'key not a string' => [$record(new MmdbRaw(MmdbWriter::control(7, 1).MmdbWriter::encode(1).MmdbWriter::encode('a'))), 'a map has a key that is not a string.'];
        yield 'boolean of 2' => [$record(new MmdbRaw(MmdbWriter::control(14, 2))), 'a boolean is 0 or 1, not 2.'];
        yield 'double of 4 bytes' => [$record(new MmdbRaw(MmdbWriter::control(3, 4).'abcd')), 'a number of 4 bytes should be 8.'];
        yield 'float of 8 bytes' => [$record(new MmdbRaw(MmdbWriter::control(15, 8).'abcdefgh')), 'a number of 8 bytes should be 4.'];
        yield 'int32 of 5 bytes' => [$record(new MmdbRaw(MmdbWriter::control(8, 5).'abcde')), 'a 32-bit integer of 5 bytes.'];
        yield 'pointer to itself' => [$record(MmdbWriter::pointer(0, 0)), 'its values nest more than 64 deep.'];
        yield 'string past the end' => [$record(new MmdbRaw(MmdbWriter::control(2, 200_000).'short')), 'it ends before 200000 bytes from offset'];
        yield 'tree without end' => [self::file(MmdbWriter::node(0, 0, 24).str_repeat("\0", 16).MmdbWriter::MARKER.MmdbWriter::encode(self::metadataOf(1, 24, 4))), 'its search tree does not end for 81.2.69.1.'];
        yield 'leaf before the data' => [self::file(MmdbWriter::node(16, 16, 24).str_repeat("\0", 16).MmdbWriter::MARKER.MmdbWriter::encode(self::metadataOf(1, 24, 4))), 'the search tree points before the data for 81.2.69.1.'];
        yield 'leaf on the data' => [self::file(MmdbWriter::node(17, 17, 24).str_repeat("\0", 16).MmdbWriter::encode(true).MmdbWriter::MARKER.MmdbWriter::encode(self::metadataOf(1, 24, 4))), 'none'];
    }

    /**
     * @param Closure(MmdbWriter, string): void $write
     */
    #[DataProvider('brokenDatabases')]
    public function testABrokenDatabaseIsSaidSo(Closure $write, string $reason): void
    {
        $write(new MmdbWriter(), $path = $this->directory.'/broken.mmdb');

        if ('none' === $reason) {
            self::assertTrue(new Database($path)->find('81.2.69.1'));

            return;
        }

        try {
            new Database($path)->find('81.2.69.1');
            self::fail($reason);
        } catch (InvalidDatabase $invalidDatabase) {
            self::assertStringStartsWith(\sprintf('The MaxMind database "%s" is not a valid MaxMind DB file: %s', $path, $reason), $invalidDatabase->getMessage());
        }
    }

    /**
     * A database from the 64 deepest values still read.
     */
    public function testValuesNestUpTo64Deep(): void
    {
        $value = 'deep';

        for ($i = 0; $i < 63; ++$i) {
            $value = [$value];
        }

        $record = new Database($this->write(['81.2.69.0/24' => $value]))->find('81.2.69.1');

        for ($i = 0; $i < 63; ++$i) {
            self::assertIsArray($record);
            $record = $record[0];
        }

        self::assertSame('deep', $record);
    }

    /**
     * The 64th level is read, the 65th refused, whether lists, maps, keys or pointers lead there.
     */
    public function testTheDepthOfEveryValueCounts(): void
    {
        $writer = new MmdbWriter();
        $text = $writer->add('text');
        $nested = static function (mixed $value, int $levels, bool $maps = false): mixed {
            for ($i = 0; $i < $levels; ++$i) {
                $value = $maps ? ['k' => $value] : [$value];
            }

            return $value;
        };
        $networks = [
            '10.0.1.0/24' => $nested('deep', 64),
            '10.0.2.0/24' => $nested('deep', 65),
            '10.0.3.0/24' => $nested('deep', 64, maps: true),
            '10.0.4.0/24' => $nested(['k' => ['x']], 63, maps: true),
            '10.0.5.0/24' => $nested(new MmdbRaw(MmdbWriter::control(7, 1).MmdbWriter::pointer($text, 0)->bytes.MmdbWriter::encode('v')), 63),
            '10.0.6.0/24' => $nested(MmdbWriter::pointer($text, 0), 63),
            '10.0.7.0/24' => $nested(MmdbWriter::pointer($text, 0), 64),
        ];
        $writer->write($path = $this->directory.'/depth.mmdb', $networks, 4, 24);
        $database = new Database($path);
        $unwrap = static function (mixed $value, int $levels, string $key = '0'): mixed {
            for ($i = 0; $i < $levels; ++$i) {
                self::assertIsArray($value);
                $value = $value['0' === $key ? 0 : $key];
            }

            return $value;
        };

        self::assertSame('deep', $unwrap($database->find('10.0.1.1'), 64));
        self::assertSame('deep', $unwrap($database->find('10.0.3.1'), 64, 'k'));
        self::assertSame('text', $unwrap($database->find('10.0.6.1'), 63));

        foreach (['10.0.2.1', '10.0.4.1', '10.0.5.1', '10.0.7.1'] as $ip) {
            try {
                $database->find($ip);
                self::fail($ip);
            } catch (InvalidDatabase $invalidDatabase) {
                self::assertStringEndsWith('its values nest more than 64 deep.', $invalidDatabase->getMessage());
            }
        }
    }

    /**
     * Records and pointers too large for their lower bytes alone: a data section past 17 MB.
     */
    public function testLargeRecordsAndPointersAreRead(): void
    {
        foreach ([28, 32] as $recordSize) {
            $writer = new MmdbWriter();
            $writer->add(str_repeat('a', 100_000));
            $near = $writer->add('near');
            $writer->add(str_repeat('b', 8_700_000));
            $writer->add(str_repeat('c', 8_700_000));
            $far = $writer->add('far');
            self::assertGreaterThan(526_336 + (1 << 24), $far);
            $writer->write($path = $this->directory.'/large.mmdb', [
                '0.0.0.0/1' => ['side' => 'left', 'near' => MmdbWriter::pointer($near, 1), 'far' => MmdbWriter::pointer($far, 2), 'farther' => MmdbWriter::pointer($far, 3)],
                '128.0.0.0/1' => ['side' => 'right'],
            ], 4, $recordSize);
            $database = new Database($path);

            self::assertSame(['side' => 'left', 'near' => 'near', 'far' => 'far', 'farther' => 'far'], $database->find('1.2.3.4'), (string) $recordSize);
            self::assertSame(['side' => 'right'], $database->find('200.1.1.1'), (string) $recordSize);
        }

        $writer = new MmdbWriter();
        $writer->add(str_repeat('a', 100_000));
        $writer->write($path = $this->directory.'/large.mmdb', ['0.0.0.0/1' => ['side' => 'left'], '128.0.0.0/1' => ['side' => 'right']], 4, 24);

        self::assertSame(['side' => 'left'], new Database($path)->find('1.2.3.4'));
        self::assertSame(['side' => 'right'], new Database($path)->find('200.1.1.1'));
    }

    /**
     * An IPv6 address finds nothing in an IPv4 database, even where its first 32 bits would; an IPv4 address finds
     * nothing in an IPv6 database that holds no IPv4 network, even where its bits lead elsewhere from the root.
     */
    public function testAnAddressOfTheOtherVersionFindsNothing(): void
    {
        self::assertNull(new Database($this->write(['32.0.0.0/8' => ['v' => 4]], 4, 24, name: 'v4'))->find('2001:db8::1'));

        $database = new Database($this->write(['2001:db8::/32' => ['v' => 6]], 6, 24, name: 'v6'));

        self::assertSame(['v' => 6], $database->find('2001:db8::1'));
        self::assertNull($database->find('32.1.13.184'));
        self::assertNull($database->find('0.1.13.184'));
    }

    /**
     * The last byte of the file can be the last byte of a value: a boolean's type closes the metadata.
     */
    public function testTheMetadataMayEndTheFileOnAnyValue(): void
    {
        file_put_contents($path = $this->directory.'/last.mmdb', MmdbWriter::node(17, 17, 24).str_repeat("\0", 16).MmdbWriter::encode(true).MmdbWriter::MARKER.MmdbWriter::encode(self::metadataOf(1, 24, 4) + ['zz' => false]));

        self::assertTrue(new Database($path)->find('81.2.69.1'));
    }

    public function testAMissingOrUnreadableFileIsSaidSo(): void
    {
        file_put_contents($locked = $this->directory.'/locked.mmdb', 'x');
        chmod($locked, 0o000);

        foreach ([$this->directory.'/none.mmdb', $this->directory, $locked] as $path) {
            try {
                new Database($path)->metadata();
                self::fail($path);
            } catch (InvalidDatabase $invalidDatabase) {
                self::assertSame(\sprintf('The MaxMind database "%s" cannot be read.', $path), $invalidDatabase->getMessage());
            }
        }
    }

    /**
     * The file is opened on the first lookup, once: a database removed after it still answers.
     */
    public function testTheFileIsOpenedOnceOnTheFirstLookup(): void
    {
        $path = $this->write(['81.2.69.0/24' => $this->london()]);
        $database = new Database($this->directory.'/later.mmdb');
        rename($path, $this->directory.'/later.mmdb');
        $metadata = $database->metadata();

        unlink($this->directory.'/later.mmdb');

        self::assertSame($metadata, $database->metadata());
        self::assertSame($this->london(), $database->find('81.2.69.1'));
        self::assertSame($this->london(), $database->find('81.2.69.2'));
    }

    public function testACityBecomesAPlace(): void
    {
        $path = $this->write(['81.2.69.0/24' => $this->london(), '81.2.70.0/24' => ['country' => ['iso_code' => 'GB', 'names' => ['en' => 'United Kingdom']]], '81.2.71.0/24' => ['location' => ['latitude' => 46.8, 'longitude' => -71.2], 'city' => ['names' => ['en' => ' ']], 'subdivisions' => [], 'postal' => ['code' => '']], '81.2.72.0/24' => ['location' => ['latitude' => 46, 'longitude' => -71.2]], '81.2.73.0/24' => ['location' => ['latitude' => 95.0, 'longitude' => 0.0]], '81.2.74.0/24' => 'not a map', '81.2.75.0/24' => ['location' => 'here', 'city' => 'Québec']]);
        $french = new MaxMindLocator(new Database($path), 'fr');
        $place = $french->locate('81.2.69.142');

        self::assertNotNull($place);
        self::assertSame([51.5142, -0.0931], [$place->coordinates->latitude, $place->coordinates->longitude]);
        self::assertSame(['maxmind', 'Londres, England, Royaume-Uni', null, 'Londres', 'England', 'EC2V', 'GB'], [$place->provider, $place->label, $place->street, $place->locality, $place->region, $place->postalCode, $place->countryCode]);
        self::assertSame('London, England, United Kingdom', new MaxMindLocator(new Database($path))->locate('81.2.69.142')?->label);
        self::assertNull($french->locate('81.2.70.1'));
        $bare = $french->locate('81.2.71.1');
        self::assertSame([null, null, null, null, null], [$bare?->label, $bare?->locality, $bare?->region, $bare?->postalCode, $bare?->countryCode]);
        self::assertNull($french->locate('81.2.72.1'));
        self::assertNull($french->locate('81.2.74.1'));
        self::assertNull($french->locate('81.2.75.1'));
        self::assertNull($french->locate('9.9.9.9'));
        self::assertSame('maxmind', MaxMindLocator::NAME);

        $this->expectException(GeocodingFailed::class);
        $this->expectExceptionMessageIsOrContains('The geocoding provider "maxmind" failed:');
        $french->locate('81.2.73.1');
    }

    public function testACountryThatIsNotTwoLettersIsAFailure(): void
    {
        $locator = new MaxMindLocator(new Database($this->write(['81.2.69.0/24' => ['location' => ['latitude' => 51.5, 'longitude' => -0.1], 'country' => ['iso_code' => 'GBR']]])));

        $this->expectException(GeocodingFailed::class);
        $this->expectExceptionMessageIsOrContains('The geocoding provider "maxmind" failed: it holds "GBR" as a country, which is no ISO 3166-1 alpha-2 code.');
        $locator->locate('81.2.69.1');
    }

    /**
     * @return Closure(MmdbWriter, string): void
     */
    private static function file(string $bytes): Closure
    {
        return static function (MmdbWriter $writer, string $path) use ($bytes): void {
            file_put_contents($path, $bytes);
        };
    }

    /**
     * @param array<string, mixed> $networks
     * @param array<string, mixed> $metadata
     */
    private function write(array $networks, int $ipVersion = 6, int $recordSize = 28, array $metadata = [], string $name = 'city'): string
    {
        new MmdbWriter()->write($path = $this->directory.'/'.$name.'.mmdb', $networks, $ipVersion, $recordSize, $metadata);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private static function metadataOf(int $nodes, int $recordSize, int $ipVersion): array
    {
        return ['binary_format_major_version' => 2, 'build_epoch' => 0, 'database_type' => 'Test', 'ip_version' => $ipVersion, 'node_count' => $nodes, 'record_size' => $recordSize];
    }

    /**
     * @return array<string, mixed>
     */
    private function london(): array
    {
        return [
            'city' => ['geoname_id' => 2_643_743, 'names' => ['en' => 'London', 'fr' => 'Londres']],
            'country' => ['iso_code' => 'GB', 'names' => ['en' => 'United Kingdom', 'fr' => 'Royaume-Uni']],
            'location' => ['accuracy_radius' => 10, 'latitude' => 51.5142, 'longitude' => -0.0931, 'time_zone' => 'Europe/London'],
            'postal' => ['code' => 'EC2V'],
            'subdivisions' => [['iso_code' => 'ENG', 'names' => ['en' => 'England']]],
        ];
    }
}
