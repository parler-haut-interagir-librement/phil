<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Doctrine\DoctrineReactionRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping as an application's schema tools create it.
 */
#[CoversClass(DoctrineReactionRepository::class)]
#[CoversClass(AlreadyReacted::class)]
#[CoversClass(Reaction::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testTheMapping(): void
    {
        self::assertSame('phil_reaction', Reaction::TABLE);

        $this->assertInstanceOf(EntityManager::class, $this->entityManager);
        $metadata = $this->entityManager->getClassMetadata(Reaction::class);
        self::assertSame(['id', 'content', 'member', 'code', 'reactedAt'], $metadata->getFieldNames());
        self::assertSame([36, 320, 255, 64], array_map(static fn (string $field): ?int => $metadata->getFieldMapping($field)->length, ['id', 'content', 'member', 'code']));
        self::assertSame(['columns' => ['content', 'member_id', 'code']], $metadata->table['uniqueConstraints']['phil_reaction_member'] ?? null);
        self::assertSame(['columns' => ['content', 'code']], $metadata->table['indexes']['phil_reaction_count'] ?? null);
        self::assertSame(['columns' => ['member_id']], $metadata->table['indexes']['phil_reaction_of_member'] ?? null);
        self::assertSame('datetime_utc_immutable', $metadata->getFieldMapping('reactedAt')->type);
    }

    /**
     * The moment is written in UTC and read back in UTC, whatever the time zone it was given in.
     */
    public function testTheMomentIsKeptInUtc(): void
    {
        $this->assertInstanceOf(EntityManager::class, $this->entityManager);
        $connection = $this->entityManager->getConnection();
        $content = ResourceReference::of('article', '1');
        $this->store->add(Reaction::of('r1', $content, 'ada', 'like', new DateTimeImmutable('2026-11-14 10:00:00.7', new DateTimeZone('America/Toronto'))));

        self::assertSame('2026-11-14 15:00:00', $connection->fetchOne('SELECT reacted_at FROM phil_reaction'));
        $read = $this->store->of($content, 'ada')[0]->reactedAt();
        self::assertSame(['2026-11-14T15:00:00+00:00', 'UTC'], [$read->format(\DATE_ATOM), $read->getTimezone()->getName()]);

        $reaction = $this->store->of($content, 'ada')[0];
        $reaction->changeTo('love', new DateTimeImmutable('2026-11-15 08:30:00', new DateTimeZone('Asia/Tokyo')));

        $this->store->save($reaction);

        self::assertSame('2026-11-14 23:30:00', $connection->fetchOne('SELECT reacted_at FROM phil_reaction'));
        self::assertSame('2026-11-14T23:30:00+00:00', $this->store->ofMember('ada')[0]->reactedAt()->format(\DATE_ATOM));
    }

    public function testARefusedDuplicateLeavesTheEntityManagerOpen(): void
    {
        $reaction = Reaction::of('r1', ResourceReference::of('article', '1'), 'ada', 'like', new DateTimeImmutable());
        $this->store->add($reaction);

        try {
            $this->store->add(Reaction::of('r2', ResourceReference::of('article', '1'), 'ada', 'like', new DateTimeImmutable()));
            self::fail('Accepted.');
        } catch (AlreadyReacted) {
            self::assertTrue($this->entityManager?->isOpen());
        }
    }

    #[Override]
    protected function store(): ReactionRepository
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Reaction::class)]);
        $this->entityManager = $entityManager;

        return new DoctrineReactionRepository($entityManager->getConnection());
    }
}
