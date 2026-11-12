<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\DoctrineBehaviours\Exception\InvalidScope;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\Place;
use Phil\DoctrineBehaviours\PositionableEntity;
use Phil\DoctrineBehaviours\PositionListener;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\Scope;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Badge;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Course;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Encore;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Lesson;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Level;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Seat;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Ticket;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Track;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\PrefixedType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PositionListener::class)]
#[CoversClass(Positions::class)]
#[CoversClass(Place::class)]
#[CoversClass(Scope::class)]
#[CoversClass(InvalidScope::class)]
#[CoversTrait(PositionableEntity::class)]
final class PositionTest extends TestCase
{
    private EntityManager $manager;

    private Positions $positions;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-10 08:00')), new FixedBlame());
        $this->positions = new Positions($this->manager);
    }

    public function testRecordsWithoutPositionGoToTheEndOfTheTable(): void
    {
        $first = new Track('a');
        $second = new Track('b');
        $this->manager->persist($first);
        $this->manager->persist($second);
        $this->manager->flush();

        $third = new Track('c');
        $this->manager->persist($third);
        $this->manager->flush();

        self::assertSame([1, 2, 3], [$first->position(), $second->position(), $third->position()]);
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], $this->stored('track'));
    }

    public function testAGivenPositionIsKept(): void
    {
        $track = new Track('a');
        $track->placeAt(7);

        $this->manager->persist($track);
        $this->manager->flush();

        $next = new Track('b');
        $this->manager->persist($next);
        $this->manager->flush();

        self::assertSame(['a' => 7, 'b' => 8], $this->stored('track'));
    }

    public function testEachGroupHasItsOwnOrder(): void
    {
        $music = new Course('music');
        $chess = new Course('chess');
        $this->manager->persist($music);
        $this->manager->persist($chess);
        $this->manager->flush();

        $lessons = [
            new Lesson('m1', 'Scales', $music),
            new Lesson('c1', 'Openings', $chess),
            new Lesson('m2', 'Chords', $music),
            new Lesson('m3', 'Modes', $music, Level::Advanced),
            new Lesson('n1', 'Alone'),
            new Lesson('n2', 'Lonely'),
            new Lesson('n3', 'Apart', level: Level::Advanced),
        ];

        foreach ($lessons as $lesson) {
            $this->manager->persist($lesson);
        }

        $this->manager->flush();

        self::assertSame(['c1' => 1, 'm1' => 1, 'm2' => 2, 'm3' => 1, 'n1' => 1, 'n2' => 2, 'n3' => 1], $this->stored('lesson'));

        $more = new Lesson('m4', 'Rhythm', $music);
        $this->manager->persist($more);
        $this->manager->flush();

        self::assertSame(3, $more->position());
    }

    public function testRecordsOfANewGroupStartAtOne(): void
    {
        $course = new Course('new');
        $other = new Course('other');
        $first = new Lesson('a', 'One', $course);
        $second = new Lesson('b', 'Two', $course);
        $advanced = new Lesson('c', 'Three', $course, Level::Advanced);
        $elsewhere = new Lesson('d', 'Four', $other);

        foreach ([$course, $other, $first, $second, $advanced, $elsewhere] as $entity) {
            $this->manager->persist($entity);
        }

        $this->manager->flush();

        self::assertSame([1, 2, 1, 1], [$first->position(), $second->position(), $advanced->position(), $elsewhere->position()]);
    }

    public function testASubclassInATableOfItsOwnIsOrderedInItsParentsTable(): void
    {
        $first = new Encore('a');
        $second = new Encore('b');
        $this->manager->persist($first);
        $this->manager->persist($second);
        $this->manager->flush();

        $this->positions->moveFirst($second);

        self::assertSame(['a' => 2, 'b' => 1], $this->stored('stage'));
    }

    public function testARecordThatChangesGroupGoesToTheEndOfItsNewGroup(): void
    {
        $music = new Course('music');
        $chess = new Course('chess');
        $scales = new Lesson('m1', 'Scales', $music);
        $openings = new Lesson('c1', 'Openings', $chess);
        $endings = new Lesson('c2', 'Endings', $chess);

        foreach ([$music, $chess, $scales, $openings, $endings] as $entity) {
            $this->manager->persist($entity);
        }

        $this->manager->flush();

        $scales->course = $chess;
        $this->manager->flush();

        self::assertSame(3, $scales->position());

        $scales->level = Level::Beginner;
        $scales->placeAt(9);

        $this->manager->flush();

        self::assertSame(9, $scales->position(), 'a position given with the move is kept');

        $openings->title = 'Gambits';
        $this->manager->flush();

        self::assertSame(1, $openings->position(), 'another change leaves the position alone');
    }

    public function testAPositionSetBackToNullGoesToTheEnd(): void
    {
        $first = new Track('a');
        $second = new Track('b');
        $this->manager->persist($first);
        $this->manager->persist($second);
        $this->manager->flush();

        $first->placeAt(null);
        $this->manager->flush();

        self::assertSame(['a' => 3, 'b' => 2], $this->stored('track'));
    }

    public function testMoveBeforeAndAfter(): void
    {
        [$a, $b, $c, $d] = $this->tracks('a', 'b', 'c', 'd');

        $this->positions->moveBefore($d, $b);

        self::assertSame(['a' => 1, 'b' => 3, 'c' => 4, 'd' => 2], $this->stored('track'));
        self::assertSame([1, 3, 4, 2], [$a->position(), $b->position(), $c->position(), $d->position()], 'the loaded records follow');

        $this->positions->moveAfter($a, $c);

        self::assertSame(['a' => 5, 'b' => 3, 'c' => 4, 'd' => 2], $this->stored('track'));
        self::assertSame(5, $a->position());

        $work = $this->manager->getUnitOfWork();
        $work->computeChangeSets();
        self::assertSame([[], [], [], []], [$work->getEntityChangeSet($a), $work->getEntityChangeSet($b), $work->getEntityChangeSet($c), $work->getEntityChangeSet($d)], 'the originals follow too');

        $this->manager->flush();
        self::assertSame(['a' => 5, 'b' => 3, 'c' => 4, 'd' => 2], $this->stored('track'), 'nothing left to flush');
    }

    public function testAMoveLocksTheRowItIsPlacedAgainstFirst(): void
    {
        [, $b, , $d] = $this->tracks('a', 'b', 'c', 'd');
        $connection = $this->manager->getConnection();
        $connection->executeStatement('CREATE TABLE touched (seq INTEGER PRIMARY KEY AUTOINCREMENT, id VARCHAR(36))');
        $connection->executeStatement('CREATE TRIGGER track_touched AFTER UPDATE OF position ON track BEGIN INSERT INTO touched (id) VALUES (NEW.id); END');

        $this->positions->moveAfter($d, $b);

        self::assertSame(['b', 'c', 'd', 'd'], $connection->fetchFirstColumn('SELECT id FROM touched ORDER BY seq'), 'the lock, the records stepping down, then the record');
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 4, 'd' => 3], $this->stored('track'));
    }

    public function testAMoveToAnEndLocksTheWholeGroupFirst(): void
    {
        [$a, , , $d] = $this->tracks('a', 'b', 'c', 'd');
        $connection = $this->manager->getConnection();
        $connection->executeStatement('CREATE TABLE touched (seq INTEGER PRIMARY KEY AUTOINCREMENT, id VARCHAR(36))');
        $connection->executeStatement('CREATE TRIGGER track_touched AFTER UPDATE OF position ON track BEGIN INSERT INTO touched (id) VALUES (NEW.id); END');

        $this->positions->moveFirst($d);
        $touched = $connection->fetchFirstColumn('SELECT id FROM touched ORDER BY seq');
        $lock = \array_slice($touched, 0, 4);
        sort($lock);

        self::assertSame(['a', 'b', 'c', 'd'], $lock, 'the whole group is locked first');
        $shifted = \array_slice($touched, 4, 4);
        sort($shifted);

        self::assertSame(['a', 'b', 'c', 'd'], $shifted, 'then the records step down');
        self::assertSame(['d'], \array_slice($touched, 8), 'and the record takes the first place');
        self::assertSame(['a' => 2, 'b' => 3, 'c' => 4, 'd' => 1], $this->stored('track'));

        $connection->executeStatement('DELETE FROM touched');
        $this->positions->moveLast($a);
        $touched = $connection->fetchFirstColumn('SELECT id FROM touched ORDER BY seq');
        $lock = \array_slice($touched, 0, 4);
        sort($lock);

        self::assertSame(['a', 'b', 'c', 'd'], $lock, 'the whole group is locked first');
        self::assertSame(['a'], \array_slice($touched, 4), 'then only the record moves');
        self::assertSame(['a' => 5, 'b' => 3, 'c' => 4, 'd' => 1], $this->stored('track'));
    }

    public function testAMoveToAnEndLocksItsOwnGroupOnly(): void
    {
        $music = new Course('music');
        $chess = new Course('chess');
        $scales = new Lesson('m1', 'Scales', $music);
        $chords = new Lesson('m2', 'Chords', $music);
        $openings = new Lesson('c1', 'Openings', $chess);

        foreach ([$music, $chess, $scales, $chords, $openings] as $record) {
            $this->manager->persist($record);
        }

        $this->manager->flush();
        $connection = $this->manager->getConnection();
        $connection->executeStatement('CREATE TABLE touched (seq INTEGER PRIMARY KEY AUTOINCREMENT, id VARCHAR(36))');
        $connection->executeStatement('CREATE TRIGGER lesson_touched AFTER UPDATE OF position ON lesson BEGIN INSERT INTO touched (id) VALUES (NEW.id); END');

        $this->positions->moveLast($scales);

        $touched = $connection->fetchFirstColumn('SELECT id FROM touched ORDER BY seq');
        self::assertNotContains('c1', $touched, 'another group is not locked');
        sort($touched);
        self::assertSame(['m1', 'm1', 'm2'], $touched, 'the lock on both lessons of the group, then the record');
    }

    public function testARecordWithACompositeIdentifierMovesWithinItsGroup(): void
    {
        $a1 = new Seat('a', '1');
        $a2 = new Seat('a', '2');
        $b1 = new Seat('b', '1');
        $side = new Seat('b', '2', 'side');

        foreach ([$a1, $a2, $b1, $side] as $seat) {
            $this->manager->persist($seat);
        }

        $this->manager->flush();

        $this->positions->moveFirst($a2);
        self::assertSame(['a1' => 2, 'a2' => 1, 'b1' => 4, 'b2' => 1], $this->seats());

        $this->positions->moveFirst($a2);
        self::assertSame(['a1' => 3, 'a2' => 2, 'b1' => 5, 'b2' => 1], $this->seats(), 'moved first again, it stays ahead of the others');

        $this->positions->moveLast($b1);
        self::assertSame(['a1' => 3, 'a2' => 2, 'b1' => 4, 'b2' => 1], $this->seats(), 'moved last again, it stays behind the others');

        $this->positions->moveLast($a1);
        $this->positions->moveLast($a2);
        self::assertSame(['a1' => 5, 'a2' => 6, 'b1' => 4, 'b2' => 1], $this->seats());
        self::assertSame([5, 6, 4, 1], [$a1->position(), $a2->position(), $b1->position(), $side->position()]);
    }

    public function testMoveAfterShiftsTheRecordsAfter(): void
    {
        $this->tracks('a', 'b', 'c');
        $this->manager->clear();
        $a = $this->find('a');
        $c = $this->find('c');

        $this->positions->moveAfter($c, $a);

        self::assertSame(['a' => 1, 'b' => 3, 'c' => 2], $this->stored('track'));
    }

    public function testMoveFirstAndLast(): void
    {
        [$a, $b, $c] = $this->tracks('a', 'b', 'c');

        $this->positions->moveFirst($c);

        self::assertSame(['a' => 2, 'b' => 3, 'c' => 1], $this->stored('track'));

        $this->positions->moveLast($a);

        self::assertSame(['a' => 4, 'b' => 3, 'c' => 1], $this->stored('track'));
        self::assertSame([4, 3, 1], [$a->position(), $b->position(), $c->position()]);
    }

    public function testAMoveAloneOrAgainstItselfChangesNothing(): void
    {
        [$a] = $this->tracks('a');

        $this->positions->moveFirst($a);
        $this->positions->moveLast($a);
        $this->positions->moveBefore($a, $a);

        self::assertSame(['a' => 1], $this->stored('track'));
    }

    public function testAMoveAgainstItselfChangesNothing(): void
    {
        [$a, $b] = $this->tracks('a', 'b');

        $this->positions->moveBefore($a, $a);
        $this->positions->moveAfter($b, $b);

        self::assertSame(['a' => 1, 'b' => 2], $this->stored('track'));
    }

    public function testAMoveToTheEndShiftsNothing(): void
    {
        [$a, , $c] = $this->tracks('a', 'b', 'c');
        $this->manager->getConnection()->executeStatement("UPDATE track SET position = 1 WHERE id = 'c'");

        $this->positions->moveLast($a);

        self::assertSame(['a' => 3, 'b' => 2, 'c' => 1], $this->stored('track'));
        self::assertSame([3, 3], [$a->position(), $c->position()], 'not even a loaded record whose row changed behind it');
    }

    public function testAMoveAgainstARecordWithoutPositionChangesNothing(): void
    {
        [$a, $b] = $this->tracks('a', 'b');
        $this->manager->getConnection()->executeStatement("UPDATE track SET position = NULL WHERE id = 'b'");

        $this->positions->moveBefore($a, $b);

        self::assertSame(['a' => 1, 'b' => null], $this->stored('track'));
    }

    public function testAMoveStaysInItsGroup(): void
    {
        $music = new Course('music');
        $chess = new Course('chess');
        $scales = new Lesson('m1', 'Scales', $music);
        $chords = new Lesson('m2', 'Chords', $music);
        $openings = new Lesson('c1', 'Openings', $chess);

        foreach ([$music, $chess, $scales, $chords, $openings] as $entity) {
            $this->manager->persist($entity);
        }

        $this->manager->flush();

        $this->positions->moveBefore($chords, $scales);

        self::assertSame(['c1' => 1, 'm1' => 2, 'm2' => 1], $this->stored('lesson'));
        self::assertSame(1, $openings->position(), 'a loaded record of another group stays');

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('across groups');
        $this->positions->moveAfter($openings, $scales);
    }

    public function testALoadedRecordChangedInMemoryKeepsItsChange(): void
    {
        [$a, $b, $c] = $this->tracks('a', 'b', 'c');
        $b->placeAt(40);

        $this->positions->moveBefore($c, $a);

        self::assertSame(40, $b->position());
        self::assertSame(2, $a->position());

        $this->manager->flush();
        self::assertSame(['a' => 2, 'b' => 40, 'c' => 1], $this->stored('track'));
    }

    public function testAMovedRecordChangedInMemoryTakesItsNewPlace(): void
    {
        [, , $c] = $this->tracks('a', 'b', 'c');
        $c->placeAt(40);

        $this->positions->moveFirst($c);

        self::assertSame(1, $c->position());

        $this->manager->flush();
        self::assertSame(['a' => 2, 'b' => 3, 'c' => 1], $this->stored('track'));
    }

    public function testAnotherRecordChangedInMemoryIsStillWrittenAfterAMove(): void
    {
        [$a, $b, $c] = $this->tracks('a', 'b', 'c');
        $b->placeAt(40);

        $this->positions->moveBefore($c, $a);
        $b->placeAt(41);

        $this->manager->flush();
        self::assertSame(['a' => 2, 'b' => 41, 'c' => 1], $this->stored('track'));
    }

    public function testAGroupRelationToARecordOutsideTheUnitOfWorkUsesItsIdentifier(): void
    {
        $course = new Course('music');
        $scales = new Lesson('m1', 'Scales', $course);
        $chords = new Lesson('m2', 'Chords', $course);

        foreach ([$course, $scales, $chords] as $entity) {
            $this->manager->persist($entity);
        }

        $this->manager->flush();
        $this->manager->detach($course);

        $this->positions->moveFirst($chords);

        self::assertSame(['m1' => 2, 'm2' => 1], $this->stored('lesson'));
    }

    public function testARecordWhoseGroupHasNoIdentifierYetCannotMove(): void
    {
        $lesson = new Lesson('m1', 'Scales');
        $this->manager->persist($lesson);
        $this->manager->flush();

        $lesson->group = ['ticket'];
        $lesson->ticket = new Ticket('later');

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('flush it first');
        $this->positions->moveLast($lesson);
    }

    public function testARecordNotStoredCannotMove(): void
    {
        [$a] = $this->tracks('a');
        $new = new Track('new');
        $this->manager->persist($new);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('flush it first');
        $this->positions->moveBefore($new, $a);
    }

    public function testADetachedRecordCannotMove(): void
    {
        $this->expectException(InvalidScope::class);
        $this->positions->moveFirst(new Track('nowhere'));
    }

    public function testARecordWhoseGroupIsNotStoredCannotMove(): void
    {
        $course = new Course('music');
        $lesson = new Lesson('m1', 'Scales', $course);
        $this->manager->persist($course);
        $this->manager->persist($lesson);
        $this->manager->flush();

        $lesson->course = new Course('later');
        $this->manager->persist($lesson->course);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('flush it first');
        $this->positions->moveLast($lesson);
    }

    public function testARecordIdentifiedByARelationCannotMove(): void
    {
        $course = new Course('music');
        $badge = new Badge($course);
        $this->manager->persist($course);
        $this->manager->persist($badge);
        $this->manager->flush();

        self::assertSame(1, $badge->position());

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('identifier is itself a relation');
        $this->positions->moveFirst($badge);
    }

    public function testAGroupNamesColumnsAndToOneRelationsOnly(): void
    {
        $lesson = new Lesson('m1', 'Scales');
        $lesson->group = ['nothing'];

        $this->manager->persist($lesson);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('"nothing"');
        $this->manager->flush();
    }

    public function testAGroupCannotNameARelationHeldByTwoColumns(): void
    {
        $lesson = new Lesson('m1', 'Scales');
        $lesson->group = ['seat'];

        $this->manager->persist($lesson);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('"seat"');
        $this->manager->flush();
    }

    public function testAGroupCannotNameAToManyRelation(): void
    {
        $course = new Course('music');
        $course->lessons->add(new Lesson('x', 'X'));

        $lesson = new Lesson('m1', 'Scales');
        $lesson->group = ['lessons'];

        $this->manager->persist($lesson);

        $this->expectException(InvalidScope::class);
        $this->manager->flush();
    }

    public function testAGroupCannotNameARelationToARecordIdentifiedByARelation(): void
    {
        $course = new Course('music');
        $badge = new Badge($course);
        $this->manager->persist($course);
        $this->manager->persist($badge);
        $this->manager->flush();

        $lesson = new Lesson('m1', 'Scales', badge: $badge);
        $lesson->group = ['badge'];

        $this->manager->persist($lesson);

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('"badge"');
        $this->manager->flush();
    }

    /**
     * @return list<Track>
     */
    private function tracks(string ...$ids): array
    {
        $tracks = [];

        foreach ($ids as $id) {
            $tracks[] = $track = new Track($id);
            $this->manager->persist($track);
        }

        $this->manager->flush();

        return $tracks;
    }

    private function find(string $id): Track
    {
        $track = $this->manager->find(Track::class, $id);
        self::assertInstanceOf(Track::class, $track);

        return $track;
    }

    /**
     * @return array<string, ?int> the positions of the seats by aisle and number, read as stored
     */
    private function seats(): array
    {
        $positions = [];

        foreach ($this->manager->getConnection()->fetchAllAssociative('SELECT aisle, number, position FROM seat ORDER BY aisle, number') as $row) {
            self::assertIsString($row['aisle']);
            self::assertIsString($row['number']);
            self::assertStringStartsWith(PrefixedType::PREFIX, $row['number']);
            $positions[$row['aisle'].substr($row['number'], \strlen(PrefixedType::PREFIX))] = is_numeric($row['position']) ? (int) $row['position'] : null;
        }

        return $positions;
    }

    /**
     * @return array<string, ?int>
     */
    private function stored(string $table): array
    {
        $positions = [];

        foreach ($this->manager->getConnection()->fetchAllAssociative(\sprintf('SELECT id, position FROM %s ORDER BY id', $table)) as $row) {
            self::assertIsString($row['id']);
            $positions[$row['id']] = is_numeric($row['position']) ? (int) $row['position'] : null;
        }

        return $positions;
    }
}
