<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding;

use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeocodingFailed;
use Phil\Geo\Exception\GeoException;
use Phil\Geo\Exception\InvalidPlace;
use Phil\Geo\Geocoding\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Place::class)]
#[CoversClass(InvalidPlace::class)]
#[CoversClass(GeocodingFailed::class)]
final class PlaceTest extends TestCase
{
    public function testAPlaceSaysWhatItKnows(): void
    {
        $at = Coordinates::of(45.5, -73.5);
        $place = new Place($at, 'test', 'Montréal, QC', '1 rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'ca');

        self::assertSame([$at, 'test', 'Montréal, QC', '1 rue A', 'Montréal', 'Québec', 'H2X 1Y4', 'CA'], [$place->coordinates, $place->provider, $place->label, $place->street, $place->locality, $place->region, $place->postalCode, $place->countryCode]);
        self::assertSame([null, null, null, null, null, null], [new Place($at, 'test')->label, new Place($at, 'test')->street, new Place($at, 'test')->locality, new Place($at, 'test')->region, new Place($at, 'test')->postalCode, new Place($at, 'test')->countryCode]);
    }

    public function testErrorsSayWhatWentWrong(): void
    {
        foreach (['CAN', 'c', "ca\n", '1a'] as $code) {
            try {
                new Place(Coordinates::of(0, 0), 'test', countryCode: $code);
                self::fail($code);
            } catch (InvalidPlace $invalid) {
                self::assertSame(\sprintf('A country is its two letters of ISO 3166-1; "%s" is not.', $code), $invalid->getMessage());
                self::assertInstanceOf(GeoException::class, $invalid);
            }
        }

        $previous = new RuntimeException('down');
        $failed = GeocodingFailed::because('nominatim', 'it is down.', $previous);
        self::assertSame(['The geocoding provider "nominatim" failed: it is down.', $previous], [$failed->getMessage(), $failed->getPrevious()]);
        self::assertInstanceOf(GeoException::class, $failed);
        self::assertSame('"x" is not an IP address.', InvalidPlace::ip('x')->getMessage());
    }

    public function testARefusedAddressIsBoundedAndEscapedInTheMessage(): void
    {
        self::assertSame('"1.1.1.1\\r\\n[forged] \\u0000/é" is not an IP address.', InvalidPlace::ip("1.1.1.1\r\n[forged] \0/é")->getMessage());
        self::assertSame(\sprintf('"%s" is not an IP address.', str_repeat('é', 32)), InvalidPlace::ip(str_repeat('é', 33))->getMessage());
        self::assertSame(\sprintf("\"%s\u{FFFD}\" is not an IP address.", str_repeat('a', 63)), InvalidPlace::ip(str_repeat('a', 63).'é')->getMessage());
        self::assertSame("\"\u{FFFD}\" is not an IP address.", InvalidPlace::ip("\xFF")->getMessage());
    }
}
