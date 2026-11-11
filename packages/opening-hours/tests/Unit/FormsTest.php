<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Tests\Unit;

use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Phil\OpeningHours\Exception\UnreadableOpeningHours;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\OpeningHoursArray;
use Phil\OpeningHours\OpeningHoursSchema;
use Phil\OpeningHours\SpecialHours;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HoursText::class)]
#[CoversClass(OpeningHoursSchema::class)]
#[CoversClass(OpeningHoursArray::class)]
#[CoversClass(UnreadableOpeningHours::class)]
final class FormsTest extends TestCase
{
    private const string SPECIALS = "2026-12-24 09:00-12:00 # Christmas Eve\n2026-12-25 # Christmas\n2027-07-01..2027-08-31 10:00-16:00";

    public function testSpecialHoursAreReadOneALine(): void
    {
        $specials = HoursText::specials("  2026-12-24 9h-12h #  Christmas Eve \r\n\r2026-12-25 #Christmas\n # a comment\n2027-07-01..2027-08-31   10:00-16:00\n");

        self::assertSame(self::SPECIALS, HoursText::specialsToText($specials));
        self::assertSame(['2026-12-24', '2026-12-24', 'Christmas Eve'], [$specials[0]->from, $specials[0]->through, $specials[0]->label]);
        self::assertSame([[], '', ''], [HoursText::specials(''), HoursText::specialsToText([]), HoursText::day(' ')->toText()]);
        self::assertSame('2026-12-25', HoursText::specialsToText([SpecialHours::of('2026-12-25')]));
        self::assertSame(['Eve', 'Christmas'], array_map(static fn (SpecialHours $special): ?string => $special->label, HoursText::specials("2026-12-24 # Eve\r2026-12-25 # Christmas")));
        self::assertSame('Music # Dance', HoursText::specials('2026-06-21 # Music # Dance')[0]->label);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badLines(): iterable
    {
        yield 'no date' => ['Christmas', '"Christmas" is not a line of special hours.'];
        yield 'a date and words' => ['2026-12-25 closed', '"closed" is not a range of hours.'];
        yield 'a bad period' => ['2026-12-25..', '"2026-12-25.." is not a line of special hours.'];
        yield 'text before the date' => ['on 2026-12-25', '"on 2026-12-25" is not a line of special hours.'];
    }

    #[DataProvider('badLines')]
    public function testABadLineIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains($message);

        HoursText::specials($text);
    }

    public function testHoursAreSchemaOrgSpecifications(): void
    {
        $hours = OpeningHours::of([1 => HoursText::day('09:00-12:00, 13:00-17:00'), 2 => HoursText::day('09:00-12:00, 13:00-17:00'), 6 => HoursText::day('10:00-16:00')], HoursText::specials(self::SPECIALS));

        self::assertSame([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'], 'opens' => '09:00', 'closes' => '12:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Monday', 'https://schema.org/Tuesday'], 'opens' => '13:00', 'closes' => '17:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['https://schema.org/Saturday'], 'opens' => '10:00', 'closes' => '16:00'],
            ['@type' => 'OpeningHoursSpecification', 'opens' => '09:00', 'closes' => '12:00', 'validFrom' => '2026-12-24', 'validThrough' => '2026-12-24'],
            ['@type' => 'OpeningHoursSpecification', 'opens' => '00:00', 'closes' => '00:00', 'validFrom' => '2026-12-25', 'validThrough' => '2026-12-25'],
            ['@type' => 'OpeningHoursSpecification', 'opens' => '10:00', 'closes' => '16:00', 'validFrom' => '2027-07-01', 'validThrough' => '2027-08-31'],
        ], OpeningHoursSchema::of($hours));
        self::assertSame([], OpeningHoursSchema::of(OpeningHours::of([])));
    }

    public function testHoursAreAnArrayOfTexts(): void
    {
        $stored = [
            'timezone' => 'America/Toronto',
            'week' => [1 => '09:00-17:00', 6 => '10:00-16:00, 20:00-02:00'],
            'specials' => [
                ['from' => '2026-12-24', 'through' => '2026-12-24', 'hours' => '09:00-12:00', 'label' => 'Christmas Eve'],
                ['from' => '2026-12-25', 'through' => '2026-12-26', 'hours' => '', 'label' => null],
            ],
        ];

        self::assertSame($stored, OpeningHoursArray::from(OpeningHoursArray::to($stored)));
        self::assertSame(['timezone' => 'UTC', 'week' => [], 'specials' => []], OpeningHoursArray::from(OpeningHoursArray::to(['timezone' => 'UTC'])));
        self::assertSame('09:00-17:00', OpeningHoursArray::to(['timezone' => 'UTC', 'week' => ['7' => '09:00-17:00'], 'specials' => [['from' => '2026-12-25', 'through' => '2026-12-25']]])->groups()[1]['hours']->toText());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unreadable(): iterable
    {
        $reason = 'it is not an array with a time zone, a week and a list of special hours';

        yield 'special hours with keys' => [['timezone' => 'UTC', 'specials' => ['x' => []]], $reason];

        yield 'not an array' => ['week', $reason];
        yield 'no time zone' => [['week' => []], $reason];
        yield 'a time zone that is no text' => [['timezone' => 1], $reason];
        yield 'a week that is no array' => [['timezone' => 'UTC', 'week' => '09:00-17:00'], $reason];
        yield 'specials that are no array' => [['timezone' => 'UTC', 'specials' => 'none'], $reason];
        yield 'no such day' => [['timezone' => 'UTC', 'week' => [8 => '09:00-17:00']], '"8" is no day of the week'];
        yield 'a day that is no text' => [['timezone' => 'UTC', 'week' => [1 => 9]], 'a value is not a text'];
        yield 'bad hours' => [['timezone' => 'UTC', 'week' => [1 => 'all day']], '"all day" is not a range of hours'];
        yield 'special hours that are no object' => [['timezone' => 'UTC', 'specials' => ['2026-12-25']], 'special hours are not an object'];
        yield 'special hours without a day' => [['timezone' => 'UTC', 'specials' => [['hours' => '']]], 'a value is not a text'];
        yield 'special hours with a label that is no text' => [['timezone' => 'UTC', 'specials' => [['from' => '2026-12-25', 'through' => '2026-12-25', 'label' => 1]]], 'a value is not a text'];
        yield 'a bad time zone' => [['timezone' => 'Mars/Olympus'], '"Mars/Olympus" is not a time zone'];
    }

    #[DataProvider('unreadable')]
    public function testWhatCannotBeReadBackIsRefused(mixed $data, string $reason): void
    {
        try {
            OpeningHoursArray::to($data);
            self::fail('Unreadable hours were read.');
        } catch (UnreadableOpeningHours $unreadableOpeningHours) {
            self::assertSame('The stored opening hours cannot be read: '.$reason.'.', $unreadableOpeningHours->getMessage());
        }
    }
}
