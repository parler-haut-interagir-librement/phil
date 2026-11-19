<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Reservation\Interval;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Model\Reservation;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineReservationRepository;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineResourceRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The resources and reservations on SQLite in memory, their tables created from the mapping, each read with the entity
 * manager cleared.
 */
#[CoversClass(DoctrineResourceRepository::class)]
#[CoversClass(DoctrineReservationRepository::class)]
#[CoversClass(BookableResource::class)]
#[CoversClass(Reservation::class)]
final class DoctrineRepositoriesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineResourceRepository $resources;

    private DoctrineReservationRepository $reservations;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $this->entityManager = new EntityManager($connection, $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->resources = new DoctrineResourceRepository($this->entityManager);
        $this->reservations = new DoctrineReservationRepository($this->entityManager);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function span(string $start, string $end): Interval
    {
        return new Interval($this->at($start), $this->at($end));
    }

    private function room(string $name = 'Salle'): BookableResource
    {
        $room = BookableResource::create($name, 4, 'America/Toronto');
        $this->resources->save($room);

        return $room;
    }

    private function reload(BookableResource $resource): BookableResource
    {
        $this->entityManager->clear();
        $reloaded = $this->resources->get($resource->id);
        self::assertNotNull($reloaded);

        return $reloaded;
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

    public function testMomentsAreStoredInUtcAndReadInUtcWhateverTheProcessZone(): void
    {
        $room = $this->room();
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $hold = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $this->at('2026-11-01 08:00'));
            $this->reservations->save($hold);

            self::assertSame(
                ['starts_at' => '2026-11-10 14:00:00', 'ends_at' => '2026-11-10 15:00:00', 'created_at' => '2026-11-01 13:00:00', 'hold_expires_at' => '2026-11-01 13:15:00'],
                $this->entityManager->getConnection()->fetchAssociative('SELECT starts_at, ends_at, created_at, hold_expires_at FROM '.Reservation::TABLE),
            );

            $this->entityManager->clear();
            $read = $this->reservations->get($hold->id);
            self::assertNotNull($read);
            self::assertSame('2026-11-10T09:00:00-05:00', $read->interval()->start->format(\DATE_ATOM));
            self::assertSame('2026-11-01T08:15:00-05:00', $read->holdExpiresAt()?->format(\DATE_ATOM));
            self::assertSame('2026-11-01T08:00:00-05:00', $read->createdAt()->format(\DATE_ATOM));
            self::assertTrue($read->holdsUnits(new DateTimeImmutable('2026-11-01 13:14:59', new DateTimeZone('UTC'))));
            self::assertFalse($read->holdsUnits(new DateTimeImmutable('2026-11-01 13:15:00', new DateTimeZone('UTC'))));

            // The queries compare in UTC too, whatever the zone of the moments they are given.
            $paris = new DateTimeZone('Europe/Paris');
            self::assertSame([$hold->id], $this->ids($this->reservations->holding($room, new Interval(new DateTimeImmutable('2026-11-10 15:59', $paris), new DateTimeImmutable('2026-11-10 17:00', $paris)), $this->at('2026-11-01 08:14'))));
            self::assertSame([], $this->ids($this->reservations->holding($room, new Interval(new DateTimeImmutable('2026-11-10 16:00', $paris), new DateTimeImmutable('2026-11-10 17:00', $paris)), $this->at('2026-11-01 08:14'))));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testAResourceIsKeptWithEverythingItDefines(): void
    {
        $room = BookableResource::create('Salle Verne', 12, 'America/Toronto');
        $room->buffer(5, 15);
        $room->restrict(60, 43200, 30, 240, 30);
        $room->openOn([1 => [[540, 1020]], 6 => [[600, 960]]], ['2026-12-25']);
        $room->remindBefore(1440, 60);
        $room->holdFor(20);
        $room->activate(false);

        $this->resources->save($room);

        $read = $this->reload($room);
        self::assertNotSame($room, $read);
        self::assertSame(['Salle Verne', 12, 'America/Toronto', 5, 15], [$read->name(), $read->capacity(), $read->timezone()->getName(), $read->bufferBefore(), $read->bufferAfter()]);
        self::assertSame([60, 43200, 30, 240, 30], [$read->minimumNotice(), $read->maximumAdvance(), $read->minimumDuration(), $read->maximumDuration(), $read->slotStep()]);
        self::assertSame([1 => [[540, 1020]], 6 => [[600, 960]]], $read->hours());
        self::assertSame(['2026-12-25'], $read->closedDays());
        self::assertSame([1440, 60], $read->reminders());
        self::assertSame(20, $read->holdMinutes());
        self::assertFalse($read->isActive());
        self::assertNull($this->resources->get('nope'));
    }

    public function testResourcesAreListedByName(): void
    {
        $b = $this->room('B');
        $a = $this->room('A');

        self::assertSame([$a->id, $b->id], array_map(static fn (BookableResource $resource): string => $resource->id, $this->resources->all()));

        $this->resources->remove($a);
        self::assertSame([$b->id], array_map(static fn (BookableResource $resource): string => $resource->id, $this->resources->all()));
    }

    public function testRemovingAResourceRemovesItsReservations(): void
    {
        $room = $this->room();
        $reservation = Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $this->at('2026-11-01'));
        $this->reservations->save($reservation);
        $id = $reservation->id;
        $this->resources->remove($room);
        $this->entityManager->clear();

        self::assertNull($this->reservations->get($id));
    }

    public function testAReservationIsKeptInUtcAndReadOnTheResourcesClock(): void
    {
        $room = $this->room();
        $hold = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:30'), 3, 'account-1', $this->at('2026-11-01 12:00'));
        $hold->remind(60);

        $this->reservations->save($hold);
        $this->entityManager->clear();

        $read = $this->reservations->get($hold->id);
        self::assertNotNull($read);
        self::assertSame('2026-11-10T09:00:00-05:00', $read->interval()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-10T10:30:00-05:00', $read->interval()->end->format(\DATE_ATOM));
        self::assertSame([3, 'account-1', ReservationStatus::Held, 60], [$read->quantity(), $read->holder(), $read->status(), $read->lastReminder()]);
        self::assertSame('2026-11-01T12:15:00-05:00', $read->holdExpiresAt()?->format(\DATE_ATOM));
        self::assertSame($room->id, $read->resource()->id);
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT starts_at, status FROM phil_reservation');
        self::assertSame(['starts_at' => '2026-11-10 14:00:00', 'status' => 'held'], $row);
        self::assertNull($this->reservations->get('nope'));
    }

    public function testTheReservationsHoldingUnitsAreThoseConfirmedOrHeldAndNotExpired(): void
    {
        $room = $this->room();
        $other = $this->room('Autre');
        $now = $this->at('2026-11-01 12:00');
        $confirmed = Reservation::confirmed($room, $this->span('2026-11-10 10:00', '2026-11-10 11:00'), 1, null, $now);
        $held = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now);
        $stale = Reservation::hold($room, $this->span('2026-11-10 09:30', '2026-11-10 10:30'), 1, null, $now->modify('-15 minutes'));
        $cancelled = Reservation::confirmed($room, $this->span('2026-11-10 09:30', '2026-11-10 10:30'), 1, null, $now);
        $cancelled->cancel();

        $elsewhere = Reservation::confirmed($other, $this->span('2026-11-10 09:30', '2026-11-10 10:30'), 1, null, $now);
        $before = Reservation::confirmed($room, $this->span('2026-11-10 08:00', '2026-11-10 09:00'), 1, null, $now);
        $after = Reservation::confirmed($room, $this->span('2026-11-10 11:00', '2026-11-10 12:00'), 1, null, $now);

        foreach ([$confirmed, $held, $stale, $cancelled, $elsewhere, $before, $after] as $reservation) {
            $this->reservations->save($reservation);
        }

        $this->entityManager->clear();
        $read = $this->reload($room);
        self::assertSame([$held->id, $confirmed->id], $this->ids($this->reservations->holding($read, $this->span('2026-11-10 09:00', '2026-11-10 11:00'), $now)));
        self::assertSame([$confirmed->id], $this->ids($this->reservations->holding($read, $this->span('2026-11-10 09:00', '2026-11-10 11:00'), $now->modify('+15 minutes'))));
        self::assertSame([$held->id], $this->ids($this->reservations->holding($read, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), $now->modify('+14 minutes +59 seconds'))));
    }

    public function testTheReservationsOfAPeriodAreThoseHeldOrConfirmed(): void
    {
        $room = $this->room();
        $other = $this->room('Autre');
        $now = $this->at('2026-11-01 12:00');
        $first = Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now);
        $second = Reservation::hold($other, $this->span('2026-11-10 10:00', '2026-11-10 11:00'), 1, null, $now);
        $third = Reservation::confirmed($room, $this->span('2026-11-10 11:00', '2026-11-10 12:00'), 1, null, $now);
        $expired = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now);
        $expired->expire($now->modify('+1 day'));

        $outside = Reservation::confirmed($room, $this->span('2026-11-11 09:00', '2026-11-11 10:00'), 1, null, $now);

        foreach ([$third, $second, $first, $expired, $outside] as $reservation) {
            $this->reservations->save($reservation);
        }

        $from = $this->at('2026-11-10 00:00');
        $until = $this->at('2026-11-11 00:00');
        self::assertSame([$first->id, $second->id, $third->id], $this->ids($this->reservations->between($from, $until, 10)));
        self::assertSame([$first->id, $second->id], $this->ids($this->reservations->between($from, $until, 2)));
        self::assertSame([$first->id, $third->id], $this->ids($this->reservations->between($from, $until, 10, $room)));
        self::assertSame([$second->id], $this->ids($this->reservations->between($this->at('2026-11-10 10:00'), $this->at('2026-11-10 11:00'), 10)));
    }

    public function testTheHoldsWhoseMomentCameAreListedTheOldestFirst(): void
    {
        $room = $this->room();
        $now = $this->at('2026-11-01 12:00');
        $older = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now->modify('-30 minutes'));
        $old = Reservation::hold($room, $this->span('2026-11-10 08:00', '2026-11-10 09:00'), 1, null, $now->modify('-15 minutes'));
        $fresh = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now->modify('-14 minutes'));
        $marked = Reservation::hold($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now->modify('-1 day'));
        $marked->expire($now);

        foreach ([$old, $fresh, $older, $marked] as $reservation) {
            $this->reservations->save($reservation);
        }

        self::assertSame([$older->id, $old->id], $this->ids($this->reservations->expiredHolds($now, 10)));
        self::assertSame([$older->id], $this->ids($this->reservations->expiredHolds($now, 1)));
    }

    public function testTheUpcomingReservationsAreThoseConfirmedThatStartSoon(): void
    {
        $room = $this->room();
        $now = $this->at('2026-11-10 08:00');
        $started = Reservation::confirmed($room, $this->span('2026-11-10 08:00', '2026-11-10 09:00'), 1, null, $now);
        $soon = Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $now);
        $edge = Reservation::confirmed($room, $this->span('2026-11-11 08:00', '2026-11-11 09:00'), 1, null, $now);
        $far = Reservation::confirmed($room, $this->span('2026-11-11 08:00:01', '2026-11-11 09:00'), 1, null, $now);
        $held = Reservation::hold($room, $this->span('2026-11-10 09:30', '2026-11-10 10:00'), 1, null, $now);

        foreach ([$edge, $far, $held, $started, $soon] as $reservation) {
            $this->reservations->save($reservation);
        }

        self::assertSame([$soon->id, $edge->id], $this->ids($this->reservations->upcoming($now, $now->modify('+1 day'), 10)));
        self::assertSame([$soon->id], $this->ids($this->reservations->upcoming($now, $now->modify('+1 day'), 1)));
    }

    public function testWorkUnderTheLockIsCommittedOrRolledBack(): void
    {
        $room = $this->room();
        $result = $this->reservations->exclusively($room, function () use ($room): string {
            $reservation = Reservation::confirmed($room, $this->span('2026-11-10 09:00', '2026-11-10 10:00'), 1, null, $this->at('2026-11-01'));
            $this->reservations->save($reservation);

            return $reservation->id;
        });

        self::assertNotNull($this->reservations->get($result));
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertSame(1, $this->reload($room)->claims());

        try {
            $this->reservations->exclusively($room, function () use ($room): never {
                $this->entityManager->getConnection()->executeStatement('UPDATE '.BookableResource::TABLE." SET name = 'Changée' WHERE id = ?", [$room->id]);

                throw new RuntimeException('refused');
            });
        } catch (RuntimeException $runtimeException) {
            self::assertSame('refused', $runtimeException->getMessage());
        }

        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertTrue($this->entityManager->isOpen());
        $read = $this->reload($room);
        self::assertSame(['Salle', 1], [$read->name(), $read->claims()]);
    }
}
