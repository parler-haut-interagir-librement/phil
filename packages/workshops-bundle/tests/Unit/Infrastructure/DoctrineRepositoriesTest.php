<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\Reservation\Interval;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Phil\WorkshopsBundle\Domain\Status\SessionStatus;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineRegistrationRepository;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineSessionRepository;
use Phil\WorkshopsBundle\Infrastructure\Doctrine\DoctrineWorkshopRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The workshops, sessions and registrations on SQLite in memory, their tables created from the mapping, each read with
 * the entity manager cleared.
 */
#[CoversClass(DoctrineWorkshopRepository::class)]
#[CoversClass(DoctrineSessionRepository::class)]
#[CoversClass(DoctrineRegistrationRepository::class)]
#[CoversClass(Workshop::class)]
#[CoversClass(WorkshopSession::class)]
#[CoversClass(Registration::class)]
final class DoctrineRepositoriesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineWorkshopRepository $workshops;

    private DoctrineSessionRepository $sessions;

    private DoctrineRegistrationRepository $registrations;

    protected function setUp(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $this->entityManager = new EntityManager($connection, $configuration);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        $this->workshops = new DoctrineWorkshopRepository($this->entityManager);
        $this->sessions = new DoctrineSessionRepository($this->entityManager);
        $this->registrations = new DoctrineRegistrationRepository($this->entityManager);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    private function workshop(string $title = 'Peindre'): Workshop
    {
        $workshop = Workshop::create($title, $this->at('2026-11-01 08:00'));
        $this->workshops->save($workshop);

        return $workshop;
    }

    private function session(?Workshop $workshop = null, string $start = '2026-11-10 09:00', int $capacity = 4): WorkshopSession
    {
        $start = $this->at($start);
        $session = WorkshopSession::schedule($workshop ?? $this->workshop(), new Interval($start, $start->modify('+3 hours')), 'America/Toronto', $capacity);
        $this->sessions->save($session);

        return $session;
    }

    private function register(WorkshopSession $session, string $participant, int $seats = 1, string $at = '2026-11-01 12:00'): Registration
    {
        $registration = Registration::confirmed($session, $participant, $seats, $this->at($at));
        $this->registrations->save($registration);

        return $registration;
    }

    /**
     * @param list<Registration|WorkshopSession> $items
     *
     * @return list<string>
     */
    private function ids(array $items): array
    {
        return array_map(static fn (Registration|WorkshopSession $item): string => $item->id, $items);
    }

    public function testAWorkshopIsKeptAndRemovedWithItsSessionsAndRegistrations(): void
    {
        $workshop = $this->workshop('Peindre des figurines');
        $workshop->activate(false);

        $this->workshops->save($workshop);
        $this->entityManager->clear();

        $read = $this->workshops->get($workshop->id);
        self::assertNotNull($read);
        self::assertNotSame($workshop, $read);
        self::assertSame(['Peindre des figurines', false, '2026-11-01T13:00:00+00:00'], [$read->title(), $read->isActive(), $read->createdAt()->format(\DATE_ATOM)]);
        self::assertNull($this->workshops->get('nope'));

        $read->activate(true);
        $session = $this->session($read);
        $registration = $this->register($session, 'a');
        $ids = [$session->id, $registration->id];

        $this->workshops->remove($read);
        $this->entityManager->clear();

        self::assertNull($this->workshops->get($workshop->id));
        self::assertNull($this->sessions->get($ids[0]));
        self::assertNull($this->registrations->get($ids[1]));
    }

    public function testASessionIsKeptInUtcAndReadOnItsClock(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $session = $this->session(capacity: 12);
            $session->openRegistration($this->at('2026-11-02 08:00'), $this->at('2026-11-09 18:00'));
            $session->cancel();
            $this->sessions->save($session);

            self::assertSame(
                ['starts_at' => '2026-11-10 14:00:00', 'ends_at' => '2026-11-10 17:00:00', 'opens_at' => '2026-11-02 13:00:00', 'closes_at' => '2026-11-09 23:00:00', 'timezone' => 'America/Toronto', 'status' => 'cancelled'],
                $this->entityManager->getConnection()->fetchAssociative('SELECT starts_at, ends_at, opens_at, closes_at, timezone, status FROM '.WorkshopSession::TABLE),
            );

            $this->entityManager->clear();
            $read = $this->sessions->get($session->id);
            self::assertNotNull($read);
            self::assertSame('2026-11-10T09:00:00-05:00', $read->span()->start->format(\DATE_ATOM));
            self::assertSame('2026-11-10T12:00:00-05:00', $read->span()->end->format(\DATE_ATOM));
            self::assertSame('2026-11-02T08:00:00-05:00', $read->opensAt()?->format(\DATE_ATOM));
            self::assertSame('2026-11-09T18:00:00-05:00', $read->closesAt()?->format(\DATE_ATOM));
            self::assertSame([12, SessionStatus::Cancelled], [$read->capacity(), $read->status()]);
            self::assertSame($session->workshop()->id, $read->workshop()->id);
            self::assertNull($this->sessions->get('nope'));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testTheSessionsOfAWorkshopAreThoseNotOverByStart(): void
    {
        $workshop = $this->workshop();
        $later = $this->session($workshop, '2026-11-12 09:00');
        $sooner = $this->session($workshop, '2026-11-10 09:00');
        $past = $this->session($workshop, '2026-11-01 09:00');
        $this->session(start: '2026-11-11 09:00');
        $this->entityManager->clear();

        self::assertSame([$sooner->id, $later->id], $this->ids($this->sessions->ofWorkshop($workshop, $this->at('2026-11-05'), 10)));
        self::assertSame([$past->id, $sooner->id], $this->ids($this->sessions->ofWorkshop($workshop, $this->at('2026-11-01 11:59'), 2)));
        // A session that ends at the moment is over.
        self::assertSame([$later->id], $this->ids($this->sessions->ofWorkshop($workshop, $this->at('2026-11-10 12:00'), 10)));
        self::assertSame([$sooner->id, $later->id], $this->ids($this->sessions->ofWorkshop($workshop, new DateTimeImmutable('2026-11-10 16:59', new DateTimeZone('UTC')), 10)));
    }

    public function testARegistrationIsKeptInUtcAndReadOnTheSessionsClock(): void
    {
        $session = $this->session();
        $registration = $this->register($session, 'account-1', 3);
        $registration->cancel($this->at('2026-11-03 12:00'));

        $this->registrations->save($registration);

        self::assertSame(
            ['participant' => 'account-1', 'seats' => 3, 'status' => 'cancelled', 'created_at' => '2026-11-01 17:00:00', 'cancelled_at' => '2026-11-03 17:00:00'],
            $this->entityManager->getConnection()->fetchAssociative('SELECT participant, seats, status, created_at, cancelled_at FROM '.Registration::TABLE),
        );

        $this->entityManager->clear();
        $read = $this->registrations->get($registration->id);
        self::assertNotNull($read);
        self::assertSame(['account-1', 3, RegistrationStatus::Cancelled], [$read->participant(), $read->seats(), $read->status()]);
        self::assertSame('2026-11-01T12:00:00-05:00', $read->createdAt()->format(\DATE_ATOM));
        self::assertSame('2026-11-03T12:00:00-05:00', $read->cancelledAt()?->format(\DATE_ATOM));
        self::assertSame($session->id, $read->session()->id);
        self::assertNull($this->registrations->get('nope'));
    }

    public function testTheLiveRegistrationOfAParticipant(): void
    {
        $session = $this->session();
        $other = $this->session();
        $cancelled = $this->register($session, 'a');
        $cancelled->cancel($this->at('2026-11-02'));

        $this->registrations->save($cancelled);
        $this->register($other, 'a');
        $registered = $this->register($session, 'b');
        $this->entityManager->clear();

        self::assertNull($this->registrations->live($session, 'a'));
        self::assertSame($registered->id, $this->registrations->live($session, 'b')?->id);
        self::assertNull($this->registrations->live($session, 'c'));

        $confirmed = $this->register($this->entityManager->find(WorkshopSession::class, $session->id) ?? throw new RuntimeException(), 'a');
        self::assertSame($confirmed->id, $this->registrations->live($session, 'a')?->id);
    }

    public function testTwoLiveRegistrationsOfOneParticipantStillReadAsOne(): void
    {
        $session = $this->session();
        $this->entityManager->getConnection()->executeStatement('INSERT INTO '.Registration::TABLE." (id, session, participant, seats, status, created_at, version) VALUES ('x', ?, 'a', 1, 'confirmed', '2026-11-01 00:00:00', 1), ('y', ?, 'a', 1, 'confirmed', '2026-11-01 00:00:01', 1)", [$session->id, $session->id]);

        self::assertInstanceOf(Registration::class, $this->registrations->live($session, 'a'));
    }

    public function testTheMappingFixesTheColumnsOfTheIdentifiersAndLinks(): void
    {
        foreach ([Workshop::class, WorkshopSession::class, Registration::class] as $class) {
            $mapping = $this->entityManager->getClassMetadata($class)->getFieldMapping('id');

            self::assertSame(36, $mapping->length);
            self::assertTrue($mapping->options['fixed'] ?? false);
        }

        self::assertSame(64, $this->entityManager->getClassMetadata(WorkshopSession::class)->getFieldMapping('timezone')->length);
        self::assertSame(16, $this->entityManager->getClassMetadata(Registration::class)->getFieldMapping('status')->length);

        foreach ([[WorkshopSession::class, 'workshop'], [Registration::class, 'session']] as [$class, $association]) {
            $mapping = $this->entityManager->getClassMetadata($class)->getAssociationMapping($association);
            self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);
            $join = $mapping->joinColumns[0];

            self::assertFalse($join->nullable);
            self::assertSame('CASCADE', $join->onDelete);
        }
    }

    public function testTheSeatsOfAStatusAreAddedUp(): void
    {
        $session = $this->session(capacity: 10);
        $other = $this->session();

        self::assertSame(0, $this->registrations->seats($session, RegistrationStatus::Confirmed));

        $this->register($session, 'a', 3);
        $this->register($session, 'b', 2);
        $this->register($other, 'a', 1);
        $cancelled = $this->register($session, 'd', 1);
        $cancelled->cancel($this->at('2026-11-02'));

        $this->registrations->save($cancelled);

        self::assertSame(5, $this->registrations->seats($session, RegistrationStatus::Confirmed));
        self::assertSame(1, $this->registrations->seats($session, RegistrationStatus::Cancelled));
    }

    public function testASessionsRegistrationsAreReadInTheOrderTheyWereMade(): void
    {
        $session = $this->session();
        $other = $this->session();
        $second = $this->register($session, 'b', at: '2026-11-01 12:00:01');
        $first = $this->register($session, 'a', at: '2026-11-01 12:00');
        $third = $this->register($session, 'c', at: '2026-11-01 12:00:01');
        $this->register($other, 'e', at: '2026-11-01 10:00');
        $third->cancel($this->at('2026-11-02'));
        $this->registrations->save($third);
        $this->entityManager->clear();

        // Two made in the same second are read by identifier: the one made first has the lower one.
        self::assertSame([$first->id, $second->id, $third->id], $this->ids($this->registrations->ofSession($session, 10)));
        self::assertSame([$first->id, $second->id], $this->ids($this->registrations->ofSession($session, 2)));
        self::assertSame([$first->id, $second->id], $this->ids($this->registrations->ofSession($session, 10, RegistrationStatus::Confirmed)));
        self::assertSame([$third->id], $this->ids($this->registrations->ofSession($session, 10, RegistrationStatus::Cancelled)));
    }

    public function testTheLockCommitsTheWorkAndReadsTheSessionAgain(): void
    {
        $session = $this->session();
        $this->entityManager->getConnection()->executeStatement('UPDATE '.WorkshopSession::TABLE.' SET capacity = 9 WHERE id = ?', [$session->id]);

        $result = $this->registrations->exclusively($session, function () use ($session): string {
            self::assertTrue($this->entityManager->getConnection()->isTransactionActive());
            $this->register($session, 'a');

            return 'done '.$session->capacity();
        });

        self::assertSame('done 9', $result);
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertSame(1, $this->entityManager->getConnection()->fetchOne('SELECT claims FROM '.WorkshopSession::TABLE));
        self::assertSame(1, $this->registrations->seats($session, RegistrationStatus::Confirmed));
    }

    public function testTheLockRollsBackARefusal(): void
    {
        $session = $this->session();

        try {
            $this->registrations->exclusively($session, function () use ($session): void {
                $this->entityManager->getConnection()->executeStatement('INSERT INTO '.Registration::TABLE." (id, session, participant, seats, status, created_at, version) VALUES ('x', ?, 'a', 1, 'confirmed', '2026-11-01 00:00:00', 1)", [$session->id]);

                // The work is refused once it has written: what it wrote is rolled back with it.
                if ($session->capacity() > 0) {
                    throw new RuntimeException('refused');
                }
            });
            self::fail('The refusal goes through.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('refused', $runtimeException->getMessage());
        }

        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertTrue($this->entityManager->isOpen());
        self::assertSame(0, $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.Registration::TABLE));
        self::assertSame(0, $this->entityManager->getConnection()->fetchOne('SELECT claims FROM '.WorkshopSession::TABLE));
    }
}
