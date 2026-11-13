<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;

/**
 * The comments in the table `phil_comment`, through the entity manager; each save and removal is flushed.
 */
final readonly class DoctrineCommentRepository implements CommentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Comment
    {
        return $this->entityManager->find(Comment::class, $id);
    }

    #[Override]
    public function ofThreads(array $threads): array
    {
        return [] === $threads ? [] : $this->entityManager->getRepository(Comment::class)->findBy(['thread' => $threads], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(Comment $comment): void
    {
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Comment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }
}
