<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\View\Item;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Infrastructure\Calendar\ReservationItems;
use Phil\ReservationBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryReservations;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryResources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReservationItems::class)]
final class ReservationItemsTest extends TestCase
{
    public function testTheConfirmedReservationsAreItemsOfTheCalendar(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $resources = new InMemoryResources();
        $reservations = new InMemoryReservations();
        $room = BookableResource::create('Salle Verne', 2, 'America/Toronto');
        $resources->save($room);
        $service = new Reservations($resources, $reservations, new FrozenClock(new DateTimeImmutable('2026-11-01 12:00 UTC')));
        // The hold comes first: the confirmed reservations after it are items all the same.
        $service->hold($room->id, new DateTimeImmutable('2026-11-10 11:00', $toronto), new DateTimeImmutable('2026-11-10 12:00', $toronto));

        $booked = $service->book($room->id, new DateTimeImmutable('2026-11-10 09:00', $toronto), new DateTimeImmutable('2026-11-10 10:00', $toronto), 1, 'secret-holder');
        $later = $service->book($room->id, new DateTimeImmutable('2026-11-10 13:00', $toronto), new DateTimeImmutable('2026-11-10 14:00', $toronto));

        $paris = new DateTimeZone('Europe/Paris');
        $items = new ReservationItems($reservations, 40)->items(new DateTimeImmutable('2026-11-10 00:00', $paris), new DateTimeImmutable('2026-11-11 00:00', $paris));

        self::assertSame(['reservation:'.$booked->id, 'reservation:'.$later->id], array_map(static fn (Item $item): string => $item->id, $items));
        self::assertSame('Salle Verne', $items[0]->title);
        self::assertSame('2026-11-10T15:00:00+01:00', $items[0]->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T16:00:00+01:00', $items[0]->end->format(\DATE_ATOM));
        self::assertFalse($items[0]->allDay);
        self::assertSame('reservation', $items[0]->source);
        self::assertSame(['Salle Verne'], $items[0]->tags);
        self::assertNull($items[0]->description);
        self::assertSame([40], $reservations->limits);

        new ReservationItems($reservations)->items(new DateTimeImmutable('2026-11-10 00:00', $paris), new DateTimeImmutable('2026-11-11 00:00', $paris));
        self::assertSame([40, 1000], $reservations->limits);
    }
}
