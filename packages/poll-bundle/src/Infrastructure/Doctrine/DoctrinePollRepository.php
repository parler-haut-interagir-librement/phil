<?php

declare(strict_types=1);

namespace Phil\PollBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Repository\PollRepository;
use Phil\Resource\ResourceReference;

/**
 * The polls in the table `phil_poll`, through the entity manager; each save and removal is flushed. The revision of
 * the ballots is read and raised in the table itself, by one statement.
 */
final readonly class DoctrinePollRepository implements PollRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?Poll
    {
        return $this->entityManager->find(Poll::class, $id);
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->entityManager->getRepository(Poll::class)->findBy(['subject' => $subject->toString()], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function save(Poll $poll): void
    {
        $this->entityManager->persist($poll);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Poll $poll): void
    {
        $this->entityManager->remove($poll);
        $this->entityManager->flush();
    }

    #[Override]
    public function revision(string $id): int
    {
        $revision = $this->entityManager->getConnection()->fetchOne('SELECT revision FROM '.Poll::TABLE.' WHERE id = ?', [$id]);

        return is_numeric($revision) ? (int) $revision : 0;
    }

    #[Override]
    public function raiseRevision(string $id): void
    {
        $this->entityManager->getConnection()->executeStatement('UPDATE '.Poll::TABLE.' SET revision = revision + 1 WHERE id = ?', [$id]);
    }
}
