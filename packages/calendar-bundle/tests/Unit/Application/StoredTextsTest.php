<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use ArrayIterator;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\EventTextSet;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\View\EventItems;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryAttendances;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\StoredDatabase;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * The texts of events read from stored translations, and what shows them: the views' items and the Twig functions.
 */
#[CoversClass(EventTexts::class)]
#[CoversClass(EventTextSet::class)]
#[CoversClass(EventItems::class)]
#[CoversClass(CalendarExtension::class)]
final class StoredTextsTest extends TestCase
{
    private EntityManager $manager;

    private Event $event;

    private LocaleAwareInterface $current;

    private EventTexts $texts;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = StoredDatabase::entityManager();
        $this->current = new class implements LocaleAwareInterface {
            public string $locale = 'fr';

            #[Override]
            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            #[Override]
            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $this->texts = new EventTexts(new Translations($this->manager), DeclaredLocales::fromArray(['en' => [], 'fr' => [], 'fr_CA' => ['fr']]), $this->current);

        $zone = new DateTimeZone('America/Toronto');
        $venue = new Venue('Grande salle');
        $this->event = Event::schedule('Soirée jeux', new DateTimeImmutable('2026-11-10 19:00', $zone), new DateTimeImmutable('2026-11-10 22:00', $zone), timezone: 'America/Toronto');
        $this->event->describe('Apportez vos jeux.');
        $this->event->placeAt($venue);

        $this->manager->persist($venue);
        $this->manager->persist($this->event);
        $this->texts->translate($this->event, 'en')->write('Game night', 'Bring your games.');
        $this->texts->translateVenue($venue, 'en')->rename('Main hall');
        $this->manager->flush();
        $this->manager->clear();

        $this->event = $this->manager->find(Event::class, $this->event->id) ?? self::fail('Saved.');
    }

    public function testTheTextsOfALocale(): void
    {
        $english = $this->texts->of([$this->event], 'en');
        self::assertSame(['Game night', 'Bring your games.', 'Main hall', true], [$english->title($this->event), $english->description($this->event), $english->place($this->event), $english->isTranslated($this->event)]);

        // A locale whose chain has no translation, and one the application does not serve, show the event as written.
        foreach (['fr_CA', 'fr', 'de', 'not a locale'] as $locale) {
            $own = $this->texts->of([$this->event], $locale);
            self::assertSame(['Soirée jeux', 'Apportez vos jeux.', 'Grande salle', false], [$own->title($this->event), $own->description($this->event), $own->place($this->event), $own->isTranslated($this->event)], $locale);
        }

        self::assertSame(['en'], $this->texts->locales($this->event));
        self::assertSame('Game night', $this->texts->translate($this->event, 'en')->title());
        self::assertSame('Main hall', $this->texts->translateVenue($this->event->venue() ?? self::fail('A venue.'), 'en')->name());
    }

    public function testTheLocaleOfTheRequest(): void
    {
        self::assertFalse($this->texts->of([$this->event])->isTranslated($this->event), 'French is the request’s');

        $this->current->setLocale('en');
        self::assertSame('Game night', $this->texts->of([$this->event])->title($this->event));
        self::assertSame('Soirée jeux', $this->texts->of([$this->event], 'fr')->title($this->event), 'the locale asked comes before the request’s');
        self::assertSame('Game night', new EventTexts(new Translations($this->manager), DeclaredLocales::fromArray(['en' => []]), $this->current)->of([$this->event])->title($this->event));
    }

    public function testWithoutAServedLocaleOrWithoutTranslations(): void
    {
        $translations = new Translations($this->manager);

        self::assertSame('Soirée jeux', new EventTexts($translations)->of([$this->event], 'en')->title($this->event));
        self::assertSame('Soirée jeux', new EventTexts($translations, DeclaredLocales::fromArray(['en' => []]))->of([$this->event])->title($this->event));
        self::assertSame('Soirée jeux', new EventTexts(null, DeclaredLocales::fromArray(['en' => []]))->of([$this->event], 'en')->title($this->event));
        self::assertSame([], new EventTexts()->locales($this->event));
    }

    public function testTheItemsOfTheViewsAreInTheRequestsLocale(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $events = new InMemoryEvents();
        $events->save($this->event);

        $calendar = new Calendar($events);
        $from = new DateTimeImmutable('2026-11-01', $zone);
        $until = new DateTimeImmutable('2026-12-01', $zone);

        $this->current->setLocale('en');
        self::assertSame(['Game night'], array_map(static fn (Item $item): string => $item->title, new EventItems($calendar, $events, $this->texts)->items($from, $until)));
        self::assertSame(['Soirée jeux'], array_map(static fn (Item $item): string => $item->title, new EventItems($calendar, $events)->items($from, $until)));
    }

    public function testTheTwigFunctionsWithTheirServices(): void
    {
        $events = new InMemoryEvents();
        $events->save($this->event);

        $calendar = new Calendar($events);
        $attendances = new InMemoryAttendances();
        $attendees = new Attendees($attendances, new MockClock('2026-11-01 12:00 UTC'));
        $extension = new CalendarExtension($calendar, null, $this->texts, $attendees, new PersonalCalendar($calendar, $attendances, $events));
        $ana = Member::of('member', 'ana');
        $zone = new DateTimeZone('America/Toronto');
        $from = new DateTimeImmutable('2026-11-01', $zone);
        $through = new DateTimeImmutable('2026-12-01', $zone);

        self::assertSame('Game night', $extension->texts($this->event, 'en')->title($this->event));
        self::assertSame('Game night', $extension->texts([$this->event], 'en')->title($this->event));
        self::assertSame('Game night', $extension->texts(new ArrayIterator([$this->event]), 'en')->title($this->event));

        self::assertSame([0, false, null], [$extension->attendance($this->event), $extension->attends($this->event, 'member', 'ana'), $extension->placesLeft($this->event)]);
        $attendees->attend($this->event, $ana);
        $this->event->limitTo(5);
        self::assertSame([1, true, 4], [$extension->attendance($this->event), $extension->attends($this->event, 'member', 'ana'), $extension->placesLeft($this->event)]);
        self::assertSame(['member:ana'], array_map(static fn (Member $member): string => (string) $member, $attendees->attendees($this->event)));

        [$occurrence] = $extension->personal('member', 'ana', $from, $through);
        self::assertSame($this->event, $occurrence->event);
        self::assertSame([], $extension->personal('member', 'ben', $from, $through));
    }
}
