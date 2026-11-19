<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use Override;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventSchema;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\EventTextSet;
use Phil\CalendarBundle\Application\PrintableCalendar;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\LocaleDimensions\DeclaredLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;

#[CoversClass(EventTexts::class)]
#[CoversClass(EventTextSet::class)]
#[CoversClass(EventSchema::class)]
#[CoversClass(PrintableCalendar::class)]
#[CoversClass(CalendarExtension::class)]
#[CoversClass(Item::class)]
final class TextsAndPrintTest extends TestCase
{
    public function testWithoutTranslationsEveryEventIsInItsOwnText(): void
    {
        $venue = new Venue('Grande salle');
        $venue->locate('salle B', null);

        $event = $this->event();
        $event->describe('Apportez vos jeux.', 'Ailleurs');
        $event->placeAt($venue);

        $current = new class implements LocaleAwareInterface {
            public function setLocale(string $locale): void
            {
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        foreach ([new EventTexts(), new EventTexts(null, DeclaredLocales::fromArray(['en' => []]), $current), new EventTexts(null, null, $current)] as $texts) {
            $set = $texts->of([$event]);
            self::assertSame(['Soirée', 'Apportez vos jeux.', 'Grande salle', 'Grande salle — salle B', 'Grande salle — salle B', false], [$set->title($event), $set->description($event), $set->venueName($venue), $set->venueLabel($venue), $set->place($event), $set->isTranslated($event)]);
            self::assertSame([], $texts->locales($event));
        }

        try {
            new EventTexts()->translate($event, 'en');
            self::fail('No translations.');
        } catch (LogicException $logicException) {
            self::assertStringContainsString('phil_calendar.translations.locales', $logicException->getMessage());
        }

        $this->expectException(LogicException::class);
        new EventTexts()->translateVenue($venue, 'en');
    }

    public function testASetOfTexts(): void
    {
        $event = $this->event();
        $event->describe('Apportez vos jeux.', 'Café X');

        $venue = new Venue('Grande salle');
        $set = new EventTextSet([$event->id => ['Game night', null]], [$venue->id => 'Main hall']);

        self::assertSame(['Game night', null, true, 'Café X'], [$set->title($event), $set->description($event), $set->isTranslated($event), $set->place($event)], 'a translation is served whole: no French description beside an English title');
        self::assertSame(['Main hall', 'Main hall'], [$set->venueName($venue), $set->venueLabel($venue)]);
        $event->placeAt($venue);
        self::assertSame('Main hall', $set->place($event));

        $schema = EventSchema::of($event, $set);
        self::assertSame(['Game night', ['@type' => 'Place', 'name' => 'Main hall']], [$schema['name'], $schema['location']]);
        self::assertArrayNotHasKey('description', $schema);
        self::assertSame('Soirée', EventSchema::of($event)['name']);
        self::assertSame(['@type' => 'Place', 'name' => 'Grande salle'], EventSchema::of($event)['location'], 'the venue is the place of schema.org data');
    }

    public function testTheTwigFunctionsWithoutTheirServices(): void
    {
        $extension = new CalendarExtension(new Calendar(new InMemoryEvents()));
        $event = $this->event();
        $from = new DateTimeImmutable('2026-11-01');

        self::assertSame('Soirée', $extension->texts($event)->title($event));
        self::assertSame('Soirée', $extension->texts([$event], 'en')->title($event));
        self::assertSame([], $extension->personal('member', 'ana', $from, $from->modify('+1 month')));
        self::assertSame(0, $extension->attendance($event));
        self::assertNull($extension->placesLeft($event));
        self::assertFalse($extension->attends($event, 'member', 'ana'));
        self::assertSame('Soirée', $extension->schema($event, 'en')['name']);
    }

    public function testTheMonthsPrinted(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $source = new readonly class($zone) implements ItemSource {
            public function __construct(
                private DateTimeZone $zone,
            ) {
            }

            #[Override]
            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                $at = fn (string $time): DateTimeImmutable => new DateTimeImmutable($time, $this->zone);

                return [
                    new Item('a', 'Festival', $at('2026-10-30'), $at('2026-11-02'), true, tags: ['fete']),
                    new Item('b', 'Soirée', $at('2026-11-10 19:00'), $at('2026-11-10 21:00'), false),
                    new Item('c', 'Annulé', $at('2026-11-12 19:00'), $at('2026-11-12 20:00'), false, cancelled: true),
                    new Item('d', 'Nuit', $at('2026-11-30 23:00'), $at('2026-12-01 00:00'), false),
                    new Item('e', 'Long', $at('2026-10-01'), $at('2027-01-31'), true),
                ];
            }
        };
        $printed = new PrintableCalendar(new Items([$source]), 'America/Toronto', 7);

        [$november, $december] = $printed->months(2026, 11, 2, 'en');
        self::assertSame(['November 2026', 2026, 11], [$november->title, $november->year, $november->month]);
        self::assertSame(['Long', 'Festival'], $november->events[1]);
        self::assertSame(['Long', 'Festival'], $november->events[2]);
        self::assertSame(['Long'], $november->events[3]);
        self::assertSame(['Long', "7:00\u{202F}PM Soirée"], $november->events[10]);
        self::assertSame(['Long', "× 7:00\u{202F}PM Annulé"], $november->events[12]);
        self::assertSame(['Long', "11:00\u{202F}PM Nuit"], $november->events[30], 'an item that ends at midnight is not on the next day');
        self::assertCount(30, $november->events);
        self::assertSame(['December 2026', 31], [$december->title, \count($december->events)]);
        self::assertSame(['Long'], $december->events[1]);
        self::assertSame([1, 2], array_keys(array_filter($printed->months(2026, 11, 1, 'en', ['fete'])[0]->events)));
        self::assertSame(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], $printed->weekdays('en'));
        self::assertSame(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'], new PrintableCalendar(new Items([]), 'UTC')->weekdays('fr'));
        self::assertStringStartsWith('%PDF-', $printed->document(2026, 12)->render());
        self::assertCount(24, new PrintableCalendar(new Items([]), 'UTC')->months(2026, 1, 24));

        foreach ([[2026, 11, 0], [2026, 11, 25], [2026, 0, 1], [2026, 13, 1], [0, 1, 1], [10000, 1, 1]] as [$year, $month, $count]) {
            try {
                $printed->months($year, $month, $count);
                self::fail('Refused.');
            } catch (InvalidArgumentException $invalid) {
                self::assertSame('A printed calendar is 1 to 24 months from a month of a year from 1 to 9999.', $invalid->getMessage());
            }
        }
    }

    /**
     * A month by default, in its own language's capitals, the days of the month in order, the first and last years.
     */
    public function testThePrintedDefaults(): void
    {
        $empty = new PrintableCalendar(new Items([]), 'UTC');

        self::assertCount(1, $empty->months(2026, 11));
        self::assertCount(1, $empty->months(1, 1));
        self::assertCount(1, $empty->months(9999, 1));
        self::assertSame(1, preg_match_all('#/Type\s*/Page\b#', $empty->document(2026, 12)->render()));
        self::assertSame(2, preg_match_all('#/Type\s*/Page\b#', $empty->document(2026, 12, 2)->render()));
        self::assertSame('Ноябрь 2026', $empty->months(2026, 11, 1, 'ru')[0]->title);
        self::assertSame(['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'], $empty->weekdays('ru'));

        // Two items whose first days come in the other order of their instants: the days of a month are in order.
        $source = new readonly class implements ItemSource {
            #[Override]
            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return [
                    new Item('a', 'Tôt', new DateTimeImmutable('2026-11-10 01:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-10 02:00', new DateTimeZone('America/Toronto')), false),
                    new Item('b', 'Tard', new DateTimeImmutable('2026-11-09 23:00', new DateTimeZone('Etc/GMT+12')), new DateTimeImmutable('2026-11-09 23:30', new DateTimeZone('Etc/GMT+12')), false),
                ];
            }
        };

        self::assertSame([9, 10], array_keys(new PrintableCalendar(new Items([$source]), 'UTC')->months(2026, 11)[0]->events));
    }

    public function testAnItemStartsItsDayAtMidnight(): void
    {
        $item = new Item('a', 'Soirée', new DateTimeImmutable('2026-11-10 19:30:45', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-10 21:00', new DateTimeZone('America/Toronto')), false);

        self::assertSame('2026-11-10 00:00:00', $item->firstDay()->format('Y-m-d H:i:s'));
    }

    private function event(): Event
    {
        return Event::schedule('Soirée', new DateTimeImmutable('2026-11-10 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-10 21:00', new DateTimeZone('America/Toronto')));
    }
}
