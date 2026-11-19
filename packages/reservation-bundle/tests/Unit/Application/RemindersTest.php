<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Application\Reminders;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryReservations;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryResources;
use Phil\ReservationBundle\Tests\Unit\Fixture\RecordingSender;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reminders::class)]
final class RemindersTest extends TestCase
{
    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    public function testTheRemindersDueAreSentOnce(): void
    {
        $resources = new InMemoryResources();
        $reservations = new InMemoryReservations();
        $sender = new RecordingSender();
        $clock = new FrozenClock($this->at('2026-11-09 19:00'));
        $room = BookableResource::create('Salle', 1, 'America/Toronto');
        $room->remindBefore(1440, 60);

        $quiet = BookableResource::create('Atelier', 1, 'America/Toronto');
        $resources->save($room);
        $resources->save($quiet);

        $soon = Reservation::confirmed($room, new Interval($this->at('2026-11-10 19:00'), $this->at('2026-11-10 21:00')), 1, 'a', $this->at('2026-11-01'));
        $later = Reservation::confirmed($room, new Interval($this->at('2026-11-12 19:00'), $this->at('2026-11-12 21:00')), 1, 'b', $this->at('2026-11-01'));
        $silent = Reservation::confirmed($quiet, new Interval($this->at('2026-11-10 09:00'), $this->at('2026-11-10 10:00')), 1, 'c', $this->at('2026-11-01'));
        $held = Reservation::hold($room, new Interval($this->at('2026-11-10 09:00'), $this->at('2026-11-10 10:00')), 1, 'd', $this->at('2026-11-09 18:59'));

        foreach ([$soon, $later, $silent, $held] as $reservation) {
            $reservations->save($reservation);
        }

        $reminders = new Reminders($resources, $reservations, $sender, $clock);
        $saves = $reservations->saves;

        self::assertSame(1, $reminders->send(50));
        self::assertSame($saves + 1, $reservations->saves);
        self::assertSame([[$soon->id, 1440]], $sender->sent);
        self::assertSame(1440, $soon->lastReminder());
        self::assertSame([50], $reservations->limits);
        self::assertSame('2026-11-10 19:00', $reservations->asked[0]->end->format('Y-m-d H:i'));
        self::assertSame(0, $reminders->send());

        $clock->now = $this->at('2026-11-10 18:00');
        self::assertSame(1, $reminders->send());
        self::assertSame([[$soon->id, 1440], [$soon->id, 60]], $sender->sent);
        self::assertSame([50, 500, 500], $reservations->limits);
    }

    public function testNothingIsReadWhenNoResourceReminds(): void
    {
        $resources = new InMemoryResources();
        $resources->save(BookableResource::create('Salle'));

        $reservations = new InMemoryReservations();

        self::assertSame(0, new Reminders($resources, $reservations, new RecordingSender(), new FrozenClock())->send());
        self::assertSame([], $reservations->limits);
        self::assertSame(0, new Reminders(new InMemoryResources(), $reservations, new RecordingSender(), new FrozenClock())->send());
    }

    public function testTheFurthestReminderOfAllResourcesBoundsTheRead(): void
    {
        $resources = new InMemoryResources();
        $near = BookableResource::create('A');
        $near->remindBefore(30);

        $far = BookableResource::create('B');
        $far->remindBefore(10080, 15);

        $resources->save($near);
        $resources->save($far);

        $reservations = new InMemoryReservations();
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-01 12:00 UTC'));

        new Reminders($resources, $reservations, new RecordingSender(), $clock)->send();

        self::assertSame('2026-11-08 12:00', $reservations->asked[0]->end->format('Y-m-d H:i'));
    }
}
