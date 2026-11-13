<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\Resource\ResourceReference;

/**
 * The threads in the table `phil_comment_thread`, through the entity manager; each save and removal is flushed.
 */
final readonly class DoctrineThreadRepository implements ThreadRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Thread
    {
        return $this->entityManager->find(Thread::class, $id);
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->entityManager->getRepository(Thread::class)->findBy(['subject' => $subject->toString()], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(Thread $thread): void
    {
        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Thread $thread): void
    {
        $this->entityManager->remove($thread);
        $this->entityManager->flush();
    }
}
