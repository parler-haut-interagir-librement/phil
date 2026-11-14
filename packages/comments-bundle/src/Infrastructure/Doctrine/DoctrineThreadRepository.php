<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommentsBundle\Domain\Exception\SuggestionDecided;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\Resource\ResourceReference;

/**
 * The threads in the table `phil_comment_thread`, through the entity manager; each save and removal is flushed, and a
 * decision on a suggestion is kept only while the stored one is pending.
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

    /**
     * Claims the decision by an `UPDATE` on the pending state, then flushes the thread read, in one transaction; a decision
     * that comes second is detached from the entity manager, which stays open and holds nothing of it.
     */
    #[Override]
    public function decide(Thread $thread): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            $connection->transactional(function () use ($connection, $thread): void {
                $claimed = $connection->executeStatement(
                    'UPDATE '.Thread::TABLE.' SET suggestion_state = ? WHERE id = ? AND suggestion_state = ?',
                    [$thread->suggestion()?->state->value, $thread->id, SuggestionState::Pending->value],
                );

                if (1 !== (int) $claimed) {
                    throw SuggestionDecided::onThread($thread->id);
                }

                $this->entityManager->flush();
            });
        } catch (SuggestionDecided $suggestionDecided) {
            $this->entityManager->detach($thread);

            throw $suggestionDecided;
        }
    }

    #[Override]
    public function remove(Thread $thread): void
    {
        $this->entityManager->remove($thread);
        $this->entityManager->flush();
    }
}
