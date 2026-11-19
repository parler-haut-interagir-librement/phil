<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application;

use Closure;
use DateTimeImmutable;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\VenueDraft;
use Phil\CalendarBundle\Application\Venues;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryVenues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(Venue::class)]
#[CoversClass(Venues::class)]
#[CoversClass(VenueDraft::class)]
#[CoversClass(Event::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(EventDraft::class)]
final class VenuesTest extends TestCase
{
    public function testAVenueOfItsOwn(): void
    {
        $venues = new Venues($repository = new InMemoryVenues());
        $draft = new VenueDraft();
        $draft->name = ' Bar Le Cercle ';
        $draft->room = ' salle B ';
        $draft->address = '228, rue Saint-Joseph Est, Québec';
        $draft->latitude = 46.8139;
        $draft->longitude = -71.2252;

        $venue = $venues->create($draft);

        self::assertSame(['Bar Le Cercle', 'salle B', '228, rue Saint-Joseph Est, Québec', [46.8139, -71.2252], null, 'Bar Le Cercle — salle B'], [$venue->name(), $venue->room(), $venue->address(), $venue->coordinates(), $venue->listing(), $venue->label()]);
        self::assertSame($venue, $repository->get($venue->id));
        self::assertSame($draft->latitude, VenueDraft::of($venue)->latitude);

        $draft->name = 'Le Cercle';
        $draft->room = ' ';
        $draft->address = null;
        $draft->latitude = null;
        $draft->longitude = null;
        $draft->listing = 'venue:42';

        $venues->apply($venue, $draft);
        self::assertSame([null, null, null, 'venue:42', 'Le Cercle'], [$venue->room(), $venue->address(), $venue->coordinates(), $venue->listing(), $venue->label()]);
        self::assertSame(['Le Cercle', null, null, null, null, 'venue:42'], [VenueDraft::of($venue)->name, VenueDraft::of($venue)->room, VenueDraft::of($venue)->address, VenueDraft::of($venue)->latitude, VenueDraft::of($venue)->longitude, VenueDraft::of($venue)->listing]);

        $draft->listing = null;
        $venues->apply($venue, $draft);
        self::assertNull($venue->listing());

        $draft->listing = 'venue:4:2';
        try {
            $venues->apply($venue, $draft);
            self::fail('A listing has one colon.');
        } catch (InvalidEvent $invalidEvent) {
            self::assertSame('The reference of a listing is 1 to 64 letters, digits, "_", "." or "-".', $invalidEvent->getMessage());
        }

        $draft->listing = null;

        self::assertSame([$venue], $venues->all());
        $venues->remove($venue);
        self::assertSame([], $venues->all());
    }

    /**
     * A listing of the directory becomes a venue the first time, and the same one after.
     */
    public function testAListingOfTheDirectory(): void
    {
        $venues = new Venues($repository = new InMemoryVenues());

        $first = $venues->ofListing('larp-site', '12', 'Terrain du Nord');
        $again = $venues->ofListing('larp-site', '12', 'Another name');

        self::assertSame($first, $again);
        self::assertSame(['Terrain du Nord', 'larp-site:12'], [$first->name(), $first->listing()]);
        self::assertCount(1, $repository->venues);
        self::assertNotSame($first, $venues->ofListing('larp-site', '13', 'Terrain du Sud'));
    }

    /**
     * @return iterable<string, array{Closure(Venue): void, string}>
     */
    public static function refusals(): iterable
    {
        yield 'no name' => [static fn (Venue $venue) => $venue->rename(' '), 'The name of a venue is 1 to 200 characters.'];
        yield 'a long name' => [static fn (Venue $venue) => $venue->rename(str_repeat('é', 201)), 'The name of a venue is 1 to 200 characters.'];
        yield 'a long room' => [static fn (Venue $venue) => $venue->locate(str_repeat('a', 101), null), 'The room of a venue is 100 characters at most.'];
        yield 'a long room in accents' => [static fn (Venue $venue) => $venue->locate(str_repeat('é', 101), null), 'The room of a venue is 100 characters at most.'];
        yield 'a long address' => [static fn (Venue $venue) => $venue->locate(null, str_repeat('a', 501)), 'The address of a venue is 500 characters at most.'];
        yield 'a latitude alone' => [static fn (Venue $venue) => $venue->locate(null, null, 46.8), 'A venue has a latitude, −90 to 90, and a longitude, −180 to 180, or neither.'];
        yield 'a longitude alone' => [static fn (Venue $venue) => $venue->locate(null, null, null, -71.2), 'or neither.'];
        yield 'a latitude past the pole' => [static fn (Venue $venue) => $venue->locate(null, null, 90.1, 0), 'or neither.'];
        yield 'a latitude past the other pole' => [static fn (Venue $venue) => $venue->locate(null, null, -90.1, 0), 'or neither.'];
        yield 'a longitude past the antimeridian' => [static fn (Venue $venue) => $venue->locate(null, null, 0, 180.1), 'or neither.'];
        yield 'a longitude past the other side' => [static fn (Venue $venue) => $venue->locate(null, null, 0, -180.1), 'or neither.'];
        yield 'a kind alone' => [static fn (Venue $venue) => $venue->listAs('venue', null), 'The reference of a listing is 1 to 64 letters, digits, "_", "." or "-".'];
        yield 'a reference alone' => [static fn (Venue $venue) => $venue->listAs(null, '42'), 'The kind of a listing is 1 to 64 letters, digits, "_", "." or "-".'];
        yield 'a kind with a colon' => [static fn (Venue $venue) => $venue->listAs('a:b', '42'), 'The kind of a listing'];
        yield 'a reference too long' => [static fn (Venue $venue) => $venue->listAs('venue', str_repeat('1', 65)), 'The reference of a listing'];
    }

    /**
     * @param Closure(Venue): void $change
     */
    #[DataProvider('refusals')]
    public function testAVenueIsRefused(Closure $change, string $message): void
    {
        $this->expectException(InvalidEvent::class);
        $this->expectExceptionMessageIsOrContains($message);

        $change(new Venue('Salle'));
    }

    public function testTheLimitsOfAVenue(): void
    {
        $venue = new Venue(str_repeat('é', 200));
        $venue->locate(str_repeat('é', 100), str_repeat('a', 500), 90.0, -180.0);
        self::assertSame(100, mb_strlen((string) $venue->room()));
        $venue->locate(str_repeat('a', 100), str_repeat('a', 500), 90.0, -180.0);
        $venue->listAs(str_repeat('k', 64), str_repeat('r', 64));

        self::assertSame([200, 100, 500, [90.0, -180.0], 129], [mb_strlen($venue->name()), \strlen((string) $venue->room()), \strlen((string) $venue->address()), $venue->coordinates(), \strlen((string) $venue->listing())]);
        $venue->locate(null, null, -90.0, 180.0);
        self::assertSame([-90.0, 180.0], $venue->coordinates());
    }

    /**
     * An event placed at a venue and illustrated by a poster, from its draft.
     */
    public function testAnEventAtAVenueWithAnImage(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $venue = new Venue('Salle Le Royal');
        $draft = new EventDraft();
        $draft->title = 'Salon';
        $draft->start = new DateTimeImmutable('2026-09-19');
        $draft->end = new DateTimeImmutable('2026-09-20');
        $draft->allDay = true;
        $draft->timezone = 'America/Toronto';
        $draft->venue = $venue;
        $draft->image = '/affiches/salon.jpg';

        $event = $calendar->schedule($draft);

        self::assertSame([$venue, '/affiches/salon.jpg'], [$event->venue(), $event->image()]);
        self::assertSame([$venue, '/affiches/salon.jpg'], [EventDraft::of($event)->venue, EventDraft::of($event)->image]);

        $event->illustrate('https://example.com/salon.jpg');
        self::assertSame('https://example.com/salon.jpg', $event->image());
        $event->illustrate('  ');
        self::assertNull($event->image());
        $event->placeAt(null);
        self::assertNull($event->venue());

        foreach (['salon.jpg', '//tracker.example/p.gif', 'ftp://example.com/salon.jpg', 'javascript:alert(1)', '/'.str_repeat('a', 2048)] as $image) {
            try {
                $event->illustrate($image);
                self::fail($image);
            } catch (InvalidEvent $invalidEvent) {
                self::assertStringStartsWith('The image of an event', $invalidEvent->getMessage());
            }
        }

        $event->illustrate('/'.str_repeat('a', 2047));
        self::assertSame(2048, \strlen((string) $event->image()));
    }

    public function testTheDrafts(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $messages = static fn (object $draft): array => array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath().': '.$violation->getMessage(), iterator_to_array($validator->validate($draft), false));

        $venue = new VenueDraft();
        self::assertSame(['name: This value should not be blank.'], $messages($venue));
        $venue->name = 'Salle';
        $venue->listing = 'venue';
        $venue->latitude = 91;
        self::assertSame(['latitude: This value should be between -90 and 90.', 'listing: calendar.listing'], $messages($venue));
        $venue->latitude = 46.8;
        $venue->longitude = -181;
        $venue->listing = "venue:42\n";
        self::assertSame(['longitude: This value should be between -180 and 180.', 'listing: calendar.listing'], $messages($venue));

        $event = new EventDraft();
        $event->title = 'Salon';
        $event->start = new DateTimeImmutable();
        $event->end = new DateTimeImmutable();
        $event->timezone = 'UTC';
        $event->image = 'salon.jpg';
        self::assertSame(['image: calendar.image'], $messages($event));
        $event->image = '/affiches/salon.jpg';
        self::assertSame([], $messages($event));
        $event->image = 'https://example.com/salon.jpg';
        self::assertSame([], $messages($event));

        foreach (['//tracker.example/p.gif', '/\\tracker.example/p.gif', '/affiches\\salon.jpg'] as $image) {
            $event->image = $image;
            self::assertSame(['image: calendar.image'], $messages($event), $image);
        }
    }
}
