<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Location::class)]
final class LocationTest extends TestCase
{
    public function testOnlineHasNoPlace(): void
    {
        $online = Location::online();

        self::assertTrue($online->isOnline());
        self::assertNull($online->area);
        self::assertNull($online->exact);
    }

    public function testAnAreaKeepsItsExactPlaceUntilApproximated(): void
    {
        $place = Location::area("  Plateau\u{00A0}Mont-Royal ", "  4200, rue  Saint-Laurent  \u{20AC}");

        self::assertFalse($place->isOnline());
        self::assertSame('Plateau Mont-Royal', $place->area);
        self::assertSame('4200, rue Saint-Laurent €', $place->exact);

        $approximate = $place->approximate();

        self::assertSame('Plateau Mont-Royal', $approximate->area);
        self::assertNull($approximate->exact);
        self::assertFalse($approximate->isOnline());
        self::assertNull(Location::area('Québec')->exact);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["Tab\x00"])]
    #[TestWith(["Zero\u{200B}width"])]
    #[TestWith(["Caf\xE9"])]
    public function testAnInvalidAreaIsRefused(string $area): void
    {
        $this->expectException(InvalidListing::class);

        Location::area($area);
    }

    public function testAnInvalidExactPlaceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidListing::because('A place is 1 to 255 characters, without control or format character.'));

        Location::area('Montréal', ' ');
    }

    public function testTheLabelsAreBounded(): void
    {
        self::assertSame(120, mb_strlen((string) Location::area(str_repeat('é', 120))->area));
        self::assertSame(255, mb_strlen((string) Location::area('a', str_repeat('é', 255))->exact));
        self::assertSame('a b', Location::area('a'.str_repeat(' ', 478).'b')->area);

        try {
            Location::area(str_repeat('é', 121));
            self::fail('A too long area was kept.');
        } catch (InvalidListing $invalidListing) {
            self::assertSame('A place is 1 to 120 characters, without control or format character.', $invalidListing->getMessage());
        }

        try {
            Location::area('a', str_repeat('é', 256));
            self::fail('A too long exact place was kept.');
        } catch (InvalidListing) {
        }

        $this->expectExceptionObject(InvalidListing::because('A place is valid UTF-8 of at most 120 characters.'));

        Location::area('a'.str_repeat(' ', 479).'b');
    }
}
