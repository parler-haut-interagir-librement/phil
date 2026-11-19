<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\View\AlmanacItems;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\DaySpan;
use Phil\CalendarBundle\Application\View\EventItems;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventItems::class)]
#[CoversClass(AlmanacItems::class)]
#[CoversClass(Items::class)]
#[CoversClass(DaySpan::class)]
final class SourcesTest extends ViewTestCase
{
    /**
     * The occurrences of the period as items in its time zone: times moved, whole days kept, hosts read once per event.
     */
    public function testTheEvents(): void
    {
        $events = new InMemoryEvents();
        $calendar = new Calendar($events);
        $weekly = $calendar->schedule($this->draft('Club', '2026-09-01 23:00', '2026-09-02 01:00', 'UTC', 'FREQ=WEEKLY;COUNT=3', ['jeu']));
        $calendar->host($weekly, 'shop', '7');
        $fair = $calendar->schedule($this->draft('Fair', '2026-09-19', '2026-09-20', 'Europe/Paris', allDay: true));
        $fair->cancel();

        $calendar->schedule($this->draft('Edge', '2026-10-01 04:00', '2026-10-01 05:00', 'UTC'));
        $atVenue = $this->draft('Venue', '2026-09-25 23:00', '2026-09-26 01:00', 'UTC');
        $atVenue->venue = new Venue('Salle Le Royal');
        $atVenue->image = '/affiches/salle.jpg';

        $calendar->schedule($atVenue);
        $events->hostQueries = 0;

        $items = new EventItems($calendar, $events)->items($this->day('2026-09-01'), $this->day('2026-10-01'));

        self::assertSame(
            [
                ['Club', '2026-09-01 19:00', '2026-09-01 21:00', false, ['jeu'], ['shop:7'], false],
                ['Club', '2026-09-08 19:00', '2026-09-08 21:00', false, ['jeu'], ['shop:7'], false],
                ['Club', '2026-09-15 19:00', '2026-09-15 21:00', false, ['jeu'], ['shop:7'], false],
                ['Fair', '2026-09-19 00:00', '2026-09-20 00:00', true, [], [], true],
                ['Venue', '2026-09-25 19:00', '2026-09-25 21:00', false, [], [], false],
            ],
            array_map(static fn (Item $item): array => [$item->title, $item->start->format('Y-m-d H:i'), $item->end->format('Y-m-d H:i'), $item->allDay, $item->tags, $item->hosts, $item->cancelled], $items),
        );
        self::assertSame(['America/Toronto', null, 'Café X', 'https://example.com/', 'Bienvenue.', Item::EVENT, null], [$items[3]->start->getTimezone()->getName(), $items[0]->venue, $items[0]->place, $items[0]->url, $items[0]->description, $items[0]->source, $items[0]->image]);
        self::assertStringStartsWith($weekly->id.'@20260901T230000Z', $items[0]->id);
        self::assertSame(1, $events->hostQueries, 'the hosts of every event are read in one query');
        self::assertSame([$atVenue->venue->id, 'Salle Le Royal', '/affiches/salle.jpg'], [$items[4]->venue, $items[4]->place, $items[4]->image], 'the venue before the place said');
        self::assertCount(1, new EventItems($calendar, $events)->items($this->day('2026-09-30'), $this->day('2026-10-01 00:01')), 'the end is excluded to the second');
    }

    public function testTheAlmanac(): void
    {
        $almanac = new Almanac([
            new Definition('noel', 'Noël', RuleParser::parse('12-25'), tags: ['fete']),
            new Definition('moon', 'Pleine lune', RuleParser::parse('full moon'), allDay: false),
            new Definition('fetes', 'Fêtes', RuleParser::parse('12-24'), days: 3),
        ], new DateTimeZone('Europe/Paris'));

        $items = new AlmanacItems($almanac)->items($this->day('2026-12-20'), $this->day('2026-12-27'));

        self::assertSame(
            [
                ['almanac:fetes@20261224T000000', 'Fêtes', '2026-12-24 00:00', '2026-12-26 00:00', true, []],
                ['almanac:moon@20261224T022808', 'Pleine lune', '2026-12-23 20:28', '2026-12-23 20:28', false, []],
                ['almanac:noel@20261225T000000', 'Noël', '2026-12-25 00:00', '2026-12-25 00:00', true, ['fete']],
            ],
            array_map(static fn (Item $item): array => [$item->id, $item->title, $item->start->format('Y-m-d H:i'), $item->end->format('Y-m-d H:i'), $item->allDay, $item->tags], $items),
        );
        self::assertSame(['America/Toronto', 'America/Toronto', Item::ALMANAC, null], [$items[0]->start->getTimezone()->getName(), $items[1]->end->getTimezone()->getName(), $items[0]->source, $items[0]->url]);
    }

    public function testTheAlmanacIsAskedForAtEachPeriod(): void
    {
        $definitions = [new Definition('noel', 'Noël', RuleParser::parse('12-25'))];
        $built = 0;
        $items = new AlmanacItems(static function () use (&$definitions, &$built): Almanac {
            ++$built;

            return new Almanac($definitions, new DateTimeZone('America/Toronto'));
        });

        self::assertCount(1, $items->items($this->day('2026-12-20'), $this->day('2026-12-27')));
        $definitions[] = new Definition('veille', 'Veille de Noël', RuleParser::parse('12-24'));
        self::assertCount(2, $items->items($this->day('2026-12-21'), $this->day('2026-12-27')));
        self::assertSame(2, $built);
    }

    public function testAPeriodTheAlmanacCannotComputeHoldsNoAlmanacItem(): void
    {
        $almanac = new Almanac([new Definition('mardi-gras', 'Mardi gras', RuleParser::parse('easter - 47 days'))], new DateTimeZone('America/Toronto'));

        self::assertSame([], new AlmanacItems($almanac)->items($this->day('1500-03-01'), $this->day('1500-04-01')));
        self::assertCount(1, new AlmanacItems($almanac)->items($this->day('2027-01-01'), $this->day('2027-12-31')));
    }

    /**
     * Every source in one list, by start, whole days first, then title; filtered by tag.
     */
    public function testEverySource(): void
    {
        $first = new readonly class([$this->at('B', '2026-09-02 00:00', '2026-09-02 01:00', ['x']), $this->days('Z', '2026-09-03', tags: ['y'])]) implements ItemSource {
            /** @param list<Item> $items */
            public function __construct(private array $items)
            {
            }

            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return $this->items;
            }
        };
        $second = new readonly class([$this->days('C', '2026-09-02', tags: ['x', 'y']), $this->days('A', '2026-09-02')]) implements ItemSource {
            /** @param list<Item> $items */
            public function __construct(private array $items)
            {
            }

            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return $this->items;
            }
        };
        $items = new Items([$first, $second]);
        $period = Period::of(CalendarView::List, $this->day('2026-09-02'), $this->zone);
        self::assertSame(['A', 'C', 'B', 'Z'], $this->titles($items->of($period)));
        self::assertSame(['C', 'B'], $this->titles($items->of($period, ['x'])));
        self::assertSame([], $items->of($period, []));
        self::assertSame([], new Items([])->of($period));
    }

    public function testTheDaysInWords(): void
    {
        $label = fn (string $first, string $last, string $locale): string => DaySpan::label($this->day($first), $this->day($last), $locale, 2026);

        self::assertSame(['mardi 1er septembre', 'Tuesday, September 1', 'Dienstag, 1. September'], [$label('2026-09-01', '2026-09-01', 'fr_CA'), $label('2026-09-01', '2026-09-01', 'en_CA'), $label('2026-09-01', '2026-09-01', 'de')]);
        self::assertSame(['1er et 2 septembre', 'September 1 and 2', '1. September – 2. September'], [$label('2026-09-01', '2026-09-02', 'fr'), $label('2026-09-01', '2026-09-02', 'en'), $label('2026-09-01', '2026-09-02', 'de_DE')]);
        self::assertSame(['2 au 4 septembre', 'September 2–4'], [$label('2026-09-02', '2026-09-04', 'FR_ca'), $label('2026-09-02', '2026-09-04', 'en')]);
        self::assertSame(['30 août au 2 septembre', 'August 30 – September 2'], [$label('2026-08-30', '2026-09-02', 'fr'), $label('2026-08-30', '2026-09-02', 'en')]);
        self::assertSame(['31 août et 1er septembre', 'August 31 and September 1'], [$label('2026-08-31', '2026-09-01', 'fr'), $label('2026-08-31', '2026-09-01', 'en')]);
        self::assertSame(['30 décembre 2026 au 2 janvier 2027', 'December 30, 2026 – January 2, 2027', '30. Dezember 2026 – 2. Januar 2027'], [$label('2026-12-30', '2027-01-02', 'fr'), $label('2026-12-30', '2027-01-02', 'en'), $label('2026-12-30', '2027-01-02', 'de')]);
        self::assertSame(['lundi 1er mars 2027', 'Monday, March 1, 2027', 'Montag, 1. März 2027'], [$label('2027-03-01', '2027-03-01', 'fr'), $label('2027-03-01', '2027-03-01', 'en'), $label('2027-03-01', '2027-03-01', 'de')]);
        self::assertSame(['15 et 16 mars 2027', 'March 15 and 16, 2027'], [$label('2027-03-15', '2027-03-16', 'fr'), $label('2027-03-15', '2027-03-16', 'en')]);
        self::assertSame(['15 au 17 mars 2027', 'March 15–17, 2027'], [$label('2027-03-15', '2027-03-17', 'fr'), $label('2027-03-15', '2027-03-17', 'en')]);
        self::assertSame(['30 mars au 2 avril 2027', 'March 30 – April 2, 2027'], [$label('2027-03-30', '2027-04-02', 'fr'), $label('2027-03-30', '2027-04-02', 'en')]);
        self::assertSame(['31 mars et 1er avril 2027', 'March 31 and April 1, 2027'], [$label('2027-03-31', '2027-04-01', 'fr'), $label('2027-03-31', '2027-04-01', 'en')]);
        self::assertSame(['31 décembre 2026 et 1er janvier 2027', 'December 31, 2026 and January 1, 2027'], [$label('2026-12-31', '2027-01-01', 'fr'), $label('2026-12-31', '2027-01-01', 'en')]);
        self::assertSame('mardi 15 septembre', DaySpan::label($this->day('2026-09-15'), $this->day('2026-09-15'), 'fr'), "the year of reference is the first day's by default");
        self::assertSame('8 et 9 mars', $label('2026-03-08', '2026-03-09', 'fr'), 'across the change of time');
        self::assertSame(
            ['Tuesday 1 September', '1 and 2 September', '2–4 September', '30 August – 2 September', '30 December 2026 – 2 January 2027', '15–17 March 2027'],
            [$label('2026-09-01', '2026-09-01', 'en_GB'), $label('2026-09-01', '2026-09-02', 'en_GB'), $label('2026-09-02', '2026-09-04', 'en_AU'), $label('2026-08-30', '2026-09-02', 'en_GB'), $label('2026-12-30', '2027-01-02', 'en_IE'), $label('2027-03-15', '2027-03-17', 'en_GB')],
            'the day first where the locale writes it so',
        );
    }

    /**
     * @param list<Item> $items
     *
     * @return list<string>
     */
    private function titles(array $items): array
    {
        return array_map(static fn (Item $item): string => $item->title, $items);
    }

    /**
     * @param list<string> $tags
     */
    private function draft(string $title, string $start, string $end, string $timezone, ?string $rule = null, array $tags = [], bool $allDay = false): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start, new DateTimeZone($timezone));
        $draft->end = new DateTimeImmutable($end, new DateTimeZone($timezone));
        $draft->allDay = $allDay;
        $draft->timezone = $timezone;
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);
        $draft->description = 'Bienvenue.';
        $draft->location = 'Café X';
        $draft->url = 'https://example.com/';
        $draft->tags = $tags;

        return $draft;
    }
}
