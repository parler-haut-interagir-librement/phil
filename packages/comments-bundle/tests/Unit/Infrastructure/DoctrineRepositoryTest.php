<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineThreadRepository;
use PHPUnit\Framework\Attributes\CoversClass;

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
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        new SchemaTool($this->entityManager)->createSchema([$this->entityManager->getClassMetadata(Thread::class), $this->entityManager->getClassMetadata(Comment::class)]);

        return [new DoctrineThreadRepository($this->entityManager), new DoctrineCommentRepository($this->entityManager)];
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
}
