<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\OptimisticLockException;
use Override;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

/**
 * The bundle's mapping, queries, lock and constraints on a real server, read from the environment: the tables created
 * from the mapping, what is kept read back, each query's filters, the version of a reservation, the cascade of a
 * resource removed, the buffers of neighbours added, and requests in separate processes racing for the last unit.
 */
abstract class ServerTestCase extends TestCase
{
    private const int RACERS = 6;

    private Server $server;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->server = Server::connect($this->url());
        $this->server->recreate();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->server)) {
            $this->server->entityManager->getConnection()->close();
        }
    }

    public function testAResourceAndItsReservationsAreKeptAsTheyWereGiven(): void
    {
        $room = BookableResource::create('Salle Brébeuf', 3, 'America/Toronto');
        $room->buffer(10, 5);
        $room->openOn([1 => [[540, 1020]], 2 => [[540, 1020]], 6 => [[600, 720], [780, 960]]], ['2026-12-25']);
        $room->restrict(60, 129_600, 30, 240, 15);
        $room->remindBefore(1440, 60);
        $this->server->resources()->save($room);
        $held = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:30'), 2, 'member-7', $this->at('2026-11-01 08:00'));
        $this->server->repository()->save($held);
        $this->server->entityManager->clear();

        $read = $this->server->resources()->get($room->id);
        self::assertInstanceOf(BookableResource::class, $read);
        self::assertSame(['Salle Brébeuf', 3, 'America/Toronto'], [$read->name(), $read->capacity(), $read->timezone()->getName()]);
        self::assertEquals($room->bookable(), $read->bookable());
        self::assertSame([$room->hours(), ['2026-12-25'], [1440, 60], 10, 5, 60, 129_600, 30, 240, 15], [$read->hours(), $read->closedDays(), $read->reminders(), $read->bufferBefore(), $read->bufferAfter(), $read->minimumNotice(), $read->maximumAdvance(), $read->minimumDuration(), $read->maximumDuration(), $read->slotStep()]);

        $reservation = $this->server->repository()->get($held->id);
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame([ReservationStatus::Held, 2, 'member-7'], [$reservation->status(), $reservation->quantity(), $reservation->holder()]);
        self::assertSame('2026-11-10T09:00:00-05:00', $reservation->interval()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10 14:00:00', $this->server->entityManager->getConnection()->fetchOne('SELECT starts_at FROM '.Reservation::TABLE));
    }

    public function testEachQueryKeepsItsReservationsInItsOrder(): void
    {
        $room = $this->room(4);
        $now = $this->at('2026-11-05 12:00');
        $confirmed = $this->keep(Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now));
        $held = $this->keep(Reservation::hold($room, $this->span('2026-11-10 08:00', '2026-11-10 09:30'), 1, null, $now));
        $stale = $this->keep(Reservation::hold($room, $this->span('2026-11-10 09:30', '2026-11-10 11:00'), 1, null, $now->modify('-1 hour')));
        $cancelled = Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now);
        $cancelled->cancel();
        $this->keep($cancelled);
        $tomorrow = $this->keep(Reservation::confirmed($room, $this->span('2026-11-06 09:00', '2026-11-06 10:00'), 1, null, $now));
        $repository = $this->server->repository();
        $this->server->entityManager->clear();
        $room = $this->server->resources()->get($room->id);
        self::assertInstanceOf(BookableResource::class, $room);

        self::assertSame([$held->id, $confirmed->id], $this->ids($repository->holding($room, $this->span('2026-11-10 09:00', '2026-11-10 12:00'), $now)));
        self::assertSame([$confirmed->id], $this->ids($repository->holding($room, $this->span('2026-11-10 09:30', '2026-11-10 09:45'), $now->modify('+1 hour'))), 'a hold whose moment came keeps nothing');
        self::assertSame([], $this->ids($repository->holding($room, $this->span('2026-11-10 10:00', '2026-11-10 10:30'), $now->modify('+1 hour'))), 'a span that only touches meets nothing');
        self::assertSame([$tomorrow->id, $held->id, $confirmed->id, $stale->id], $this->ids($repository->between($this->at('2026-11-01'), $this->at('2026-11-30'), 10)));
        self::assertSame([$tomorrow->id], $this->ids($repository->between($this->at('2026-11-01'), $this->at('2026-11-30'), 1, $room)));
        self::assertSame([$stale->id], $this->ids($repository->expiredHolds($now, 10)));
        self::assertSame([$tomorrow->id], $this->ids($repository->upcoming($now, $now->modify('+1 day'), 10)));
    }

    public function testAReservationChangedMeanwhileIsNotOverwritten(): void
    {
        $room = $this->room(1);
        $reservation = $this->keep(Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $this->at('2026-11-05 12:00')));
        $other = Server::connect($this->url());
        $copy = $other->repository()->get($reservation->id);
        self::assertInstanceOf(Reservation::class, $copy);

        $reservation->confirm();
        $this->server->repository()->save($reservation);
        $copy->cancel();

        try {
            $other->repository()->save($copy);
            self::fail('The second change overwrote the first.');
        } catch (OptimisticLockException) {
            $this->server->entityManager->clear();
            self::assertSame(ReservationStatus::Confirmed, $this->server->repository()->get($reservation->id)?->status());
        } finally {
            $other->entityManager->getConnection()->close();
        }
    }

    public function testRemovingAResourceRemovesItsReservationsInTheDatabase(): void
    {
        $room = $this->room(1);
        $this->keep(Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $this->at('2026-11-05')));
        $this->server->entityManager->getConnection()->executeStatement('DELETE FROM '.BookableResource::TABLE.' WHERE id = ?', [$room->id]);

        self::assertSame(0, $this->number('SELECT COUNT(*) FROM '.Reservation::TABLE));
    }

    public function testTheBuffersOfNeighboursAdd(): void
    {
        $room = BookableResource::create('Salle', 1, 'America/Toronto');
        $room->buffer(15, 15);
        $this->server->resources()->save($room);
        $reservations = $this->server->reservations(new MockClock('2026-11-01 12:00 America/Toronto'));
        $reservations->book($room->id, $this->at('2026-11-10 10:00'), $this->at('2026-11-10 11:00'));

        try {
            $reservations->book($room->id, $this->at('2026-11-10 11:15'), $this->at('2026-11-10 12:00'));
            self::fail('Fifteen minutes between two bookings of fifteen minutes of buffer each were enough.');
        } catch (ReservationRefused) {
        }

        self::assertSame('2026-11-10 11:30', $reservations->book($room->id, $this->at('2026-11-10 11:30'), $this->at('2026-11-10 12:00'))->interval()->start->format('Y-m-d H:i'));
    }

    public function testRequestsRacingForTheLastUnitBookItOnce(): void
    {
        $room = $this->room(1);
        $start = '2030-06-10T19:00:00-04:00';
        $end = '2030-06-10T22:00:00-04:00';
        $moment = \sprintf('%.6F', microtime(true) + 1.5);
        $processes = [];

        for ($racer = 0; $racer < self::RACERS; ++$racer) {
            $process = new Process([\PHP_BINARY, __DIR__.'/book.php', $this->url(), $room->id, $start, $end, $moment]);
            $process->start();
            $processes[] = $process;
        }

        $answers = [];

        foreach ($processes as $process) {
            $process->wait();
            $answers[] = trim($process->getOutput()).trim($process->getErrorOutput());
        }

        sort($answers);
        self::assertSame(['booked', ...array_fill(0, self::RACERS - 1, 'refused')], $answers);

        self::assertSame(1, $this->number('SELECT COUNT(*) FROM '.Reservation::TABLE.' WHERE status = ?', [ReservationStatus::Confirmed->value]));
        self::assertSame(1, $this->number('SELECT claims FROM '.BookableResource::TABLE.' WHERE id = ?', [$room->id]), 'a refused request rolls its claim back with the rest');
    }

    private function url(): string
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $url;
    }

    /**
     * The number a query answers, which some drivers give as a string.
     *
     * @param list<string> $parameters
     */
    private function number(string $sql, array $parameters = []): int
    {
        $number = $this->server->entityManager->getConnection()->fetchOne($sql, $parameters);
        self::assertIsNumeric($number);

        return (int) $number;
    }

    private function room(int $capacity): BookableResource
    {
        $room = BookableResource::create('Salle', $capacity, 'America/Toronto');
        $this->server->resources()->save($room);

        return $room;
    }

    private function keep(Reservation $reservation): Reservation
    {
        $this->server->repository()->save($reservation);

        return $reservation;
    }

    /**
     * @param list<Reservation> $reservations
     *
     * @return list<string>
     */
    private function ids(array $reservations): array
    {
        return array_map(static fn (Reservation $reservation): string => $reservation->id, $reservations);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function span(string $start, string $end): Interval
    {
        return new Interval($this->at($start), $this->at($end));
    }
}
