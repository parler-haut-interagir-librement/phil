<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Domain\Exception\SuggestionDecided;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineThreadRepository;
use Phil\CommentsBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

/**
 * The stores on SQLite in memory, their tables created from the mapping as an application's schema tools create them,
 * and each entity read back from the database, the entity manager cleared.
 */
#[CoversClass(DoctrineThreadRepository::class)]
#[CoversClass(DoctrineCommentRepository::class)]
final class DoctrineRepositoryTest extends RepositoryTestCase
{
    private ?EntityManager $entityManager = null;

    #[Override]
    protected function stores(): array
    {
        $this->entityManager = $this->manager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        new SchemaTool($this->entityManager)->createSchema([$this->entityManager->getClassMetadata(Thread::class), $this->entityManager->getClassMetadata(Comment::class)]);

        return [new DoctrineThreadRepository($this->entityManager), new DoctrineCommentRepository($this->entityManager)];
    }

    public function testOfTwoDecisionsOnASuggestionOnlyTheFirstIsKept(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $first = $this->manager($connection);
        $second = $this->manager($connection);
        new SchemaTool($first)->createSchema([$first->getClassMetadata(Thread::class), $first->getClassMetadata(Comment::class)]);
        $clock = new MockClock('2026-11-06 10:00:00 UTC');
        $ids = 0;
        $policy = new Policy(deciders: ['user:bob', 'user:cy']);
        $accepting = new Comments(new DoctrineThreadRepository($first), new DoctrineCommentRepository($first), $clock, $policy, ids: static function () use (&$ids): string {
            return 'id'.++$ids;
        });
        $rejecting = new Comments(new DoctrineThreadRepository($second), new DoctrineCommentRepository($second), $clock, $policy);
        $accepting->open(ResourceReference::of('page', '1'), Anchor::of(null, null, 0, 'cat'), ResourceReference::of('user', 'ada'), 'Why?', 'dog');
        $read = $rejecting->thread('id1');
        self::assertTrue($read->suggestion()?->isPending());

        $accepting->accept('id1', ResourceReference::of('user', 'bob'));

        try {
            $rejecting->reject('id1', ResourceReference::of('user', 'cy'));
            self::fail('a second decision on the same suggestion is refused');
        } catch (SuggestionDecided $suggestionDecided) {
            self::assertSame('The suggestion of the thread "id1" was decided meanwhile.', $suggestionDecided->getMessage());
        }

        self::assertTrue($second->isOpen(), 'the entity manager stays open');
        self::assertFalse($second->contains($read), 'the losing decision leaves the entity manager');
        $second->flush();
        self::assertSame(SuggestionState::Accepted, $second->find(Thread::class, 'id1')?->suggestion()?->state);
        $first->clear();
        $kept = $first->find(Thread::class, 'id1');
        self::assertSame([SuggestionState::Accepted, 'user:bob'], [$kept?->suggestion()?->state, $kept?->resolvedBy()?->toString()]);
    }

    #[Override]
    protected function reread(ThreadRepository $threads, string $id): Thread
    {
        $this->entityManager?->clear();

        return parent::reread($threads, $id);
    }

    #[Override]
    protected function rereadComment(CommentRepository $comments, string $id): Comment
    {
        $this->entityManager?->clear();

        return parent::rereadComment($comments, $id);
    }

    private function manager(Connection $connection): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager($connection, $config);
    }
}
