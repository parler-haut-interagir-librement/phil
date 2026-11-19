<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Domain;

use Phil\Geo\Coordinates;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapPlace::class)]
#[CoversClass(SiteAddress::class)]
final class PlacesTest extends TestCase
{
    public function testAPlace(): void
    {
        $place = MapPlace::of('hall', 'Main hall', Coordinates::of(45.5, -73.6), 'Doors at 9', SiteAddress::of('/venues/hall'));

        self::assertSame(['hall', 'Main hall', 'Doors at 9', '/venues/hall'], [$place->id, $place->name, $place->description, $place->link?->value]);
        self::assertSame('45.5,-73.6', $place->coordinates->toString());
        self::assertNull(MapPlace::of('a', 'A', Coordinates::of(0, 0), '')->description);
        self::assertNull(MapPlace::of('a', 'A', Coordinates::of(0, 0))->link);
    }

    public function testTheLimitsOfAPlace(): void
    {
        $place = MapPlace::of(str_repeat('i', 200), str_repeat('é', 200), Coordinates::of(0, 0), str_repeat('é', 500));

        self::assertSame(200, \strlen($place->id));
        self::assertSame(500, mb_strlen((string) $place->description));
    }

    /**
     * @return iterable<string, array{string, string, ?string, string}>
     */
    public static function refusedPlaces(): iterable
    {
        yield 'empty identifier' => [' ', 'A', null, 'A place has an identifier of 1 to 200 bytes.'];
        yield 'long identifier' => [str_repeat('i', 201), 'A', null, 'A place has an identifier of 1 to 200 bytes.'];
        yield 'blank name' => ['a', ' ', null, 'A place has a name of 1 to 200 characters.'];
        yield 'empty name' => ['a', '', null, 'A place has a name of 1 to 200 characters.'];
        yield 'long name' => ['a', str_repeat('é', 201), null, 'A place has a name of 1 to 200 characters.'];
        yield 'long description' => ['a', 'A', str_repeat('é', 501), 'The description of a place holds 500 characters at most.'];
    }

    #[DataProvider('refusedPlaces')]
    public function testARefusedPlace(string $id, string $name, ?string $description, string $message): void
    {
        $this->expectExceptionObject(InvalidMap::because($message));

        MapPlace::of($id, $name, Coordinates::of(0, 0), $description);
    }

    public function testAnAddressIsAPathOfTheSiteOrAnAbsoluteAddress(): void
    {
        self::assertSame('/plans/hall.png', SiteAddress::of('/plans/hall.png')->value);
        self::assertSame('https://cdn.example.org/a.png', SiteAddress::of('https://CDN.example.org/a.png')->value);
        self::assertSame('http://localhost/a', SiteAddress::of('http://localhost/a', true)->value);
        self::assertSame('/a//b/c\\d', SiteAddress::of('/a//b/c\\d')->value, 'only the start of a path may name another host');
        self::assertSame('/'.str_repeat('a', 2047), SiteAddress::of('/'.str_repeat('a', 2047))->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'another host' => ['//evil.example/a'];
        yield 'a backslash' => ['/\\evil.example/a'];
        yield 'a space' => ['/a b'];
        yield 'a control' => ["/a\x01"];
        yield 'a delete' => ["/a\x7f"];
        yield 'too long' => ['/'.str_repeat('a', 2048)];
        yield 'relative' => ['plans/a.png'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'localhost' => ['http://localhost/a'];
    }

    #[DataProvider('refusedAddresses')]
    public function testARefusedAddress(string $text): void
    {
        $this->expectException(InvalidMap::class);

        SiteAddress::of($text);
    }

    public function testTheReasonOfARefusedAddress(): void
    {
        $this->expectExceptionObject(InvalidMap::because('"//x.example" is not a path of the site.'));

        SiteAddress::of('//x.example');
    }

    public function testTheReasonOfAnAddressPhilUrlRefuses(): void
    {
        $this->expectException(InvalidMap::class);
        $this->expectExceptionMessageMatches('/^"ftp:\/\/x\.example" is neither a path of the site nor an address: ./');

        SiteAddress::of('ftp://x.example');
    }
}
