<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The store on SQLite in memory, its table created from the mapping as an application's schema tools create it, and
 * each mention read back from the database, the entity manager cleared.
 */
#[CoversClass(DoctrineMentionRepository::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    public function testASubjectMentionsAMemberOnce(): void
    {
        $this->store();
        $connection = $this->entityManager?->getConnection();
        self::assertInstanceOf(Connection::class, $connection);
        $table = $connection->createSchemaManager()->introspectTableByUnquotedName(RecordedMention::TABLE);
        self::assertTrue($table->hasIndex('phil_mention_member'));
        $connection->insert(RecordedMention::TABLE, ['id' => 'a', 'subject' => 'comment:1', 'member' => 'user:1', 'author' => 'user:9', 'created_at' => '2026-11-08 10:00:00']);

        $this->expectException(UniqueConstraintViolationException::class);

        $connection->insert(RecordedMention::TABLE, ['id' => 'b', 'subject' => 'comment:1', 'member' => 'user:1', 'author' => 'user:9', 'created_at' => '2026-11-08 10:00:00']);
    }

    public function testAMentionRemovedLeavesTheEntityManager(): void
    {
        $store = $this->store();
        $store->change([RecordedMention::record('a', ResourceReference::of('comment', '1'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable())], []);
        [$found] = $store->ofSubject(ResourceReference::of('comment', '1'));
        self::assertTrue($this->entityManager?->contains($found));

        $store->change([], [$found]);

        self::assertFalse($this->entityManager->contains($found));
        self::assertSame([], $store->ofSubject(ResourceReference::of('comment', '1')));
    }

    public function testAFailedChangeIsRolledBack(): void
    {
        $store = $this->store();
        $kept = RecordedMention::record('a', ResourceReference::of('comment', '1'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable());
        $store->change([$kept], []);
        $found = $this->reread($store, 'comment:1');

        try {
            // The same identifier twice: the primary key refuses the second row, and the removal is undone with it.
            $store->change([
                RecordedMention::record('b', ResourceReference::of('comment', '2'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable()),
                RecordedMention::record('b', ResourceReference::of('comment', '3'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable()),
            ], $found);
            self::fail('The second row is refused.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame(['a'], array_map(static fn (RecordedMention $mention): string => $mention->id, $this->reread($store, 'comment:1')));
        self::assertSame([], $this->reread($store, 'comment:2'));
    }

    #[Override]
    protected function store(): MentionRepository
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($this->entityManager)->createSchema([$this->entityManager->getClassMetadata(RecordedMention::class)]);

        return new DoctrineMentionRepository($this->entityManager);
    }

    #[Override]
    protected function reread(MentionRepository $store, string $subject): array
    {
        $this->entityManager?->clear();

        return parent::reread($store, $subject);
    }
}
