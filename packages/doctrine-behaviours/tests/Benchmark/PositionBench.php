<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Track;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Symfony\Component\Clock\MockClock;

/**
 * A flush that sends a record to the end of a table of 500 positions, one query whatever the count; a move to the front
 * of it, which locks the group and shifts every position in one statement each; and a move to the end, which locks the
 * group and writes one row. The new row is removed after each revolution.
 *
 * @Groups({"doctrine"})
 */
final readonly class PositionBench
{
    private const int ROWS = 500;

    private EntityManager $manager;

    public function __construct()
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-10 12:00 UTC')), new MutableBlame());
        $connection = $this->manager->getConnection();

        for ($number = 1; $number <= self::ROWS; ++$number) {
            $connection->executeStatement('INSERT INTO track (id, position) VALUES (?, ?)', ['seed-'.$number, $number]);
        }
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchFlushToTheEnd(): void
    {
        $this->manager->persist(new Track('bench'));
        $this->manager->flush();
        $this->manager->clear();
        $this->manager->getConnection()->executeStatement("DELETE FROM track WHERE id = 'bench'");
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchMoveFirst(): void
    {
        $track = new Track('bench');
        $this->manager->persist($track);
        $this->manager->flush();
        new Positions($this->manager)->moveFirst($track);
        $this->manager->clear();
        $this->manager->getConnection()->executeStatement("DELETE FROM track WHERE id = 'bench'");
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchMoveLast(): void
    {
        $track = new Track('bench');
        $track->placeAt(1);

        $this->manager->persist($track);
        $this->manager->flush();
        new Positions($this->manager)->moveLast($track);
        $this->manager->clear();
        $this->manager->getConnection()->executeStatement("DELETE FROM track WHERE id = 'bench'");
    }
}
