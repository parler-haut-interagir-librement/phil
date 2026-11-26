<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Phil\Almanac\Exception\InvalidRule;
use Phil\UxKit\Application\Festivities;
use Phil\UxKit\Application\Festivity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Festivities::class)]
#[CoversClass(Festivity::class)]
final class FestivitiesTest extends TestCase
{
    private DateTimeZone $zone;

    protected function setUp(): void
    {
        $this->zone = new DateTimeZone('America/Toronto');
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function days(): iterable
    {
        yield 'the first day of the winter holidays' => ['2026-12-20 00:00', 'winter-holidays', '2026-12-20'];
        yield 'the last day of the winter holidays, in the next year' => ['2027-01-02 23:59', 'winter-holidays', '2026-12-20'];
        yield 'the day after them' => ['2027-01-03 00:00', null, null];
        yield 'the day before them' => ['2026-12-19 23:59', null, null];
        yield 'Valentine’s Day' => ['2026-02-14 12:00', 'valentines-day', '2026-02-14'];
        yield 'St. Patrick’s Day' => ['2026-03-17 08:00', 'st-patricks-day', '2026-03-17'];
        yield 'Good Friday, two days before Easter' => ['2026-04-03 10:00', 'easter', '2026-04-03'];
        yield 'Easter Monday' => ['2026-04-06 10:00', 'easter', '2026-04-03'];
        yield 'the first day of Halloween' => ['2026-10-25 00:00', 'halloween', '2026-10-25'];
        yield 'Halloween' => ['2026-10-31 21:00', 'halloween', '2026-10-25'];
        yield 'the day of the March equinox' => ['2026-03-20 18:00', 'spring', '2026-03-20'];
        yield 'the Lunar New Year' => ['2026-02-17 09:00', 'lunar-new-year', '2026-02-17'];
        yield 'Remembrance Day' => ['2026-11-11 11:00', 'remembrance-day', '2026-11-11'];
        yield 'a day of none' => ['2026-08-12 12:00', null, null];
    }

    #[DataProvider('days')]
    public function testTheDefaultHolidayOfADay(string $moment, ?string $key, ?string $start): void
    {
        $festivity = new Festivities(Festivities::DEFAULTS, $this->zone)->at(new DateTimeImmutable($moment, $this->zone));

        self::assertSame($key, $festivity?->key);
        self::assertSame($start, $festivity?->start->format('Y-m-d'));
    }

    public function testAFestivityHasItsTitleItsDecorAndAnIdForItsYear(): void
    {
        $festivity = new Festivities(Festivities::DEFAULTS, $this->zone)->at(new DateTimeImmutable('2026-10-31', $this->zone));

        self::assertNotNull($festivity);
        self::assertSame('Halloween', $festivity->title);
        self::assertSame('bats', $festivity->decor);
        self::assertFalse($festivity->sober);
        self::assertSame('halloween-2026-10-25', $festivity->id());
    }

    public function testTheDayIsCountedInTheTimeZoneOfTheConfiguration(): void
    {
        $festivities = new Festivities(Festivities::DEFAULTS, $this->zone);

        self::assertNull($festivities->at(new DateTimeImmutable('2026-11-11 04:30', new DateTimeZone('UTC'))), 'still November 10 in Toronto');
        self::assertSame('remembrance-day', $festivities->at(new DateTimeImmutable('2026-11-12 04:30', new DateTimeZone('UTC')))?->key, 'still November 11 in Toronto');
    }

    public function testNowWithoutAMoment(): void
    {
        $festivities = new Festivities(['always' => ['title' => 'Always', 'rule' => '01-01', 'days' => 366, 'decor' => 'snow']], $this->zone);

        self::assertSame('always', $festivities->at()?->key);
    }

    public function testASoberHolidayComesFirstThenTheOrderOfTheConfiguration(): void
    {
        $holidays = [
            'autumn' => ['title' => 'Autumn', 'rule' => '11-01', 'days' => 30, 'decor' => 'leaves'],
            'maple' => ['title' => 'Maple', 'rule' => '11-10', 'days' => 3, 'decor' => 'maple-leaves'],
            'remembrance' => ['title' => 'Remembrance', 'rule' => '11-11', 'decor' => 'poppy'],
            'mourning' => ['title' => 'Mourning', 'rule' => '11-20', 'decor' => 'leaves', 'sober' => true],
            'remembered' => ['title' => 'Remembered', 'rule' => '11-20', 'decor' => 'petals', 'sober' => true],
            'not-sober' => ['title' => 'Not sober', 'rule' => '11-25', 'decor' => 'poppy', 'sober' => false],
        ];
        $festivities = new Festivities($holidays, $this->zone);
        $on = fn (string $day): ?Festivity => $festivities->at(new DateTimeImmutable($day, $this->zone));

        self::assertSame('autumn', $on('2026-11-10')?->key, 'the first of the list');
        self::assertSame(['remembrance', true], [$on('2026-11-11')?->key, $on('2026-11-11')?->sober], 'the sober one first');
        self::assertSame(['mourning', true], [$on('2026-11-20')?->key, $on('2026-11-20')?->sober], 'the first of the sober ones');
        self::assertSame('autumn', $on('2026-11-25')?->key, 'a poppy said not sober is not chosen first');
        self::assertFalse(new Festivities(['n' => $holidays['not-sober']], $this->zone)->at(new DateTimeImmutable('2026-11-25', $this->zone))?->sober);
    }

    public function testAHolidayUntilAnotherRule(): void
    {
        $festivities = new Festivities(['fall' => ['title' => 'Fall', 'rule' => 'september equinox', 'until' => 'december solstice', 'decor' => 'leaves']], $this->zone);

        self::assertSame('fall', $festivities->at(new DateTimeImmutable('2026-11-30', $this->zone))?->key);
        self::assertNull($festivities->at(new DateTimeImmutable('2026-12-23', $this->zone)));
    }

    public function testEachDefaultDecorIsOneOfTheDecors(): void
    {
        foreach (Festivities::DEFAULTS as $holiday) {
            self::assertContains($holiday['decor'], Festivities::DECORS);
        }

        self::assertSame(['poppy'], Festivities::SOBER);
    }

    public function testAnUnknownDecorIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The decor "confetti" of the holiday "party" is none of "snow", "hearts"');

        new Festivities(['party' => ['title' => 'Party', 'rule' => '01-01', 'decor' => 'confetti']], $this->zone);
    }

    public function testAnUnreadableRuleIsRefusedWithItsHoliday(): void
    {
        try {
            new Festivities(['party' => ['title' => 'Party', 'rule' => 'every day', 'decor' => 'snow']], $this->zone);
            self::fail('An unreadable rule is refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith('The holiday "party": ', $invalidArgumentException->getMessage());
            self::assertInstanceOf(InvalidRule::class, $invalidArgumentException->getPrevious());
            self::assertSame(0, $invalidArgumentException->getCode());
        }
    }

    public function testAnUnreadableUntilIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The holiday "party": ');

        new Festivities(['party' => ['title' => 'Party', 'rule' => '01-01', 'until' => 'later', 'decor' => 'snow']], $this->zone);
    }
}
