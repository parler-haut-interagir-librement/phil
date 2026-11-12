<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Infrastructure;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Phil\Almanac\Astronomy\LunarEclipse;
use Phil\Almanac\Astronomy\SolarEclipse;
use Phil\Almanac\Place;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\Definitions;
use Phil\AlmanacBundle\Application\Places;
use Phil\AlmanacBundle\Infrastructure\Twig\SkyExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SkyExtension::class)]
#[CoversClass(Places::class)]
#[CoversClass(AlmanacFactory::class)]
final class SkyExtensionTest extends TestCase
{
    public function testThePlaces(): void
    {
        $places = new Places(['montreal' => ['latitude' => 45.5017, 'longitude' => -73.5673, 'timezone' => 'America/Toronto']]);

        self::assertSame(['montreal'], $places->names());
        $place = $places->get('montreal');
        self::assertSame([45.5017, -73.5673, 'America/Toronto'], [$place->latitude, $place->longitude, $place->zone->getName()]);

        try {
            $places->get('paris');
            self::fail('No such place.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('phil_almanac.places has no place "paris"; it has: montreal.', $invalidArgumentException->getMessage());
        }

        try {
            new Places([])->get(str_repeat('é', 150));
            self::fail('No such place.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('phil_almanac.places has no place "'.str_repeat('é', 100).'"; it has: none.', $invalidArgumentException->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('phil_almanac.places has no place "'.str_repeat('a', 100).'"; it has: none.');
        new Places([])->get(str_repeat('a', 150));
    }

    public function testTheSunTimes(): void
    {
        $extension = $this->extension();

        $day = $extension->sunTimes('montreal', '2026-06-20');
        self::assertSame('2026-06-20 05:05', $day->sunrise()?->format('Y-m-d H:i'));
        self::assertSame('America/Toronto', $day->date->getTimezone()->getName());

        // Text is read in the place's time zone, a date as the instant it is.
        $tokyo = new Place(35.6762, 139.6503, new DateTimeZone('Asia/Tokyo'));
        self::assertSame('2026-06-21', $extension->sunTimes($tokyo, '2026-06-21')->date->format('Y-m-d'));
        self::assertSame('2026-06-21', $extension->sunTimes($tokyo, new DateTime('2026-06-20 18:00 UTC'))->date->format('Y-m-d'));
        self::assertSame(new DateTimeImmutable('today', $tokyo->zone)->format('Y-m-d'), $extension->sunTimes($tokyo)->date->format('Y-m-d'));
    }

    public function testTheEclipses(): void
    {
        $extension = $this->extension();
        $label = static fn (SolarEclipse|LunarEclipse $eclipse): string => ($eclipse instanceof SolarEclipse ? 'solar ' : 'lunar ').$eclipse->at->format('m-d');

        self::assertSame(['solar 02-17', 'lunar 03-03', 'solar 08-12', 'lunar 08-28'], array_map($label, $extension->eclipses('2026-01-01', '2027-01-01')));
        self::assertSame(['solar 02-17', 'solar 08-12'], array_map($label, $extension->eclipses('2026-01-01', new DateTime('2027-01-01'), 'solar')));
        self::assertSame(['lunar 03-03', 'lunar 08-28'], array_map($label, $extension->eclipses(new DateTimeImmutable('2026-01-01'), '2027-01-01', 'lunar')));
        // The bounds are read in the almanac's time zone: the lunar eclipse of August 28, 00:13 in Toronto, is in August.
        self::assertSame(['solar 08-12', 'lunar 08-28'], array_map($label, $extension->eclipses('2026-08-01', '2026-08-28 01:00')));
        self::assertSame([], $extension->eclipses('2026-08-01', '2026-08-28 00:00', 'lunar'));

        try {
            $extension->eclipses('2026-01-01', '2027-01-01', str_repeat('é', 150));
            self::fail('No such body.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('An eclipse is "solar" or "lunar", not "'.str_repeat('é', 100).'".', $invalidArgumentException->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('An eclipse is "solar" or "lunar", not "stellar".');
        $extension->eclipses('2026-01-01', '2027-01-01', 'stellar');
    }

    private function extension(): SkyExtension
    {
        return new SkyExtension(
            new Places(['montreal' => ['latitude' => 45.5017, 'longitude' => -73.5673, 'timezone' => 'America/Toronto']]),
            new AlmanacFactory(new Definitions([]), 'America/Toronto'),
        );
    }
}
