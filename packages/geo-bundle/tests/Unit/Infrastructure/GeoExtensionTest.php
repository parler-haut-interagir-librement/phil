<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\Geo\Coordinates;
use Phil\Geo\Distance;
use Phil\GeoBundle\Infrastructure\Twig\GeoExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(GeoExtension::class)]
final class GeoExtensionTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testADistanceIsComputedBetweenTwoPositions(): void
    {
        $extension = new GeoExtension();
        $montreal = Coordinates::of(45.5017, -73.5673);
        $quebec = Coordinates::of(46.8139, -71.208);

        self::assertEqualsWithDelta(233_029.3, $extension->distance($montreal, $quebec)?->meters, 0.1);
        self::assertNull($extension->distance(null, $quebec));
        self::assertNull($extension->distance($montreal, null));
    }

    public function testADistanceIsWrittenForPeople(): void
    {
        $extension = new GeoExtension();

        self::assertSame('850 m', $extension->formatDistance(Distance::meters(849.6)));
        self::assertSame('999 m', $extension->formatDistance(Distance::meters(999.4)));
        self::assertSame('1 km', $extension->formatDistance(Distance::meters(1_000)));
        self::assertSame('1 km', $extension->formatDistance(Distance::meters(999.6), 'en'));
        self::assertSame('1 km', $extension->formatDistance(Distance::meters(999.5), 'en'));
        self::assertSame('12,3 km', $extension->formatDistance(Distance::meters(12_345)));
        self::assertSame('12.3 km', $extension->formatDistance(Distance::meters(12_345), 'en'));
        self::assertSame('0.5 mi', $extension->formatDistance(Distance::miles(0.5), 'en', 'imperial'));
        self::assertSame('7.7 mi', $extension->formatDistance(Distance::kilometers(12.345), 'en', 'imperial'));
        self::assertSame('', $extension->formatDistance(null));
    }

    /**
     * The formatter of a locale is kept, and the default locale is the one of the moment.
     */
    public function testEachLocaleKeepsItsOwnFormatter(): void
    {
        $extension = new GeoExtension();

        self::assertSame('12,3 km', $extension->formatDistance(Distance::meters(12_345)));
        Locale::setDefault('en_US');
        self::assertSame('12.3 km', $extension->formatDistance(Distance::meters(12_345)));
        self::assertSame('1,234.6 km', $extension->formatDistance(Distance::meters(1_234_567)));
        self::assertSame('12,3 km', $extension->formatDistance(Distance::meters(12_345), 'fr_CA'));
        self::assertSame('12.3 km', $extension->formatDistance(Distance::meters(12_345)));
    }

    public function testAPlaceCarriesItsTargetAndItsPosition(): void
    {
        $extension = new GeoExtension();
        $place = Coordinates::of(45.5016891, -73.5672562);

        self::assertSame('data-phil--geo-bundle--distance-sort-target="item" data-latitude="45.5016891" data-longitude="-73.5672562"', $extension->attributes($place));
        self::assertSame('data-phil--geo-bundle--distance-sort-target="item" data-latitude="45.5" data-longitude="-73.57"', $extension->attributes($place, 2));
        self::assertSame('data-phil--geo-bundle--distance-sort-target="item"', $extension->attributes(null));
        self::assertSame('phil--geo-bundle--distance-sort', GeoExtension::CONTROLLER);
    }

    public function testAPositionIsGeoCoordinates(): void
    {
        self::assertSame(['@type' => 'GeoCoordinates', 'latitude' => 45.5, 'longitude' => -73.5], new GeoExtension()->schema(Coordinates::of(45.5, -73.5)));
        self::assertNull(new GeoExtension()->schema(null));
    }

    public function testTheFormattersOfSixtyFourLocalesAreKeptThenBuiltAgain(): void
    {
        $extension = new GeoExtension();
        $distance = Distance::meters(850);
        $formatters = new ReflectionProperty(GeoExtension::class, 'formatters');
        $locales = array_map(static fn (int $number): string => 'en_'.$number, range(1, 64));

        foreach ($locales as $locale) {
            $extension->formatDistance($distance, $locale);
        }

        self::assertSame($locales, array_keys((array) $formatters->getValue($extension)));

        $first = ((array) $formatters->getValue($extension))['en_1'];
        self::assertSame('850 m', $extension->formatDistance($distance, 'en_1'));
        self::assertSame($first, ((array) $formatters->getValue($extension))['en_1']);
        self::assertCount(64, (array) $formatters->getValue($extension));

        self::assertSame('850 m', $extension->formatDistance($distance, 'en_65'));
        self::assertSame(['en_65'], array_keys((array) $formatters->getValue($extension)));
    }
}
