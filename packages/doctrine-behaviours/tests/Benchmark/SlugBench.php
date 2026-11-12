<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Symfony\Component\Clock\MockClock;

/**
 * A flush that makes a slug against a table holding 90 of its numbered forms: one query, whatever the count. The new
 * row is removed after each revolution, so the table stays the same.
 *
 * @Groups({"doctrine"})
 */
final readonly class SlugBench
{
    private const int TAKEN = 90;

    private EntityManager $manager;

    public function __construct()
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-04 12:00 UTC')), new MutableBlame());
        $connection = $this->manager->getConnection();

        for ($number = 1; $number <= self::TAKEN; ++$number) {
            $connection->executeStatement("INSERT INTO tag (id, name, slug) VALUES (?, 'x', ?)", ['seed-'.$number, 1 === $number ? 'hello' : 'hello-'.$number]);
        }
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchFlushWithASlug(): void
    {
        $this->manager->persist(new Tag('bench', 'Hello'));
        $this->manager->flush();
        $this->manager->clear();
        $this->manager->getConnection()->executeStatement("DELETE FROM tag WHERE id = 'bench'");
    }
}
