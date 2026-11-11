<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Twig\OpeningHoursExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

#[CoversClass(OpeningHoursExtension::class)]
final class OpeningHoursExtensionTest extends TestCase
{
    private function hours(): OpeningHours
    {
        return OpeningHours::of(
            [1 => HoursText::day('09:00-17:00'), 2 => HoursText::day('09:00-17:00'), 3 => HoursText::day('09:00-17:00'), 6 => HoursText::day('10:00-12:00, 13:00-24:00')],
            HoursText::specials("2026-11-01 # Past\n2026-12-24..2026-12-26 10:00-14:00 # Holidays\n2026-12-31"),
            'America/Toronto',
        );
    }

    private function clock(string $now): ClockInterface
    {
        return new readonly class(new DateTimeImmutable($now)) implements ClockInterface {
            public function __construct(
                private DateTimeImmutable $now,
            ) {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    public function testWhetherThePlaceIsOpenAndWhenItNextOpens(): void
    {
        $extension = new OpeningHoursExtension($this->clock('2026-11-02 10:00 America/Toronto'));

        self::assertTrue($extension->isOpen($this->hours()));
        self::assertFalse($extension->isOpen($this->hours(), new DateTimeImmutable('2026-11-02 08:00 America/Toronto')));
        self::assertFalse($extension->isOpen(null));
        self::assertSame('2026-11-03 09:00', $extension->next($this->hours())?->format('Y-m-d H:i'));
        self::assertSame('2026-11-02 09:00', $extension->next($this->hours(), new DateTimeImmutable('2026-11-02 08:00 America/Toronto'))?->format('Y-m-d H:i'));
        self::assertNull($extension->next(null));
    }

    public function testWithoutClockNowIsTheSystemsTime(): void
    {
        $always = OpeningHours::of(array_fill(1, 7, HoursText::day('00:00-24:00')));

        self::assertTrue(new OpeningHoursExtension(new NativeClock())->isOpen($always));
    }

    public function testTheWeekIsWrittenInALanguage(): void
    {
        $extension = new OpeningHoursExtension(new NativeClock());

        self::assertSame([
            ['days' => 'lundi – mercredi', 'heading' => 'Lundi – mercredi', 'hours' => '09 h 00 – 17 h 00'],
            ['days' => 'jeudi – vendredi', 'heading' => 'Jeudi – vendredi', 'hours' => null],
            ['days' => 'samedi', 'heading' => 'Samedi', 'hours' => '10 h 00 – 12 h 00, 13 h 00 – 00 h 00'],
            ['days' => 'dimanche', 'heading' => 'Dimanche', 'hours' => null],
        ], $extension->week($this->hours(), 'fr_CA'));
        self::assertSame(['Monday – Wednesday', 'Monday – Wednesday'], array_values(array_intersect_key($extension->week($this->hours(), 'en_US')[0], ['days' => 0, 'heading' => 0])));
        self::assertSame(['Montag – Mittwoch', 'Montag – Mittwoch'], array_values(array_intersect_key($extension->week($this->hours(), 'de')[0], ['days' => 0, 'heading' => 0])));
        self::assertSame(['понедельник – среда', 'Понедельник – среда'], array_values(array_intersect_key($extension->week($this->hours(), 'ru')[0], ['days' => 0, 'heading' => 0])));
        self::assertSame([], $extension->week(null));
    }

    public function testTheSpecialHoursToComeAreWrittenInALanguage(): void
    {
        $extension = new OpeningHoursExtension($this->clock('2026-11-02 10:00 America/Toronto'));

        self::assertSame([
            ['period' => '24 déc. 2026 – 26 déc. 2026', 'label' => 'Holidays', 'hours' => '10 h 00 – 14 h 00'],
            ['period' => '31 déc. 2026', 'label' => null, 'hours' => null],
        ], $extension->specials($this->hours(), locale: 'fr_CA'));
        self::assertCount(1, $extension->specials($this->hours(), new DateTimeImmutable('2026-12-27 00:00 America/Toronto'), 'fr_CA'));
        self::assertCount(2, $extension->specials($this->hours(), new DateTimeImmutable('2026-12-26 23:59 America/Toronto'), 'fr_CA'));
        self::assertSame([], $extension->specials(null));
    }

    public function testTodayIsTheDayItIsAtThePlace(): void
    {
        $christmas = OpeningHours::of([], HoursText::specials('2026-12-25 # Christmas'), 'America/Toronto');

        // 22:00 on Christmas Day in Toronto, already the 26th in UTC.
        self::assertSame([['period' => 'Dec 25, 2026', 'label' => 'Christmas', 'hours' => null]], new OpeningHoursExtension($this->clock('2026-12-26 03:00 UTC'))->specials($christmas, locale: 'en_US'));
        self::assertCount(1, new OpeningHoursExtension(new NativeClock())->specials($christmas, new DateTimeImmutable('2026-12-26 04:59 UTC'), 'en_US'));
        self::assertSame([], new OpeningHoursExtension(new NativeClock())->specials($christmas, new DateTimeImmutable('2026-12-26 05:00 UTC'), 'en_US'));

        // Midnight in Tokyo on the 26th, still the 25th in UTC.
        $tokyo = OpeningHours::of([], HoursText::specials('2026-12-25 # Christmas'), 'Asia/Tokyo');
        self::assertSame([], new OpeningHoursExtension($this->clock('2026-12-25 15:00 UTC'))->specials($tokyo));
    }

    public function testHoursAreSchemaOrg(): void
    {
        $extension = new OpeningHoursExtension(new NativeClock());

        self::assertSame(['https://schema.org/Monday', 'https://schema.org/Tuesday', 'https://schema.org/Wednesday'], $extension->schema($this->hours())[0]['dayOfWeek'] ?? null);
        self::assertSame([], $extension->schema(null));
    }
}
