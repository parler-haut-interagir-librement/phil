<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventSchema;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\Recurrence\RecurrenceRule;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Node;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(CalendarExtension::class)]
#[CoversClass(EventSchema::class)]
final class CalendarExtensionTest extends TestCase
{
    public function testWhenAnOccurrenceTakesPlaceIsWrittenInALanguage(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $at = static fn (string $moment): DateTimeImmutable => new DateTimeImmutable($moment, $zone);
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $occurrence = static function (string $start, string $end, bool $allDay = false) use ($at): Occurrence {
            $event = Event::schedule('x', $at($start), $at($end), $allDay);

            return $event->occurrences($at('2000-01-01'), $at('2100-01-01'))[0];
        };

        self::assertSame('mardi 10 novembre 2026, 19 h 00 – 22 h 00', $extension->when($occurrence('2026-11-10 19:00', '2026-11-10 22:00'), 'fr_CA'));
        self::assertSame("Tuesday, November 10, 2026, 7:00\u{202F}PM – Wednesday, November 11, 2026, 1:00\u{202F}AM", $extension->when($occurrence('2026-11-10 19:00', '2026-11-11 01:00'), 'en_US'));
        self::assertSame('vendredi 10 juillet 2026', $extension->when($occurrence('2026-07-10', '2026-07-10', true), 'fr_CA'));
        self::assertSame('vendredi 10 juillet 2026 – dimanche 12 juillet 2026', $extension->when($occurrence('2026-07-10', '2026-07-12', true), 'fr_CA'));

        $default = Locale::getDefault();
        Locale::setDefault('fr_CA');

        try {
            self::assertSame('vendredi 10 juillet 2026', $extension->when($occurrence('2026-07-10', '2026-07-10', true)));
        } finally {
            Locale::setDefault($default);
        }

        self::assertSame([], $extension->occurrences($at('2026-01-01'), $at('2027-01-01')));
    }

    public function testAnEventIsSchemaOrgData(): void
    {
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $event = Event::schedule('Tournoi de printemps', new DateTimeImmutable('2027-04-10 10:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2027-04-10 18:00', new DateTimeZone('America/Toronto')));

        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Event', 'name' => 'Tournoi de printemps', 'startDate' => '2027-04-10T10:00:00-04:00', 'endDate' => '2027-04-10T18:00:00-04:00', 'eventStatus' => 'https://schema.org/EventScheduled'], $extension->schema($event));

        $event->describe('Une journée de jeux', 'Salle Saint-Denis', 'https://example.com/tournoi');
        $event->tag(['jeux', 'printemps']);
        $event->repeat(RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'));
        $event->except(new DateTimeImmutable('2027-04-17 10:00', new DateTimeZone('America/Toronto')));
        $event->cancel();

        $schema = EventSchema::of($event);

        self::assertSame(['Une journée de jeux', ['@type' => 'Place', 'name' => 'Salle Saint-Denis'], 'https://example.com/tournoi', 'https://schema.org/EventCancelled', 'jeux, printemps'], [$schema['description'], $schema['location'], $schema['url'], $schema['eventStatus'], $schema['keywords']]);
        self::assertSame(['@type' => 'Schedule', 'startDate' => '2027-04-10', 'startTime' => '10:00:00', 'repeatFrequency' => 'P1W', 'repeatCount' => 3, 'exceptDate' => ['2027-04-17'], 'scheduleTimezone' => 'America/Toronto'], $schema['eventSchedule']);

        $plain = Event::schedule('Tournoi </script>', new DateTimeImmutable('2027-04-10', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2027-04-10', new DateTimeZone('America/Toronto')), true);
        self::assertSame('<script type="application/ld+json">'.json_encode(EventSchema::of($plain), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP).'</script>', $extension->jsonLd($plain));
        self::assertStringNotContainsString('</script><', $extension->jsonLd($plain));
        self::assertStringContainsString('"name":"Tournoi \\u003C/script\\u003E"', $extension->jsonLd($plain));

        $graph = new class implements NodeCollector {
            /** @var list<Node> */
            public array $nodes = [];

            public function add(Node ...$nodes): void
            {
                foreach ($nodes as $node) {
                    $this->nodes[] = $node;
                }
            }
        };
        self::assertSame('', new CalendarExtension(new Calendar(new InMemoryEvents()), $graph)->jsonLd($plain));
        self::assertSame([['@type' => 'Event', 'name' => 'Tournoi </script>', 'startDate' => '2027-04-10', 'endDate' => '2027-04-10', 'eventStatus' => 'https://schema.org/EventScheduled']], array_map(static fn (Node $node): array => $node->toArray(), $graph->nodes));

        $allDay = Event::schedule('Congé', new DateTimeImmutable('2027-05-24', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2027-05-24', new DateTimeZone('America/Toronto')), true);
        self::assertSame(['2027-05-24', '2027-05-24'], [EventSchema::of($allDay)['startDate'], EventSchema::of($allDay)['endDate']]);
    }

    /**
     * The views' words: days in words, a period's title, patterns, times and hours, and the links of a browser without
     * JavaScript.
     */
    public function testTheViewsWords(): void
    {
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $zone = new DateTimeZone('America/Toronto');
        $day = static fn (string $date): DateTimeImmutable => new DateTimeImmutable($date, $zone);

        self::assertSame(['1er et 2 septembre', 'September 2–4, 2027', 'mardi 1er septembre'], [$extension->days($day('2026-09-01'), $day('2026-09-02'), null, 'fr'), $extension->days($day('2027-09-02'), $day('2027-09-04'), 2026, 'en'), $extension->days($day('2026-09-01'), $day('2026-09-01'), 2026, 'fr_CA')]);
        self::assertSame(
            ['septembre 2026', '14 au 20 septembre', 'mardi 15 septembre', 'septembre 2026', 'September 2026', '13 au 19 septembre'],
            [
                $extension->title(Period::of(CalendarView::Month, $day('2026-09-15'), $zone), 'fr'),
                $extension->title(Period::of(CalendarView::Week, $day('2026-09-15'), $zone), 'fr'),
                $extension->title(Period::of(CalendarView::Day, $day('2026-09-15'), $zone), 'fr'),
                $extension->title(Period::of(CalendarView::List, $day('2026-09-15'), $zone), 'fr'),
                $extension->title(Period::of(CalendarView::Posters, $day('2026-09-15'), $zone), 'en'),
                $extension->title(Period::of(CalendarView::Week, $day('2026-09-15'), $zone, 7), 'fr'),
            ],
        );
        self::assertSame(['mar.', 'mardi', '19:30', '19 h 30', '7:30 PM', '09 h 00', '12:00 AM', '23 h 00'], [
            $extension->pattern($day('2026-09-15'), 'EEE', 'fr'),
            $extension->pattern($day('2026-09-15'), 'EEEE', 'fr_CA'),
            $extension->pattern($day('2026-09-15 19:30'), 'HH:mm', 'fr'),
            $extension->time($day('2026-09-15 19:30'), 'fr_CA'),
            str_replace("\u{202F}", ' ', $extension->time($day('2026-09-15 19:30'), 'en_US')),
            $extension->hour(9, 'fr_CA'),
            str_replace("\u{202F}", ' ', $extension->hour(0, 'en_US')),
            $extension->hour(23, 'fr_CA'),
        ]);
        self::assertSame(['?view=month&date=2026-09-15', '?view=week&tags%5B0%5D=larp&tags%5B1%5D=rpg', '?view=day&date=2026-09-01'], [$extension->link('month', '2026-09-15'), $extension->link('week', '', ['larp', 'rpg']), $extension->link('day', '2026-09-01', [])]);
    }

    /**
     * A formatter is kept for each language, time zone, style and pattern: one never writes for another.
     */
    public function testEachLanguageZoneAndPatternHasItsFormatter(): void
    {
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $toronto = new DateTimeImmutable('2026-09-15 19:30', new DateTimeZone('America/Toronto'));
        $paris = $toronto->setTimezone(new DateTimeZone('Europe/Paris'));

        self::assertSame(
            ['19:30', '01:30', '19:30', 'mar.', 'Tue', '19 h 30', '01 h 30', '19:30', 'mar.'],
            [
                $extension->pattern($toronto, 'HH:mm', 'fr'),
                $extension->pattern($paris, 'HH:mm', 'fr'),
                $extension->pattern($toronto, 'HH:mm', 'en'),
                $extension->pattern($toronto, 'EEE', 'fr'),
                $extension->pattern($toronto, 'EEE', 'en'),
                $extension->time($toronto, 'fr_CA'),
                $extension->time($paris, 'fr_CA'),
                $extension->pattern($toronto, 'HH:mm', 'fr'),
                $extension->pattern($toronto, 'EEE', 'fr'),
            ],
        );
    }

    public function testSixtyFourFormattersAreKeptThenBuiltAgain(): void
    {
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $moment = new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('UTC'));
        $formatters = new ReflectionProperty(CalendarExtension::class, 'formatters');
        $locales = array_map(static fn (int $number): string => 'en_'.$number, range(1, 63));

        foreach ($locales as $locale) {
            $extension->pattern($moment, 'y', $locale);
        }

        $extension->pattern($moment, 'MMMM', 'en_1');
        /** @var array<string, array<int, array<int, array<string, array<string, IntlDateFormatter>>>>> $kept */
        $kept = $formatters->getValue($extension);
        self::assertSame($locales, array_keys($kept));
        self::assertSame(['y', 'MMMM'], array_keys($kept['en_1'][IntlDateFormatter::NONE][IntlDateFormatter::NONE]['UTC']));

        $first = $kept['en_1'][IntlDateFormatter::NONE][IntlDateFormatter::NONE]['UTC']['y'];
        self::assertSame('2026', $extension->pattern($moment, 'y', 'en_1'));
        /** @var array<string, array<int, array<int, array<string, array<string, IntlDateFormatter>>>>> $kept */
        $kept = $formatters->getValue($extension);
        self::assertSame($first, $kept['en_1'][IntlDateFormatter::NONE][IntlDateFormatter::NONE]['UTC']['y']);
        self::assertCount(63, $kept);

        self::assertSame('2026', $extension->pattern($moment, 'y', 'en_64'));
        /** @var array<string, mixed> $kept */
        $kept = $formatters->getValue($extension);
        self::assertSame(['en_64'], array_keys($kept));

        // The count starts again: 64 formatters, then the 65th empties the cache once more.
        foreach (range(65, 127) as $number) {
            $extension->pattern($moment, 'y', 'en_'.$number);
        }

        self::assertCount(64, (array) $formatters->getValue($extension));
        $extension->pattern($moment, 'y', 'en_128');
        self::assertSame(['en_128'], array_keys((array) $formatters->getValue($extension)));
    }
}
