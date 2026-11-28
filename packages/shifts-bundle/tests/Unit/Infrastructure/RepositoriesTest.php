<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Phil\Shifts\Exception\InvalidDocument;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Swap\SwapOffer;
use Phil\Shifts\Swap\SwapStatus;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use Phil\ShiftsBundle\Domain\Model\StoredRoster;
use Phil\ShiftsBundle\Domain\Model\StoredSwap;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalAbsenceRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalGuardianRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalRosterRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalSwapRepository;
use Phil\ShiftsBundle\Infrastructure\Doctrine\DbalTransaction;
use Phil\ShiftsBundle\Infrastructure\Doctrine\Json;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsSchemaListener;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsTables;
use Phil\ShiftsBundle\Tests\Fixtures\ForeignColumnConnection;
use Phil\ShiftsBundle\Tests\Fixtures\RacingConnection;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbalRosterRepository::class)]
#[CoversClass(DbalGuardianRepository::class)]
#[CoversClass(DbalAbsenceRepository::class)]
#[CoversClass(DbalSwapRepository::class)]
#[CoversClass(DbalTransaction::class)]
#[CoversClass(ShiftsTables::class)]
#[CoversClass(ShiftsSchemaListener::class)]
#[CoversClass(Json::class)]
#[CoversClass(StoredRoster::class)]
#[CoversClass(StoredSwap::class)]
final class RepositoriesTest extends TestCase
{
    public function testARosterIsAddedOnceAndWrittenOverItsRevision(): void
    {
        $stage = new Stage();
        $repository = $stage->rosterRepository;
        $roster = Roster::open(Stage::desk());

        self::assertTrue($repository->add($roster));
        self::assertFalse($repository->add($roster));
        self::assertSame(1, $repository->find('desk-am')?->revision);
        self::assertEquals($roster->shift, $repository->find('desk-am')?->roster->shift);
        self::assertNull($repository->find('missing'));
        self::assertSame('2026-11-28 09:00:00', $stage->connection->fetchOne('SELECT starts_at FROM phil_shifts_shifts'));
        self::assertSame('2026-11-28 12:00:00', $stage->connection->fetchOne('SELECT ends_at FROM phil_shifts_shifts'));
        self::assertSame('2026-11-27 10:00:00', $stage->connection->fetchOne('SELECT updated_at FROM phil_shifts_shifts'));

        $signed = $roster->signUp('ada', [], Stage::at('08:00:00'))->signUp('bob', [], Stage::at('08:00:00'))->signUp('cy', [], Stage::at('08:00:00'));
        self::assertTrue($repository->update($signed, 1));
        self::assertFalse($repository->update($signed, 1));
        $stored = $repository->find('desk-am');
        self::assertNotNull($stored);
        self::assertSame(2, $stored->revision);
        self::assertSame(PlaceStatus::Reserve, $stored->roster->statusOf('cy'));
        self::assertSame([['ada', 'confirmed'], ['bob', 'confirmed'], ['cy', 'reserve']], $stage->connection->fetchAllNumeric('SELECT volunteer, status FROM phil_shifts_places ORDER BY volunteer'));

        self::assertTrue($repository->update($signed->withdraw('ada', Stage::at('08:00:00')), 2));
        self::assertSame([['bob', 'confirmed'], ['cy', 'confirmed']], $stage->connection->fetchAllNumeric('SELECT volunteer, status FROM phil_shifts_places ORDER BY volunteer'));
    }

    public function testTheUpcomingShiftsAndThoseOfAVolunteer(): void
    {
        $stage = new Stage();
        $repository = $stage->rosterRepository;
        $repository->add(Roster::open(Stage::desk('pm', '13:00:00', '14:00:00'))->signUp('ada', [], Stage::at('08:00:00')));
        $repository->add(Roster::open(Stage::desk())->signUp('ada', [], Stage::at('08:00:00')));
        $repository->add(Roster::open(Stage::desk('early', '07:00:00', '08:00:00')));
        $repository->add(Roster::open(Stage::cloakroom())->expressInterest('ada', [], Stage::at('07:00:00')));

        $ids = static fn (array $rosters): array => array_map(static fn (Roster $roster): string => $roster->shift->id, $rosters);
        self::assertSame(['early', 'cloakroom', 'desk-am', 'pm'], $ids($repository->upcoming(Stage::at('00:00:00'))));
        self::assertSame(['cloakroom', 'desk-am', 'pm'], $ids($repository->upcoming(Stage::at('08:00:00'))));
        self::assertSame(['early', 'cloakroom'], $ids($repository->upcoming(Stage::at('00:00:00'), 2)));
        self::assertSame(['early'], $ids($repository->upcoming(Stage::at('00:00:00'), -3)));
        self::assertSame(['early', 'cloakroom', 'desk-am', 'pm'], $ids($repository->upcoming(Stage::at('00:00:00'), 9000)));
        self::assertSame([], $repository->upcoming(Stage::at('20:00:00')));
        self::assertSame(['cloakroom', 'desk-am', 'pm'], $ids($repository->ofVolunteer('ada')));
        self::assertSame([], $repository->ofVolunteer('bob'));

        $repository->remove('pm');
        self::assertNull($repository->find('pm'));
        self::assertSame(['cloakroom', 'desk-am'], $ids($repository->ofVolunteer('ada')));
    }

    public function testAVolunteerIsErasedFromEveryRoster(): void
    {
        $stage = new Stage();
        $repository = $stage->rosterRepository;
        $repository->add(Roster::open(Stage::desk(capacity: 1))->signUp('ada', [], Stage::at('08:00:00'))->signUp('bob', [], Stage::at('08:00:00')));
        $repository->add(Roster::open(Stage::cloakroom())->expressInterest('ada', [], Stage::at('07:00:00')));
        $repository->add(Roster::open(Stage::desk('pm', '13:00:00', '14:00:00')));

        self::assertSame(['cloakroom', 'desk-am'], $repository->eraseVolunteer('ada'));
        self::assertSame([], $repository->ofVolunteer('ada'));
        self::assertSame(PlaceStatus::Reserve, $repository->find('desk-am')?->roster->statusOf('bob'));
        self::assertSame(2, $repository->find('desk-am')?->revision);
        self::assertSame([], $repository->eraseVolunteer('ada'));
    }

    public function testAWriteTouchesOnlyItsOwnPlaces(): void
    {
        $stage = new Stage();
        $repository = $stage->rosterRepository;
        $repository->add(Roster::open(Stage::desk())->signUp('ada', [], Stage::at('08:00:00')));
        $repository->add(Roster::open(Stage::desk('pm', '13:00:00', '14:00:00'))->signUp('bob', [], Stage::at('08:00:00')));

        $repository->update(Roster::open(Stage::desk())->signUp('cy', [], Stage::at('08:00:00')), 1);

        self::assertSame([['bob', 'pm'], ['cy', 'desk-am']], $stage->connection->fetchAllNumeric('SELECT volunteer, shift_id FROM phil_shifts_places ORDER BY volunteer'));
    }

    public function testTheListsAreCutAtTwoHundredByDefault(): void
    {
        $stage = new Stage();

        for ($index = 0; $index < 201; ++$index) {
            $stage->rosterRepository->add(Roster::open(Stage::desk('s'.$index)));
            $stage->swapRepository->add(new SwapOffer('o'.$index, 'desk-am', 'ada', Stage::at('06:00:00')));
        }

        self::assertCount(200, $stage->rosterRepository->upcoming(Stage::at('00:00:00')));
        self::assertCount(200, $stage->swapRepository->active());
    }

    public function testAnErasureIsWrittenAgainFiveTimesThenRefusedAsBusy(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => RacingConnection::class]);

        foreach (ShiftsTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        self::assertInstanceOf(RacingConnection::class, $connection);
        $repository = new DbalRosterRepository($connection, new MockClock('2026-11-27 10:00:00 UTC'));
        $repository->add(Roster::open(Stage::desk())->signUp('ada', [], Stage::at('08:00:00')));

        $connection->writes = 0;
        $connection->losses = 5;

        try {
            $repository->eraseVolunteer('ada');
            self::fail('The erasure is refused.');
        } catch (RequestRefused $requestRefused) {
            self::assertSame(RequestRefused::BUSY, $requestRefused->reason);
        }

        self::assertSame(5, $connection->writes);
        self::assertSame(PlaceStatus::Confirmed, $repository->find('desk-am')?->roster->statusOf('ada'));

        $connection->writes = 0;
        $connection->losses = 4;
        self::assertSame(['desk-am'], $repository->eraseVolunteer('ada'));
        self::assertSame(5, $connection->writes);
        self::assertNull($repository->find('desk-am')?->roster->statusOf('ada'));
    }

    public function testTheOffersOfTheSwapBoard(): void
    {
        $stage = new Stage();
        $repository = $stage->swapRepository;
        $open = new SwapOffer('s1', 'desk-am', 'ada', Stage::at('06:00:00'), 'Sick');
        $older = new SwapOffer('s0', 'pm', 'bob', Stage::at('05:00:00'));
        $repository->add($open);
        $repository->add($older);

        self::assertEquals($open, $repository->find('s1')?->offer);
        self::assertSame(1, $repository->find('s1')?->revision);
        self::assertNull($repository->find('missing'));
        self::assertSame(['s0', 's1'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->active()));
        self::assertSame(['s0'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->active(1)));
        self::assertSame(['s0'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->active(0)));

        $taken = new SwapOffer('s1', 'desk-am', 'ada', Stage::at('06:00:00'), 'Sick', SwapStatus::Pending, 'cy');
        self::assertTrue($repository->update($taken, 1));
        self::assertFalse($repository->update($taken, 1));
        self::assertSame(2, $repository->find('s1')?->revision);
        self::assertSame(['cy', 'pending'], $stage->connection->fetchNumeric('SELECT taken_by, status FROM phil_shifts_swaps WHERE id = ?', ['s1']));
        self::assertSame(['s1'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->ofVolunteer('cy')));
        self::assertSame(['s1'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->ofVolunteer('ada')));

        $repository->update(new SwapOffer('s0', 'pm', 'bob', Stage::at('05:00:00'), '', SwapStatus::Cancelled, null, 'bob', Stage::at('05:30:00')), 1);
        self::assertSame(['s1'], array_map(static fn (SwapOffer $offer): string => $offer->id, $repository->active()));

        $repository->eraseVolunteer('cy');
        self::assertNull($repository->find('s1'));
        self::assertNotNull($repository->find('s0'));
        $repository->eraseVolunteer('bob');
        self::assertNull($repository->find('s0'));
    }

    public function testRemovingAShiftRemovesItsOffers(): void
    {
        $stage = new Stage();
        $stage->rosterRepository->add(Roster::open(Stage::desk())->signUp('ada', [], Stage::at('08:00:00')));
        $stage->swapRepository->add(new SwapOffer('s1', 'desk-am', 'ada', Stage::at('06:00:00')));
        $stage->swapRepository->add(new SwapOffer('s2', 'other', 'bob', Stage::at('06:00:00')));

        $stage->rosterRepository->remove('desk-am');

        self::assertNull($stage->swapRepository->find('s1'));
        self::assertNotNull($stage->swapRepository->find('s2'));
        self::assertSame([], $stage->connection->fetchFirstColumn('SELECT volunteer FROM phil_shifts_places'));
    }

    public function testATransactionRollsBack(): void
    {
        $stage = new Stage();
        $transaction = new DbalTransaction($stage->connection);

        self::assertSame(42, $transaction->run(static fn (): int => 42));

        try {
            $transaction->run(static function () use ($stage): never {
                $stage->swapRepository->add(new SwapOffer('s1', 'desk-am', 'ada', Stage::at('06:00:00')));

                throw new LogicException('rolled back');
            });
        } catch (LogicException $logicException) {
            $thrown = $logicException->getMessage();
        }

        self::assertSame('rolled back', $thrown);
        self::assertNull($stage->swapRepository->find('s1'));
    }

    public function testAGuardianIsReplacedAndAStoredValueOutOfItsShapeIsRefused(): void
    {
        $stage = new Stage();
        $repository = $stage->guardianRepository;
        $repository->save(new GuardianContact('ada', 'Marie', '5550100', null, 'grace'));
        $repository->save(new GuardianContact('ada', 'Marie Curie', null, 'marie@example.org', 'olga', new DateTimeImmutable('2026-11-27T10:00:00Z'), 'olga'));

        self::assertSame(1, Json::int($stage->connection->fetchOne('SELECT COUNT(*) FROM phil_shifts_guardians')));
        self::assertSame('2026-11-27 10:00:00', $stage->connection->fetchOne('SELECT consented_at FROM phil_shifts_guardians'));
        self::assertEquals(new GuardianContact('ada', 'Marie Curie', null, 'marie@example.org', 'olga', new DateTimeImmutable('2026-11-27T10:00:00Z'), 'olga'), $repository->find('ada'));
        $repository->remove('ada');
        self::assertNull($repository->find('ada'));

        $stage->connection->insert('phil_shifts_absences', ['shift_id' => 'desk-am', 'volunteer' => 'ada', 'recorded_at' => 'not an instant', 'recorded_by' => 'grace']);

        try {
            $stage->absenceRepository->ofVolunteer('ada');
            self::fail('The stored instant is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('A stored column holds an instant.', $invalidDocument->getMessage());
        }

        self::assertNull(Json::instant($stage->connection, null));
        try {
            Json::requiredInstant($stage->connection, null);
            self::fail('A missing instant is refused.');
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame('A stored column holds an instant.', $invalidDocument->getMessage());
        }

        self::assertEquals(new DateTimeImmutable('2026-11-27T10:00:00Z'), Json::instant($stage->connection, '2026-11-27 10:00:00'));
    }

    public function testTheGuardiansOfOtherVolunteersAreKept(): void
    {
        $stage = new Stage();
        $repository = $stage->guardianRepository;
        $repository->save(new GuardianContact('ada', 'Marie', '5550100', null, 'grace'));
        $repository->save(new GuardianContact('bob', 'Pierre', '5550101', null, 'grace'));
        $repository->save(new GuardianContact('bob', 'Paul', '5550101', null, 'grace'));

        self::assertSame('Marie', $repository->find('ada')?->name);
        self::assertSame('Paul', $repository->find('bob')?->name);

        $repository->remove('bob');
        self::assertNull($repository->find('bob'));
        self::assertSame('Marie', $repository->find('ada')?->name);
    }

    public function testAnAbsenceIsKeyedByShiftAndVolunteerAndRemovedAlone(): void
    {
        $stage = new Stage();
        $repository = $stage->absenceRepository;
        $at = new DateTimeImmutable('2026-11-28T09:00:00Z');

        self::assertTrue($repository->add(new Absence('early', 'ada', $at, 'grace')));
        self::assertTrue($repository->add(new Absence('late', 'ada', $at, 'grace')));
        self::assertTrue($repository->add(new Absence('early', 'bob', $at, 'grace')));
        self::assertFalse($repository->add(new Absence('early', 'ada', $at, 'grace')));
        self::assertSame(2, Json::int($stage->connection->fetchOne("SELECT COUNT(*) FROM phil_shifts_absences WHERE volunteer = 'ada'")));

        self::assertTrue($repository->remove('early', 'ada'));
        self::assertFalse($repository->remove('early', 'ada'));
        self::assertSame(['ada'], $repository->absentFrom('late'));
        self::assertSame(['bob'], $repository->absentFrom('early'));
        self::assertSame(['late'], array_map(static fn (Absence $absence): string => $absence->shiftId, $repository->ofVolunteer('ada')));
    }

    public function testAColumnOutOfItsShapeIsRefusedInTheListOfAbsent(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => ForeignColumnConnection::class]);
        $repository = new DbalAbsenceRepository($connection);

        $this->expectException(InvalidDocument::class);
        $repository->absentFrom('early');
    }

    public function testTheTablesAndTheirCollation(): void
    {
        $schema = ShiftsTables::configure(Schema::editor()->create(), new MySQLPlatform());

        self::assertSame(ShiftsTables::ALL, array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables()));
        $volunteer = $schema->getTable(ShiftsTables::PLACES)->getColumn('volunteer');
        self::assertSame('utf8mb4_bin', $volunteer->getCollation());
        self::assertSame(191, $volunteer->getLength());
        self::assertNull(ShiftsTables::configure(Schema::editor()->create())->getTable(ShiftsTables::PLACES)->getColumn('volunteer')->getCollation());
        self::assertFalse($schema->getTable(ShiftsTables::SWAPS)->getColumn('taken_by')->getNotnull());
        self::assertTrue($schema->getTable(ShiftsTables::SWAPS)->getColumn('offered_by')->getNotnull());
        self::assertTrue($schema->getTable(ShiftsTables::SHIFTS)->hasIndex('phil_shifts_shifts_ends'));
        self::assertTrue($schema->getTable(ShiftsTables::SWAPS)->hasIndex('phil_shifts_swaps_taken'));

        self::assertSame(16, $schema->getTable(ShiftsTables::PLACES)->getColumn('status')->getLength());
        self::assertSame(16, $schema->getTable(ShiftsTables::SWAPS)->getColumn('status')->getLength());
        self::assertSame(['volunteer'], $this->indexed($schema->getTable(ShiftsTables::PLACES), 'phil_shifts_places_volunteer'));
        self::assertSame(['status', 'created_at'], $this->indexed($schema->getTable(ShiftsTables::SWAPS), 'phil_shifts_swaps_status'));
        self::assertSame(['shift_id'], $this->indexed($schema->getTable(ShiftsTables::SWAPS), 'phil_shifts_swaps_shift'));
        self::assertSame(['offered_by'], $this->indexed($schema->getTable(ShiftsTables::SWAPS), 'phil_shifts_swaps_offered'));
        self::assertSame(['taken_by'], $this->indexed($schema->getTable(ShiftsTables::SWAPS), 'phil_shifts_swaps_taken'));

        self::assertSame('utf8mb4_bin', $schema->getTable(ShiftsTables::GUARDIANS)->getColumn('volunteer')->getCollation());
        self::assertNull($schema->getTable(ShiftsTables::GUARDIANS)->getColumn('name')->getCollation());
        self::assertSame(120, $schema->getTable(ShiftsTables::GUARDIANS)->getColumn('name')->getLength());
        self::assertSame(32, $schema->getTable(ShiftsTables::GUARDIANS)->getColumn('phone')->getLength());
        self::assertSame(254, $schema->getTable(ShiftsTables::GUARDIANS)->getColumn('email')->getLength());
        self::assertFalse($schema->getTable(ShiftsTables::GUARDIANS)->getColumn('phone')->getNotnull());
        self::assertFalse($schema->getTable(ShiftsTables::GUARDIANS)->getColumn('consented_at')->getNotnull());
        self::assertTrue($schema->getTable(ShiftsTables::GUARDIANS)->getColumn('name')->getNotnull());
        self::assertTrue($schema->getTable(ShiftsTables::ABSENCES)->getColumn('recorded_at')->getNotnull());
        self::assertFalse($schema->getTable(ShiftsTables::ABSENCES)->getColumn('lifted_at')->getNotnull());
        self::assertFalse($schema->getTable(ShiftsTables::ABSENCES)->getColumn('lifted_by')->getNotnull());
        self::assertSame(['volunteer', 'recorded_at'], $this->indexed($schema->getTable(ShiftsTables::ABSENCES), 'phil_shifts_absences_volunteer'));

        $existing = Schema::editor()->create();
        $again = ShiftsTables::configure(ShiftsTables::configure($existing));
        self::assertCount(5, $again->getTables());
    }

    /**
     * @return list<string> the columns of an index, in its order
     */
    private function indexed(Table $table, string $index): array
    {
        return array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $table->getIndex($index)->getIndexedColumns());
    }

    public function testTheListenerCompletesTheSchemaOfItsConnection(): void
    {
        $stage = new Stage();
        $other = new Stage();
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($stage->connection);

        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new ShiftsSchemaListener($stage->connection)->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(ShiftsTables::SWAPS));

        $untouched = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new ShiftsSchemaListener($other->connection)->postGenerateSchema($untouched);
        self::assertFalse($untouched->getSchema()->hasTable(ShiftsTables::SWAPS));
    }

    public function testTheValuesOfTheDrivers(): void
    {
        self::assertSame(['a' => 'é/'], Json::decode(Json::encode(['a' => 'é/'])));
        self::assertSame('{"a":"é/"}', Json::encode(['a' => 'é/']));
        self::assertSame(12, Json::int('12'));
        self::assertSame(-3, Json::int(-3));
        self::assertSame('x', Json::string('x'));
        self::assertSame(['x' => 1], Json::decode('{"x":1}'));
        self::assertSame(-12, Json::int('-12'));
        self::assertSame(999999999999999999, Json::int(str_repeat('9', 18)));
        self::assertIsArray(Json::decode(str_repeat('[', 15).'1'.str_repeat(']', 15)));

        foreach ([static fn (): mixed => Json::decode('{'), static fn (): mixed => Json::decode(3), static fn (): int => Json::int('1.5'), static fn (): int => Json::int("12\n"), static fn (): mixed => Json::decode(str_repeat('[', 16).'1'.str_repeat(']', 16)), static fn (): int => Json::int(null), static fn (): int => Json::int(str_repeat('9', 19))] as $read) {
            try {
                $read();
                self::fail('The value is refused.');
            } catch (InvalidDocument) {
                self::addToAssertionCount(1);
            }
        }
    }
}
